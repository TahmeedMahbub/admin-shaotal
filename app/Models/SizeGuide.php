<?php

namespace App\Models;

use App\Models\Concerns\TracksAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SizeGuide extends Model
{
    use TracksAdmin;

    protected $table = 'size_guides';

    protected $guarded = [];

    public function sizeAttributes(): HasMany
    {
        return $this->hasMany(SizeGuideAttribute::class)->orderBy('sort_order');
    }

    public function sizeValues(): HasMany
    {
        return $this->hasMany(SizeGuideValue::class)->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
