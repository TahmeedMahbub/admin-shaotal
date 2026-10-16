<?php

namespace App\Models;

use App\Models\Concerns\TracksAdmin;
use Illuminate\Database\Eloquent\Model;

class ProductColor extends Model
{
    use TracksAdmin;

    protected $table = 'product_colors';

    protected $guarded = [];
}
