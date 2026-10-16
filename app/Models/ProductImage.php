<?php

namespace App\Models;

use App\Models\Concerns\TracksAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductImage extends Model
{
    use TracksAdmin;

    protected $table = 'product_images';

    protected $guarded = [];

    public $timestamps = false;

    public function colors(): BelongsToMany
    {
        return $this->belongsToMany(ProductColor::class, 'product_image_colors', 'image_id', 'color_id');
    }
}
