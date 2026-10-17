@extends('layouts.admin')
@section('title', 'Wishlist')
@section('section', 'Engagement')

@section('content')
@php use App\Support\Fmt; @endphp
<x-admin.page-header title="Customer Wishlists" subtitle="Track products customers saved to their wishlists.">
  <a href="{{ route('wishlists.create') }}" class="btn btn-beige d-flex align-items-center gap-1"><i class="mdi mdi-plus fs-5"></i> Add Item</a>
</x-admin.page-header>

<x-admin.filters placeholder="Search product or customer...">
  <select name="stock" class="form-select" style="max-width: 190px;" onchange="this.form.submit()">
    <option value="">All Stock Statuses</option><option value="in" @selected(request('stock') === 'in')>In Stock</option><option value="out" @selected(request('stock') === 'out')>Out of Stock</option>
  </select>
  <select name="sort" class="form-select" style="max-width: 190px;" onchange="this.form.submit()">
    @foreach (['' => 'Newest Added', 'oldest' => 'Oldest Added', 'price_high' => 'Price: High to Low', 'price_low' => 'Price: Low to High'] as $k => $l)<option value="{{ $k }}" @selected((string) request('sort') === (string) $k)>{{ $l }}</option>@endforeach
  </select>
</x-admin.filters>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Wishlist ID</th><th>Customer</th><th>Product Details</th><th>Current Price</th><th>Added Date</th><th>Availability</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        @forelse ($rows as $w)
          <tr data-row-href="{{ route('wishlists.show', $w) }}">
            <td><a href="{{ route('wishlists.show', $w) }}" class="fw-bold text-dark">#WSH-{{ $w->id }}</a></td>
            <td>{{ trim(($w->customer->first_name ?? '').' '.($w->customer->last_name ?? '')) }}<br><small class="text-muted">{{ $w->customer->email ?? '' }}</small></td>
            <td><a href="{{ route('products.edit', $w->product_id) }}" class="fw-bold text-dark fs-8">{{ $w->product->name }}</a></td>
            <td class="fw-bold">{{ Fmt::money($w->product->selling_price ?? $w->product->price) }}</td>
            <td>{{ $w->created_at->format('M d, Y') }}</td>
            <td><x-admin.badge :status="$w->product->availability" /></td>
            <td class="text-end"><x-admin.row-actions :show="route('wishlists.show', $w)" :delete="route('wishlists.destroy', $w)" label="wishlist item" /></td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No wishlist items found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>
@endsection
