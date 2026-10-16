@extends('layouts.admin')
@section('title', $subcategory->exists ? 'Edit Subcategory' : 'Create Subcategory')
@section('section', 'Catalog')

@section('content')
<x-admin.form-shell :title="$subcategory->exists ? 'Edit Subcategory' : 'Create New Subcategory'" subtitle="A subcategory assigned to a parent category."
  :action="$subcategory->exists ? route('subcategories.update', $subcategory) : route('subcategories.store')" :back="route('subcategories.index')"
  :editing="$subcategory->exists" main-title="Subcategory Details" side-title="Settings" submit="Save Subcategory">
  <x-admin.field name="category_id" label="Parent Category" type="select" :value="$subcategory->category_id" :options="$categories" blank="Select Parent Category" required />
  <x-admin.field name="name" label="Subcategory Name" :value="$subcategory->name" required placeholder="e.g. Dining Tables" />
  <x-admin.field name="slug" label="Slug" :value="$subcategory->slug" placeholder="dining-tables" help="Leave blank to generate from the name." />
  <x-admin.field name="description" label="Description" type="textarea" :value="$subcategory->description" placeholder="Subcategory details..." />
  <x-slot:side>
    <x-admin.image-field name="image" label="Subcategory Image" :current="$subcategory->image_url" />
    <x-admin.field name="sort_order" label="Sort Order" type="number" :value="$subcategory->sort_order" required min="0" />
    <x-admin.field name="status" label="Status" type="select" :value="$subcategory->status" :options="['active' => 'Active', 'inactive' => 'Inactive']" required />
  </x-slot:side>
</x-admin.form-shell>
@endsection
