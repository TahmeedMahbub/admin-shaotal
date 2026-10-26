<?php

namespace App\Console\Commands;

use App\Models\ProductImportItem;
use App\Services\ProductImport\ImportProcessor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('product-imports:process {--limit=10 : Maximum items to process in this run} {--item= : Process one specific item id}')]
#[Description('Fetch and extract queued product import URLs (Phase 1; never creates products)')]
class ProcessProductImports extends Command
{
    public function handle(ImportProcessor $processor): int
    {
        $ids = $this->option('item') ? collect([(int) $this->option('item')]) : $processor->due((int) $this->option('limit'));

        foreach ($ids as $id) {
            $ran = $processor->process($id);
            $item = ProductImportItem::find($id);
            $this->line(sprintf('#%d %s%s', $id, $ran ? ($item?->status ?? 'missing') : 'skipped (not due or claimed elsewhere)', $item?->last_error ? ' - '.$item->last_error : ''));
        }
        $this->info($ids->count().' item(s) handled.');

        return self::SUCCESS;
    }
}
