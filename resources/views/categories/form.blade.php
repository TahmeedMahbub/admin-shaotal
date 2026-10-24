@extends('layouts.admin')
@section('title', $category->exists ? 'Edit Category' : 'Create Category')
@section('section', 'Catalog')

@section('content')
<x-admin.form-shell :title="$category->exists ? 'Edit Category' : 'Create New Category'" subtitle="A main top-level product category."
  :action="$category->exists ? route('categories.update', $category) : route('categories.store')" :back="route('categories.index')"
  :editing="$category->exists" main-title="Category Details" side-title="Category Image &amp; Settings" submit="Save Category">
  <x-admin.field name="name" label="Category Name" :value="$category->name" required placeholder="e.g. Lighting & Fixtures" />
  <x-admin.field name="slug" label="Slug" :value="$category->slug" placeholder="lighting-fixtures" help="Auto-generated from the name; edit it if you want a custom one." />
  <x-admin.field name="description" label="Description" type="textarea" :value="$category->description" placeholder="Category description for catalog overview..." />
  <x-slot:side>
    <x-admin.image-field name="image" label="Category Image" :current="$category->image_url" />
    <x-admin.field name="sort_order" label="Sort Order" type="number" :value="$category->sort_order" required min="0" />
    <x-admin.field name="status" label="Status" type="select" :value="$category->status" :options="['active' => 'Active', 'inactive' => 'Inactive']" required />
  </x-slot:side>
</x-admin.form-shell>
@endsection
