@extends('layouts.admin')
@section('title', $offer->exists ? 'Edit Offer' : 'Create Offer')
@section('section', 'Marketing')

@section('content')
@php
  $fmt = fn ($d) => $d ? $d->format('Y-m-d\TH:i') : null;
  $selProducts = old('product_ids', $offer->products->pluck('id')->all());
  $selCategories = old('category_ids', $offer->categories->pluck('id')->all());
@endphp
<x-admin.form-shell :title="$offer->exists ? 'Edit Promotional Offer' : 'Create Promotional Offer'" subtitle="Discount rates, validity dates, and applicable products."
  :action="$offer->exists ? route('offers.update', $offer) : route('offers.store')" :back="route('offers.index')"
  :editing="$offer->exists" :files="false" main-title="Offer Details" side-title="Status &amp; Validity" submit="Save Offer">
  <x-admin.field name="name" label="Offer Name" :value="$offer->name" required placeholder="e.g. Spring Clearance Sale" />
  <div class="row">
    <x-admin.field col="col-md-6 mb-3" name="discount_type" label="Discount Type" type="select" :value="$offer->discount_type" :options="['percentage' => 'Percentage (%)', 'fixed_amount' => 'Fixed Amount (A$)']" required />
    <x-admin.field col="col-md-6 mb-3" name="discount_value" label="Discount Value" type="number" step="0.01" min="0" :value="$offer->discount_value" required placeholder="e.g. 20 for 20% or 50 for A$50" />
  </div>
  <x-admin.field name="description" label="Offer Description" type="textarea" rows="2" :value="$offer->description" />
  <div class="row">
    <div class="col-md-6 mb-3"><label class="form-label fw-bold fs-8">Applicable Categories (none = all)</label>
      <select name="category_ids[]" multiple class="form-select" size="6">@foreach ($categories as $id => $n)<option value="{{ $id }}" @selected(in_array($id, $selCategories))>{{ $n }}</option>@endforeach</select></div>
    <div class="col-md-6 mb-3"><label class="form-label fw-bold fs-8">Applicable Products (none = all)</label>
      <select name="product_ids[]" multiple class="form-select" size="6">@foreach ($products as $id => $n)<option value="{{ $id }}" @selected(in_array($id, $selProducts))>{{ $n }}</option>@endforeach</select></div>
  </div>
  <x-slot:side>
    <x-admin.field name="status" label="Status" type="select" :value="$offer->status" :options="['active' => 'Active', 'inactive' => 'Inactive']" required />
    <x-admin.field name="starts_at" label="Start Date" type="datetime-local" :value="$fmt($offer->starts_at)" />
    <x-admin.field name="ends_at" label="End Date" type="datetime-local" :value="$fmt($offer->ends_at)" />
  </x-slot:side>
</x-admin.form-shell>
@endsection
