@extends('layouts.admin')
@section('title', 'Products')
@section('section', 'Catalog')

@section('content')
@php use App\Support\Fmt; @endphp
<x-admin.page-header title="Products Catalog" subtitle="Manage your store products, pricing, stock, colors, and variant options.">
  <a href="{{ route('products.create') }}" class="btn btn-beige d-flex align-items-center gap-1"><i class="mdi mdi-plus fs-5"></i> Add Product</a>
</x-admin.page-header>

<x-admin.filters :statuses="['active', 'draft', 'inactive', 'archived']" placeholder="Search by name, SKU...">
  <select name="category_id" class="form-select" style="max-width: 200px;" onchange="this.form.submit()">
    <option value="">All Categories</option>
    @foreach ($categories as $id => $n)<option value="{{ $id }}" @selected((string) request('category_id') === (string) $id)>{{ $n }}</option>@endforeach
  </select>
  <select name="brand_id" class="form-select" style="max-width: 200px;" onchange="this.form.submit()">
    <option value="">All Brands</option>
    @foreach ($brands as $id => $n)<option value="{{ $id }}" @selected((string) request('brand_id') === (string) $id)>{{ $n }}</option>@endforeach
  </select>
  <select name="stock" class="form-select" style="max-width: 160px;" onchange="this.form.submit()">
    <option value="">All Stock</option>
    <option value="low" @selected(request('stock') === 'low')>Low Stock</option>
  </select>
</x-admin.filters>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Product</th><th>Category</th><th>Brand</th><th>Price</th><th>Variants</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        @forelse ($rows as $p)
          @php
            $stock = $p->has_variants ? (int) $p->variants_stock : (int) $p->stock_qty;
            $low = $stock <= ($p->low_stock_threshold ?? 5);
            $price = (float) $p->price; $sale = $p->selling_price !== null ? (float) $p->selling_price : null;
          @endphp
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <x-admin.thumb :src="$p->image_url" icon="mdi-package-variant" :size="48" />
                <div>
                  <a href="{{ route('products.edit', $p) }}" class="fw-bold text-dark fs-8">{{ $p->name }}</a>
                  <div><small class="text-muted">{{ $p->subcategory->name ?? '' }} @if ($p->sku) · SKU {{ $p->sku }} @endif</small></div>
                </div>
              </div>
            </td>
            <td>{{ $p->category->name ?? '—' }}</td>
            <td>@if ($p->brand)<span class="badge bg-label-secondary">{{ $p->brand->name }}</span>@else — @endif</td>
            <td>
              @if ($sale !== null && $sale < $price)
                <div class="fw-bold">{{ Fmt::money($sale) }}</div>
                <small class="text-muted text-decoration-line-through">{{ Fmt::money($price) }}</small>
                <span class="badge bg-label-success">{{ round((1 - $sale / $price) * 100) }}% OFF</span>
              @else
                <div class="fw-bold">{{ Fmt::money($price) }}</div>
              @endif
            </td>
            <td>
              @if ($p->has_variants)<span class="badge bg-label-info">{{ $p->variants_count }} Variants</span>@endif
              @if ($p->availability === 'preorder')<span class="badge bg-label-warning">Pre-order</span>
              @else<span class="badge {{ $stock <= 0 ? 'bg-label-danger' : ($low ? 'bg-label-warning' : 'bg-label-success') }}">{{ $stock }} {{ $low && $stock > 0 ? 'left (Low)' : 'in stock' }}</span>@endif
            </td>
            <td><x-admin.badge :status="$p->status" />@if ($p->visibility === 'hidden')<span class="badge bg-label-secondary">Hidden</span>@endif</td>
            <td class="text-end"><x-admin.row-actions :edit="route('products.edit', $p)" :delete="route('products.destroy', $p)" label="product" /></td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No products found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>
@endsection
