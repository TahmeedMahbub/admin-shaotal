@extends('layouts.admin')
@section('title', $item->exists ? 'Edit Navigation Item' : 'Add Navigation Item')
@section('section', 'Navigation')

@section('content')
<x-admin.form-shell :title="$item->exists ? 'Edit Navigation Item' : 'Add Navigation Item'" subtitle="Header menu link, dropdown item, or footer link."
  :action="$item->exists ? route('navigation.update', $item) : route('navigation.store')" :back="route('navigation.index')"
  :editing="$item->exists" :files="false" main-title="Link" side-title="Placement" submit="Save Item">
  <x-admin.field name="title" label="Navigation Item Title" :value="$item->title" required placeholder="e.g. Jute Bags" />
  <x-admin.field name="url" label="Link / Destination URL" :value="$item->url" required placeholder="e.g. /products?category=jute or https://..." />
  <x-slot:side>
    <x-admin.field name="location" label="Location" type="select" :value="$item->location" :options="$locations" required />
    <x-admin.field name="parent_id" label="Parent (dropdown under)" type="select" :value="$item->parent_id" :options="$parents" blank="Root level item" />
    <x-admin.field name="sort_order" label="Sort Order Index" type="number" min="0" :value="$item->sort_order" required />
    <x-admin.field name="status" label="Status" type="select" :value="$item->status" :options="['active' => 'Active', 'inactive' => 'Inactive']" required />
  </x-slot:side>
</x-admin.form-shell>
@endsection
