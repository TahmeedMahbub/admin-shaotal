<?php

namespace App\Models;

use App\Models\Concerns\TracksAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BulkOrderRequestItem extends Model
{
    use TracksAdmin;

    protected $table = 'bulk_order_request_items';

    protected $guarded = [];

    public $timestamps = false;

    public function variants(): HasMany
    {
        return $this->hasMany(BulkOrderItemVariant::class, 'bulk_order_request_item_id');
    }
}
