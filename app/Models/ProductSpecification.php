<?php

namespace App\Models;

use App\Models\Concerns\TracksAdmin;
use Illuminate\Database\Eloquent\Model;

class ProductSpecification extends Model
{
    use TracksAdmin;

    protected $table = 'product_specifications';

    protected $guarded = [];
}
