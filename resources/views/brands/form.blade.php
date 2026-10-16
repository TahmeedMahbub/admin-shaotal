@extends('layouts.admin')
@section('title', $brand->exists ? 'Edit Brand' : 'Create Brand')
@section('section', 'Catalog')

@section('content')
<x-admin.form-shell :title="$brand->exists ? 'Edit Brand' : 'Add New Brand'" subtitle="Product manufacturer or designer brand."
  :action="$brand->exists ? route('brands.update', $brand) : route('brands.store')" :back="route('brands.index')"
  :editing="$brand->exists" main-title="Brand Details" side-title="Brand Logo &amp; Status" submit="Save Brand">
  <x-admin.field name="name" label="Brand Name" :value="$brand->name" required placeholder="e.g. Heritage Craft Works" />
  <x-admin.field name="slug" label="Slug" :value="$brand->slug" placeholder="heritage-craft-works" help="Leave blank to generate from the name." />
  <x-admin.field name="origin_country" label="Origin Country" :value="$brand->origin_country" />
  <x-admin.field name="description" label="Description" type="textarea" :value="$brand->description" placeholder="Brand story and background..." />
  <x-slot:side>
    <x-admin.image-field name="image" label="Brand Logo" :current="$brand->logo_url" />
    <x-admin.field name="status" label="Status" type="select" :value="$brand->status" :options="['active' => 'Active', 'inactive' => 'Inactive']" required />
  </x-slot:side>
</x-admin.form-shell>
@endsection
