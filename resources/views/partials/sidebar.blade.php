@php
  $menu = [
    ['Dashboard', 'mdi-view-dashboard-outline', 'dashboard', null],
    ['Catalog', 'mdi-package-variant-closed', null, [
      ['Products', 'products.*', 'products.index'], ['Import Products', 'product-imports.*', 'product-imports.index'], ['Categories', 'categories.*', 'categories.index'],
      ['Subcategories', 'subcategories.*', 'subcategories.index'], ['Brands', 'brands.*', 'brands.index'],
      ['Size Guides', 'size-guides.*', 'size-guides.index']]],
    ['Sales', 'mdi-cart-outline', null, [
      ['Orders', 'orders.*', 'orders.index'], ['Customers', 'customers.*', 'customers.index'],
      ['Abandoned Carts', 'abandoned-carts.*', 'abandoned-carts.index']]],
    ['Requests', 'mdi-hands-pray', null, [
      ['Sourcing Requests', 'sourcing-requests.*', 'sourcing-requests.index'], ['Bulk Orders', 'bulk-orders.*', 'bulk-orders.index']]],
    ['Marketing', 'mdi-bullhorn-outline', null, [
      ['Offers / Discounts', 'offers.*', 'offers.index'], ['Coupons', 'coupons.*', 'coupons.index']]],
    ['Content', 'mdi-web', null, [
      ['Banners', 'banners.*', 'banners.index'], ['Homepage', 'homepage.*', 'homepage.edit'],
      ['Pages', 'pages.*', 'pages.index'], ['Navigation', 'navigation.*', 'navigation.index'],
      ['Site Content', 'site-contents.*', 'site-contents.index']]],
    ['Engagement', 'mdi-star-outline', null, [
      ['Reviews', 'reviews.*', 'reviews.index'], ['Wishlist', 'wishlists.*', 'wishlists.index'],
      ['Subscribers', 'subscribers.*', 'subscribers.index'], ['Contacts', 'contacts.*', 'contacts.index']]],
  ];
@endphp
<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
  <div class="app-brand demo">
    <a href="{{ route('dashboard') }}" class="app-brand-link gap-2 align-items-center">
      <img src="{{ asset('assets/img/logo/logo1.png') }}" alt="Shaotal Admin" class="app-brand-img" style="height: 26px; width: auto; max-width: 170px;">
    </a>
    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block"><i class="mdi mdi-chevron-left mdi-24px align-middle"></i></a>
  </div>
  <div class="menu-inner-shadow"></div>
  <ul class="menu-inner py-2">
    @foreach ($menu as [$label, $icon, $route, $children])
      @if ($children === null)
        <li class="menu-item {{ request()->routeIs($route) ? 'active' : '' }}">
          <a href="{{ route($route) }}" class="menu-link"><i class="menu-icon tf-icons mdi {{ $icon }}"></i><div>{{ $label }}</div></a>
        </li>
      @else
        @php $open = collect($children)->contains(fn ($c) => request()->routeIs($c[1])); @endphp
        <li class="menu-item {{ $open ? 'active open' : '' }}">
          <a href="javascript:void(0);" class="menu-link menu-toggle"><i class="menu-icon tf-icons mdi {{ $icon }}"></i><div>{{ $label }}</div></a>
          <ul class="menu-sub">
            @foreach ($children as [$cl, $pattern, $cr])
              <li class="menu-item {{ request()->routeIs($pattern) ? 'active' : '' }}">
                <a href="{{ route($cr) }}" class="menu-link"><div>{{ $cl }}</div>@if (str_starts_with($cr, 'pending.'))<span class="badge bg-label-warning ms-auto fs-9">Pending</span>@endif</a>
              </li>
            @endforeach
          </ul>
        </li>
      @endif
    @endforeach
  </ul>
</aside>
