<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductImportBatch extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'product_import_batches';

    protected $guarded = [];

    public function items(): HasMany
    {
        return $this->hasMany(ProductImportItem::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }
}
