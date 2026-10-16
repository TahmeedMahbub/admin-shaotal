@extends('layouts.admin')
@section('title', $coupon->exists ? 'Edit Coupon' : 'Create Coupon')
@section('section', 'Marketing')

@section('content')
@php
  $fmt = fn ($d) => $d ? $d->format('Y-m-d\TH:i') : null;
  $selProducts = old('product_ids', $coupon->products->pluck('id')->all());
  $selCategories = old('category_ids', $coupon->categories->pluck('id')->all());
@endphp
<x-admin.form-shell :title="$coupon->exists ? 'Edit Coupon Code' : 'Create Coupon Code'" subtitle="Discount voucher codes, usage caps, and minimum cart rules."
  :action="$coupon->exists ? route('coupons.update', $coupon) : route('coupons.store')" :back="route('coupons.index')"
  :editing="$coupon->exists" :files="false" main-title="Coupon Rules" side-title="Status &amp; Validity" submit="Save Coupon">
  <div class="row">
    <x-admin.field col="col-md-6 mb-3" name="code" label="Coupon Code" :value="$coupon->code" required placeholder="e.g. SPRING20" />
    <x-admin.field col="col-md-6 mb-3" name="discount_type" label="Discount Type" type="select" :value="$coupon->discount_type" :options="['percentage' => 'Percentage (%)', 'fixed_amount' => 'Fixed Amount (A$)', 'free_shipping' => 'Free Shipping']" required />
    <x-admin.field col="col-md-6 mb-3" name="discount_value" label="Discount Value" type="number" step="0.01" min="0" :value="$coupon->discount_value" help="Percent (0–100) or A$ amount. Ignored for free shipping." />
    <x-admin.field col="col-md-6 mb-3" name="minimum_order_amount" label="Minimum Order Value (A$)" type="number" step="0.01" min="0" :value="$coupon->minimum_order_amount" />
    <x-admin.field col="col-md-6 mb-3" name="maximum_discount_amount" label="Maximum Discount Amount (A$)" type="number" step="0.01" min="0" :value="$coupon->maximum_discount_amount" />
    <x-admin.field col="col-md-3 mb-3" name="usage_limit" label="Total Usage Limit" type="number" min="1" :value="$coupon->usage_limit" />
    <x-admin.field col="col-md-3 mb-3" name="usage_limit_per_customer" label="Per-Customer Limit" type="number" min="1" :value="$coupon->usage_limit_per_customer" />
  </div>
  <x-admin.field name="description" label="Coupon Description" type="textarea" rows="2" :value="$coupon->description" />
  <div class="row">
    <div class="col-md-6 mb-3">
      <label class="form-label fw-bold fs-8">Limit to categories (none = all)</label>
      <select name="category_ids[]" multiple class="form-select" size="6">@foreach ($categories as $id => $n)<option value="{{ $id }}" @selected(in_array($id, $selCategories))>{{ $n }}</option>@endforeach</select>
    </div>
    <div class="col-md-6 mb-3">
      <label class="form-label fw-bold fs-8">Limit to products (none = all)</label>
      <select name="product_ids[]" multiple class="form-select" size="6">@foreach ($products as $id => $n)<option value="{{ $id }}" @selected(in_array($id, $selProducts))>{{ $n }}</option>@endforeach</select>
    </div>
  </div>
  <x-slot:side>
    <x-admin.field name="status" label="Status" type="select" :value="$coupon->status" :options="['active' => 'Active', 'inactive' => 'Disabled', 'expired' => 'Expired']" required />
    <x-admin.field name="starts_at" label="Start Date" type="datetime-local" :value="$fmt($coupon->starts_at)" />
    <x-admin.field name="expires_at" label="End Date" type="datetime-local" :value="$fmt($coupon->expires_at)" />
  </x-slot:side>
</x-admin.form-shell>
@endsection
