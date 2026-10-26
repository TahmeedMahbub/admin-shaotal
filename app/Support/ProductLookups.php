<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Category;
use App\Models\SizeGuide;
use App\Models\Subcategory;
use Illuminate\Support\Facades\Cache;

/**
 * Rarely-changing dropdown data for the product form (categories, subcategories, brands, size guides).
 * The database is remote, so each avoided query saves a network round trip. Cached as plain arrays and
 * flushed whenever one of the source models changes (see AppServiceProvider); the TTL is a safety net.
 */
class ProductLookups
{
    private const KEY = 'product-form-lookups';

    /** @return array{categories: array<int,string>, subcategories: array<int,array>, brands: array<int,string>, guides: array<int,array>} */
    public static function all(): array
    {
        return Cache::remember(self::KEY, 600, fn () => [
            'categories' => Category::orderBy('sort_order')->pluck('name', 'id')->all(),
            'subcategories' => Subcategory::orderBy('sort_order')->get(['id', 'category_id', 'name'])->map->only(['id', 'category_id', 'name'])->all(),
            'brands' => Brand::orderBy('name')->pluck('name', 'id')->all(),
            'guides' => SizeGuide::with('sizeValues:id,size_guide_id,size_label,sort_order')->orderBy('title')->get(['id', 'title'])
                ->map(fn ($g) => ['id' => $g->id, 'title' => $g->title, 'sizes' => $g->sizeValues->sortBy('sort_order')->pluck('size_label')->unique()->values()->all()])->all(),
        ]);
    }

    public static function flush(): void
    {
        Cache::forget(self::KEY);
    }
}
