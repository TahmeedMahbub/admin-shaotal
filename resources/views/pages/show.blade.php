@extends('layouts.admin')
@section('title', $page->title)
@section('section', 'Pages')

@section('content')
<x-admin.page-header :title="$page->title" :subtitle="'/pages/'.$page->slug">
  <a href="{{ route('pages.index') }}" class="btn btn-outline-secondary">Back</a>
  <a href="{{ route('pages.edit', $page) }}" class="btn btn-beige">Edit Page</a>
  <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-delete-url="{{ route('pages.destroy', $page) }}" data-delete-label="page">Delete</button>
</x-admin.page-header>
<div class="row g-4">
  <div class="col-12 col-lg-8"><div class="card"><div class="card-header py-3 fs-6">Content (source HTML, shown escaped)</div>
    <div class="card-body"><pre class="mb-0 fs-8" style="white-space: pre-wrap;">{{ $page->content ?: '—' }}</pre></div></div></div>
  <div class="col-12 col-lg-4"><div class="card"><div class="card-body fs-8">
    <x-admin.badge :status="$page->status" />
    @if ($page->featured_image_url)<img src="{{ $page->featured_image_url }}" alt="" class="img-fluid rounded my-3">@endif
    <div class="text-muted">SEO title</div><div class="mb-2">{{ $page->seo_title ?: '—' }}</div>
    <div class="text-muted">SEO description</div><div>{{ $page->seo_description ?: '—' }}</div>
  </div></div></div>
</div>
@endsection
