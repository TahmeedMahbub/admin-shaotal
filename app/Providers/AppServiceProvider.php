<?php

namespace App\Providers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\SizeGuide;
use App\Models\SizeGuideValue;
use App\Models\Subcategory;
use App\Support\ProductLookups;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        foreach ([Category::class, Subcategory::class, Brand::class, SizeGuide::class, SizeGuideValue::class] as $model) {
            $model::saved(fn () => ProductLookups::flush());
            $model::deleted(fn () => ProductLookups::flush());
        }
    }
}
