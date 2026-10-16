@extends('layouts.admin')
@section('title', 'Add Wishlist Item')
@section('section', 'Engagement')

@section('content')
<x-admin.form-shell title="Add Wishlist Item" subtitle="Save a product to a customer's wishlist." :action="route('wishlists.store')" :back="route('wishlists.index')"
  :files="false" main-title="Wishlist Item" side-title="Save" submit="Save Item">
  <x-admin.field name="customer_id" label="Customer" type="select" :options="$customers" blank="Select Customer" required />
  <x-admin.field name="product_id" label="Product" type="select" :options="$products" blank="Select Product" required />
</x-admin.form-shell>
@endsection
