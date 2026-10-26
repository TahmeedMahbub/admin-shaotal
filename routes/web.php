<?php

use App\Http\Controllers\AbandonedCartController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\BulkOrderController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomepageController;
use App\Http\Controllers\NavigationController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ProductImportImageController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SiteContentController;
use App\Http\Controllers\SizeGuideController;
use App\Http\Controllers\SourcingRequestController;
use App\Http\Controllers\SubcategoryController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:admin')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth:admin')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('product-imports', [ProductImportController::class, 'index'])->name('product-imports.index');
    Route::get('product-imports/create', [ProductImportController::class, 'create'])->name('product-imports.create');
    Route::post('product-imports', [ProductImportController::class, 'store'])->name('product-imports.store');
    Route::get('product-imports/items/{item}', [ProductImportController::class, 'item'])->name('product-imports.item');
    Route::get('product-imports/items/{item}/review', [ProductImportController::class, 'review'])->name('product-imports.review');
    Route::post('product-imports/items/{item}/save', [ProductImportController::class, 'save'])->name('product-imports.save');
    Route::post('product-imports/items/{item}/images/original', [ProductImportImageController::class, 'original'])->name('product-imports.images.original');
    Route::post('product-imports/items/{item}/images/generate', [ProductImportImageController::class, 'generate'])->name('product-imports.images.generate');
    Route::post('product-imports/items/{item}/images/approve', [ProductImportImageController::class, 'approve'])->name('product-imports.images.approve');
    Route::get('product-imports/items/{item}/images/preview/{id}', [ProductImportImageController::class, 'preview'])->name('product-imports.images.preview');
    Route::post('product-imports/items/{item}/run', [ProductImportController::class, 'run'])->name('product-imports.run');
    Route::get('product-imports/{batch}', [ProductImportController::class, 'show'])->name('product-imports.show');
    Route::post('products/upload-image', [ProductController::class, 'storeImage'])->name('products.upload-image');
    Route::get('products/{product}/duplicate', [ProductController::class, 'duplicate'])->name('products.duplicate');
    Route::resource('products', ProductController::class)->except('show');
    Route::post('categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');
    Route::resource('categories', CategoryController::class)->except('show');
    Route::post('subcategories/reorder', [SubcategoryController::class, 'reorder'])->name('subcategories.reorder');
    Route::resource('subcategories', SubcategoryController::class)->except('show');
    Route::resource('brands', BrandController::class)->except('show');
    Route::resource('size-guides', SizeGuideController::class)->parameters(['size-guides' => 'size_guide']);

    Route::resource('orders', OrderController::class)->only(['index', 'show']);
    Route::post('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
    Route::post('orders/{order}/payment', [OrderController::class, 'updatePayment'])->name('orders.payment');
    Route::post('orders/{order}/note', [OrderController::class, 'updateNote'])->name('orders.note');
    Route::resource('customers', CustomerController::class)->only(['index', 'show', 'edit', 'update']);
    Route::resource('abandoned-carts', AbandonedCartController::class)->only(['index', 'show', 'update'])->parameters(['abandoned-carts' => 'cart']);

    Route::resource('sourcing-requests', SourcingRequestController::class)->only(['index', 'show', 'update', 'destroy'])->parameters(['sourcing-requests' => 'sourcing_request']);
    Route::resource('bulk-orders', BulkOrderController::class)->only(['index', 'show', 'update', 'destroy'])->parameters(['bulk-orders' => 'bulk_order']);

    Route::resource('coupons', CouponController::class);
    Route::post('banners/reorder', [BannerController::class, 'reorder'])->name('banners.reorder');
    Route::resource('banners', BannerController::class);
    Route::resource('reviews', ReviewController::class);
    Route::resource('wishlists', WishlistController::class)->only(['index', 'create', 'store', 'show', 'destroy'])->parameters(['wishlists' => 'wishlist']);
    Route::resource('subscribers', SubscriberController::class);
    Route::resource('contacts', ContactController::class);

    Route::resource('offers', OfferController::class);
    Route::resource('pages', PageController::class);
    Route::resource('navigation', NavigationController::class)->except('show');
    Route::resource('site-contents', SiteContentController::class)->only(['index', 'show', 'edit', 'update'])->parameters(['site-contents' => 'site_content']);
    Route::get('homepage', [HomepageController::class, 'edit'])->name('homepage.edit');
    Route::put('homepage', [HomepageController::class, 'update'])->name('homepage.update');
});
