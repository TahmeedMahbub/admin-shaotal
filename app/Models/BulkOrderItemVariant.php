<?php

namespace App\Models;

use App\Models\Concerns\TracksAdmin;
use Illuminate\Database\Eloquent\Model;

class BulkOrderItemVariant extends Model
{
    use TracksAdmin;

    protected $table = 'bulk_order_item_variants';

    protected $guarded = [];

    public $timestamps = false;
}
