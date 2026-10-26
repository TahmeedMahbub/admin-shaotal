<?php

namespace App\Services\ProductImport;

use App\Models\ProductImportItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Fetches a queued page, stores the extraction and a first editable draft on the item. Never creates or changes products. */
class ImportProcessor
{
    /** Minutes after which an item stuck in "processing" (crashed run) may be claimed again. */
    private const STALE_LOCK_MINUTES = 10;

    public function __construct(private PageFetcher $fetcher, private ProductExtractor $extractor) {}

    /** Items due for a run: queued and past their back-off, or abandoned mid-run. */
    public function due(int $limit): Collection
    {
        return ProductImportItem::query()
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('status', 'queued')->where(fn ($d) => $d->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now())))
                ->orWhere(fn ($w) => $w->where('status', 'processing')->where('locked_at', '<', now()->subMinutes(self::STALE_LOCK_MINUTES))))
            ->orderBy('id')->limit($limit)->pluck('id');
    }

    /** Manual retry: forget previous attempts and run again right away. */
    public function reset(ProductImportItem $item): void
    {
        if ($item->status === 'failed') {
            $item->update(['status' => 'queued', 'attempts' => 0, 'next_attempt_at' => null, 'last_error' => null]);
        }
    }

    /** @return bool true when the item was processed (success or recorded failure), false when another worker owns it */
    public function process(int $itemId): bool
    {
        // Atomic claim so two workers (or a double click) never fetch the same item.
        $claimed = ProductImportItem::where('id', $itemId)
            ->where(fn ($q) => $q->where('status', 'queued')->orWhere(fn ($w) => $w->where('status', 'processing')->where('locked_at', '<', now()->subMinutes(self::STALE_LOCK_MINUTES))))
            ->update(['status' => 'processing', 'locked_at' => now(), 'attempts' => DB::raw('attempts + 1')]);
        if (! $claimed) {
            return false;
        }

        $item = ProductImportItem::findOrFail($itemId);
        try {
            $page = $this->fetcher->fetch($item->source_url);
            $data = $this->extractor->extract($page['html'], $page['url']);
            $data['source_url'] = $item->source_url;
            $data['final_url'] = $page['url'];

            if (! $data['name'] && $data['price'] === null && ! $data['images']) {
                throw new ImportException('No product data was found on this page. It may need a login or render with JavaScript.', false);
            }
            // Draft values are built from the page only; a name already in the catalogue blocks saving until it is changed.
            $draft = DraftBuilder::build($data);
            $existing = DuplicateGuard::find($draft['name'], null);
            if ($existing) {
                $data['warnings'][] = DuplicateGuard::message($existing);
            }

            $item->update([
                'status' => $existing ? 'duplicate' : 'needs_review', 'raw_data' => $data, 'draft_data' => $draft,
                'source_images' => array_column($data['images'], 'url'), 'last_error' => null, 'locked_at' => null, 'next_attempt_at' => null,
            ]);
        } catch (ImportException $e) {
            $this->fail($item, $e->getMessage(), $e->retryable);
        } catch (Throwable $e) {
            report($e);
            $this->fail($item, 'Unexpected error while extracting ('.class_basename($e).').', true);
        }

        return true;
    }

    private function fail(ProductImportItem $item, string $message, bool $retryable): void
    {
        $retry = $retryable && $item->attempts < ProductImportItem::MAX_ATTEMPTS;
        $item->update([
            'status' => $retry ? 'queued' : 'failed',
            'last_error' => mb_substr($message, 0, 500),
            'locked_at' => null,
            // 2, 4 minutes after the 1st and 2nd failure.
            'next_attempt_at' => $retry ? now()->addMinutes(2 ** $item->attempts) : null,
        ]);
    }
}
