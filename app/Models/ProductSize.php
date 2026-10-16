<?php

namespace App\Models;

use App\Models\Concerns\TracksAdmin;
use Illuminate\Database\Eloquent\Model;

class ProductSize extends Model
{
    use TracksAdmin;

    protected $table = 'product_sizes';

    protected $guarded = [];
}
