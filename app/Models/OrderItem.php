<?php

namespace App\Models;

use App\Models\Concerns\TracksAdmin;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use TracksAdmin;

    protected $table = 'order_items';

    protected $guarded = [];

    public $timestamps = false;
}
