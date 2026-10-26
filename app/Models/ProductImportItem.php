<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImportItem extends Model
{
    public const MAX_ATTEMPTS = 3;

    protected $table = 'product_import_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array', 'draft_data' => 'array', 'source_images' => 'array', 'generated_images' => 'array',
            'next_attempt_at' => 'datetime', 'locked_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductImportBatch::class, 'batch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
