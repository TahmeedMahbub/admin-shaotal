@extends('layouts.admin')
@section('title', $banner->title ?: 'Banner')
@section('section', 'Banners')

@section('content')
<x-admin.page-header :title="$banner->title ?: 'Banner #'.$banner->id" :subtitle="$banner->subtitle">
  <a href="{{ route('banners.index') }}" class="btn btn-outline-secondary">Back</a>
  <a href="{{ route('banners.edit', $banner) }}" class="btn btn-beige">Edit Banner</a>
</x-admin.page-header>
<div class="row g-4">
  <div class="col-12 col-lg-8">
    <div class="card mb-4"><div class="card-body"><img src="{{ $banner->image_url }}" alt="" class="img-fluid rounded w-100"></div></div>
    @if ($banner->mobile_image_url)<div class="card"><div class="card-header py-3 fs-6">Mobile image</div><div class="card-body"><img src="{{ $banner->mobile_image_url }}" alt="" class="img-fluid rounded" style="max-height: 320px;"></div></div>@endif
  </div>
  <div class="col-12 col-lg-4"><div class="card"><div class="card-body fs-8">
    <div class="mb-2"><x-admin.badge :status="$banner->status" /></div>
    <div class="mb-2"><div class="text-muted">Button</div>{{ $banner->button_text ?: '—' }}</div>
    <div class="mb-2"><div class="text-muted">Destination</div>{{ $banner->button_url ?: '—' }}</div>
    <div class="mb-2"><div class="text-muted">Display order</div>{{ $banner->sort_order }}</div>
    <div class="mb-3"><div class="text-muted">Schedule</div>{{ $banner->starts_at?->format('M d, Y h:i A') ?? 'Any time' }} → {{ $banner->ends_at?->format('M d, Y h:i A') ?? 'No end' }}</div>
    <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#deleteModal" data-delete-url="{{ route('banners.destroy', $banner) }}" data-delete-label="banner">Delete Banner</button>
  </div></div></div>
</div>
@endsection
