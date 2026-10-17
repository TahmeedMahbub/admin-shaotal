@extends('layouts.admin')
@section('title', $banner->exists ? 'Edit Banner' : 'Create Banner')
@section('section', 'Content')

@section('content')
@php $fmt = fn ($d) => $d ? $d->format('Y-m-d\TH:i') : null; @endphp
<x-admin.form-shell :title="$banner->exists ? 'Edit Store Banner' : 'Create Store Banner'" subtitle="Banner image, destination link, and scheduling."
  :action="$banner->exists ? route('banners.update', $banner) : route('banners.store')" :back="route('banners.index')"
  :editing="$banner->exists" main-title="Banner Details" side-title="Images &amp; Status" submit="Save Banner">
  <x-admin.field name="title" label="Banner Title" :value="$banner->title" />
  <x-admin.field name="subtitle" label="Short Description (Optional)" type="textarea" rows="2" :value="$banner->subtitle" />
  <div class="row">
    <x-admin.field col="col-md-5 mb-3" name="button_text" label="Call to Action Button Text" :value="$banner->button_text" />
    <x-admin.field col="col-md-7 mb-3" name="button_url" label="Destination URL" :value="$banner->button_url" placeholder="/products or https://..." />
    <x-admin.field col="col-md-6 mb-3" name="starts_at" label="Start Date" type="datetime-local" :value="$fmt($banner->starts_at)" />
    <x-admin.field col="col-md-6 mb-3" name="ends_at" label="End Date" type="datetime-local" :value="$fmt($banner->ends_at)" />
  </div>
  <x-slot:side>
    <x-admin.image-field name="image" label="Banner Image *" :current="$banner->image_url" />
    <x-admin.field name="image_url" label="Or existing image URL (https)" type="url" :value="$banner->image_url" placeholder="https://res.cloudinary.com/..." />
    <x-admin.image-field name="mobile_image" label="Mobile Image (optional)" :current="$banner->mobile_image_url" />
    <x-admin.field name="status" label="Status" type="select" :value="$banner->status" :options="['active' => 'Active', 'inactive' => 'Inactive']" required />
    <div class="fs-9 text-muted">Display order is set by dragging banners on the <a href="{{ route('banners.index') }}">banner list</a>.</div>
  </x-slot:side>
</x-admin.form-shell>
@endsection
