<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SavesProducts;
use App\Models\Product;
use App\Models\ProductImportBatch;
use App\Models\ProductImportItem;
use App\Services\ProductImport\DraftBuilder;
use App\Services\ProductImport\DuplicateGuard;
use App\Services\ProductImport\ImportProcessor;
use App\Services\ProductImport\UrlNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductImportController extends Controller
{
    use SavesProducts;

    private const REVIEWABLE = ['needs_review', 'ready', 'draft_saved', 'published', 'duplicate'];

    private const MAX_URLS = 200;

    public function index()
    {
        $batches = ProductImportBatch::query()->withCount('items')->latest('id')->paginate($this->perPage());
        $counts = ProductImportItem::whereIn('batch_id', $batches->pluck('id'))
            ->selectRaw('batch_id, status, COUNT(*) as n')->groupBy('batch_id', 'status')->get()
            ->groupBy('batch_id')->map(fn ($g) => $g->pluck('n', 'status'));

        return view('product-imports.index', ['batches' => $batches, 'counts' => $counts]);
    }

    public function create()
    {
        return view('product-imports.create', ['max' => self::MAX_URLS]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:190'],
            'urls' => ['required', 'string', 'max:100000'],
        ]);

        $lines = collect(preg_split('/[\r\n]+/', $request->urls))->map(fn ($l) => trim($l))->filter();
        if ($lines->count() > self::MAX_URLS) {
            return back()->withInput()->withErrors(['urls' => 'Please submit at most '.self::MAX_URLS.' URLs at a time.']);
        }

        $invalid = [];
        $candidates = []; // hash => [source, normalized]
        $repeated = 0;
        foreach ($lines as $line) {
            $normalized = UrlNormalizer::normalize($line);
            if (! $normalized || strlen($line) > 2048 || strlen($normalized) > 2048) {
                $invalid[] = $line;

                continue;
            }
            $hash = UrlNormalizer::hash($normalized);
            if (isset($candidates[$hash])) {
                $repeated++;

                continue;
            }
            $candidates[$hash] = ['source' => $line, 'normalized' => $normalized];
        }

        $existing = ProductImportItem::whereIn('url_hash', array_keys($candidates))->get(['id', 'batch_id', 'url_hash', 'status', 'source_url'])->keyBy('url_hash');
        $fresh = array_diff_key($candidates, $existing->all());

        $batch = null;
        $created = 0;
        if ($fresh) {
            DB::transaction(function () use ($request, $fresh, &$batch, &$created) {
                $batch = ProductImportBatch::create(['name' => $request->name ?: null, 'created_by' => auth('admin')->id()]);
                $rows = collect($fresh)->map(fn ($c, $hash) => [
                    'batch_id' => $batch->id, 'source_url' => $c['source'], 'normalized_url' => $c['normalized'], 'url_hash' => $hash,
                    'status' => 'queued', 'created_by' => auth('admin')->id(), 'created_at' => now(), 'updated_at' => now(),
                ])->values()->all();
                // insertOrIgnore: a concurrent submit of the same URL loses to the UNIQUE key instead of erroring.
                $created = DB::table('product_import_items')->insertOrIgnore($rows);
            });
        }

        $skipped = $existing->map(fn ($i) => ['url' => $i->source_url, 'item' => $i->id, 'batch' => $i->batch_id, 'status' => $i->status])->values()->all();
        $flash = ['created' => $created, 'repeated' => $repeated, 'skipped' => $skipped, 'invalid' => $invalid];

        if ($batch && $created) {
            return redirect()->route('product-imports.show', $batch)->with('import_result', $flash)
                ->with('success', "$created URL(s) queued.");
        }
        if ($batch) {
            $batch->delete();
        }

        return back()->withInput()->with('import_result', $flash)->withErrors(['urls' => 'Nothing new to import: every URL was already imported, repeated, or invalid.']);
    }

    public function show(ProductImportBatch $batch)
    {
        $items = $batch->items()->select('id', 'batch_id', 'source_url', 'status', 'attempts', 'next_attempt_at', 'last_error', 'product_id')
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.name')) as ex_name, JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.price')) as ex_price, JSON_UNQUOTE(JSON_EXTRACT(raw_data, '$.currency')) as ex_currency, JSON_UNQUOTE(JSON_EXTRACT(source_images, '$[0]')) as ex_image, JSON_LENGTH(source_images) as ex_images")
            ->orderBy('id')->paginate(100);

        $counts = $batch->items()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('product-imports.show', ['batch' => $batch, 'items' => $items, 'counts' => $counts]);
    }

    public function item(ProductImportItem $item)
    {
        return view('product-imports.item', ['item' => $item->load('batch')]);
    }

    /** Fetch now / Retry: runs one item synchronously so the result is visible immediately. */
    public function run(ProductImportItem $item, ImportProcessor $processor)
    {
        $processor->reset($item);
        $item->refresh();
        if ($item->status !== 'queued') {
            return back()->with('success', 'This item is not waiting to be fetched.');
        }
        // A back-off delay only applies to automatic runs; an explicit click runs it now.
        $item->update(['next_attempt_at' => null]);
        $processor->process($item->id);
        $item->refresh();

        $ok = in_array($item->status, self::REVIEWABLE, true);

        return back()->with($ok ? 'success' : 'error', $ok ? 'Product data extracted. It is ready to review.' : 'Fetch did not succeed: '.$item->last_error);
    }

    /** The product form, prefilled from the staged draft (or from the saved product once a draft exists). */
    public function review(ProductImportItem $item)
    {
        if (! in_array($item->status, self::REVIEWABLE, true)) {
            return redirect()->route('product-imports.item', $item)->with('error', 'This item has no extracted data to review yet.');
        }

        $product = $this->linkedProduct($item);
        if ($product) {
            $data = $this->formData($product);
        } else {
            if (! session()->hasOldInput()) {
                session()->now('_old_input', $item->draft_data ?: DraftBuilder::build($item->raw_data ?? []));
            }
            $data = $this->formData(new Product(['has_variants' => 0, 'availability' => 'in_stock', 'visibility' => 'visible', 'status' => 'draft', 'stock_qty' => 0]));
        }

        $current = $product ? $this->currentValues($product) : (session()->getOldInput() ?: []);
        $duplicate = DuplicateGuard::find(old('name', $product->name ?? ($item->draft_data['name'] ?? '')), old('sku', $product->sku ?? ''), $product?->id);

        return view('products.form', $data + [
            'import' => $item, 'importRaw' => $item->raw_data ?? [], 'importProduct' => $product,
            'manualFields' => DraftBuilder::manualFields($current), 'importDuplicate' => $duplicate,
        ]);
    }

    /** One endpoint for the explicit review actions: progress, approve, draft, publish (and update for a published product). */
    public function save(Request $request, ProductImportItem $item)
    {
        $action = $request->input('import_action');
        abort_unless(in_array($action, ['progress', 'approve', 'draft', 'publish', 'update'], true), 422);
        abort_unless(in_array($item->status, self::REVIEWABLE, true), 409, 'Nothing to save yet.');

        $product = $this->linkedProduct($item);
        abort_if($product && ! in_array($action, ['draft', 'publish', 'update'], true), 409, 'This item already has a product; use Save Draft, Publish or Update.');
        abort_if($action === 'update' && $item->status !== 'published', 409);

        $snapshot = $request->except(['_token', '_method', 'import_action', 'save_draft']);
        $existing = DuplicateGuard::find($request->input('name'), $request->input('sku'), $product?->id);

        if ($action === 'progress') {
            $item->update(['draft_data' => $snapshot, 'status' => $existing ? 'duplicate' : 'needs_review']);

            return back()->with('success', $existing ? 'Progress saved, but this name or SKU matches an existing product.' : 'Progress saved. Nothing was created in the catalogue.');
        }

        if ($existing) {
            throw ValidationException::withMessages(['name' => DuplicateGuard::message($existing)]);
        }

        $request->merge(['save_draft' => $action === 'draft' ? 1 : 0]);
        if ($action === 'publish') {
            $request->merge(['status' => 'active', 'visibility' => 'visible']);
        }
        $data = $this->validated($request, $product);
        if ($action !== 'draft') {
            $this->checkReady($data);
        }

        if ($action === 'approve') {
            $item->update(['draft_data' => $snapshot, 'status' => 'ready']);

            return back()->with('success', 'Approved. This product passed every publish check; use Save Draft or Publish when you are ready.');
        }

        $saved = DB::transaction(fn () => $this->persist($product ?? new Product, $data));
        $item->update([
            'product_id' => $saved->id, 'draft_data' => $snapshot,
            'status' => match ($action) {
                'publish', 'update' => 'published', default => 'draft_saved'
            },
        ]);

        $msg = ['draft' => 'Draft saved. It is hidden from the storefront.', 'publish' => 'Published. The product is now live on the storefront.', 'update' => 'Product updated.'][$action];

        return redirect()->route('product-imports.review', $item)->with('success', $msg);
    }

    /** A product only counts as ours if this import created it after the item existed, so an existing product can never be overwritten. */
    private function linkedProduct(ProductImportItem $item): ?Product
    {
        if (! $item->product_id) {
            return null;
        }
        $product = Product::find($item->product_id);
        abort_if(! $product || $product->created_at < $item->created_at, 409, 'The linked product does not belong to this import.');

        return $product;
    }

    /** Extra requirements for approving or publishing, on top of the normal product validation. */
    private function checkReady(array $data): void
    {
        $e = [];
        if (empty($data['images'])) {
            $e['images'] = 'Add at least one approved product image first.';
        }
        if ((float) ($data['price'] ?? 0) <= 0) {
            $e['price'] = 'Enter the A$ selling price (it is not converted from the source currency).';
        }
        if (($data['availability'] ?? '') === 'in_stock') {
            $stock = $data['has_variants']
                ? collect($data['variants'] ?? [])->filter(fn ($v) => ! empty($v['is_active']))->sum(fn ($v) => (int) ($v['stock_qty'] ?? 0))
                : (int) ($data['stock_qty'] ?? 0);
            if ($stock <= 0) {
                $e['stock_qty'] = 'Enter the stock quantity, or set availability to something other than In stock.';
            }
        }
        if ($e) {
            throw ValidationException::withMessages($e);
        }
    }

    /** Form-shaped values of a saved product, for the "manual entry" checklist. */
    private function currentValues(Product $p): array
    {
        $p->loadMissing(['specification', 'colors', 'variants', 'images']);

        return [
            'price' => $p->price > 0 ? $p->price : '', 'sku' => $p->sku, 'short_description' => $p->short_description, 'category_id' => $p->category_id,
            'subcategory_id' => $p->subcategory_id, 'brand_id' => $p->brand_id, 'has_variants' => $p->has_variants, 'stock_qty' => $p->stock_qty,
            'spec' => $p->specification?->only(['material', 'fabric', 'dimensions', 'fit', 'pattern', 'care_instructions']) ?? [],
            'seo_title' => $p->seo_title, 'seo_description' => $p->seo_description,
            'variants' => $p->variants->map(fn ($v) => ['stock_qty' => $v->stock_qty])->all(),
            'colors' => $p->colors->map(fn ($c) => ['hex_code' => $c->hex_code])->all(), 'images' => $p->images->all(),
        ];
    }
}
