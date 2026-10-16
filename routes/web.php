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

    Route::resource('products', ProductController::class)->except('show');
    Route::resource('categories', CategoryController::class)->except('show');
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
