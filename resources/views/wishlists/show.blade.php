@extends('layouts.admin')
@section('title', 'Wishlist Item')
@section('section', 'Wishlist')

@section('content')
<x-admin.page-header title="Wishlist Item" :subtitle="'Saved on '.$item->created_at->format('M d, Y h:i A')">
  <a href="{{ route('wishlists.index') }}" class="btn btn-outline-secondary">Back</a>
  <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-delete-url="{{ route('wishlists.destroy', $item) }}" data-delete-label="wishlist item">Remove</button>
</x-admin.page-header>
<div class="row g-4">
  <div class="col-md-6"><div class="card"><div class="card-header py-3 fs-6">Product</div><div class="card-body fs-8">
    <a href="{{ route('products.edit', $item->product_id) }}" class="fw-bold">{{ $item->product->name }}</a>
    <div>SKU: {{ $item->product->sku ?: '—' }}</div><div>Price: {{ \App\Support\Fmt::money($item->product->selling_price ?? $item->product->price) }}</div>
    <x-admin.badge :status="$item->product->availability" />
  </div></div></div>
  <div class="col-md-6"><div class="card"><div class="card-header py-3 fs-6">Customer</div><div class="card-body fs-8">
    <a href="{{ route('customers.show', $item->customer_id) }}" class="fw-bold">{{ trim($item->customer->first_name.' '.$item->customer->last_name) }}</a><div>{{ $item->customer->email }}</div>
  </div></div></div>
</div>
@endsection
