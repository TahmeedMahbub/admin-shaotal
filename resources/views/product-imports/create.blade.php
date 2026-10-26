@extends('layouts.admin')
@section('title', 'Import Products')
@section('section', 'Catalog')

@section('content')
<x-admin.page-header title="Import Products from URLs" subtitle="Paste product page links. Each unique page is fetched and its product data extracted for review; nothing is created in the catalogue yet.">
  <a href="{{ route('product-imports.index') }}" class="btn btn-outline-secondary">Back to imports</a>
</x-admin.page-header>

@include('product-imports._result')

<form method="POST" action="{{ route('product-imports.store') }}" novalidate>
  @csrf
  <div class="row g-4">
    <div class="col-12 col-lg-8">
      <div class="card mb-4">
        <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Product URLs</h5></div>
        <div class="card-body pt-3">
          <div class="mb-3">
            <label class="form-label fw-bold fs-8" for="f-urls">URLs <span class="text-danger">*</span></label>
            <textarea id="f-urls" name="urls" rows="14" class="form-control font-monospace fs-8{{ $errors->has('urls') ? ' is-invalid' : '' }}" placeholder="https://example.com/products/linen-shirt&#10;https://example.com/products/cotton-trouser" required>{{ old('urls') }}</textarea>
            @error('urls')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <div class="form-text fs-9">One URL per line, up to {{ $max }} at a time.</div>
          </div>
          <x-admin.field name="name" label="Batch Name" :value="old('name')" placeholder="e.g. Autumn lounge wear" help="Optional, only to find this batch later." />
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-4">
      <div class="card mb-4">
        <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">How it works</h5></div>
        <div class="card-body pt-3 fs-8 text-muted">
          <ul class="ps-3 mb-3">
            <li>Links to the same page are detected even when spelled differently (<code>www</code>, tracking parameters, trailing slash, <code>#fragment</code>).</li>
            <li>A URL that was imported before is skipped, in any batch.</li>
            <li>Fetching runs with <code>php artisan product-imports:process</code>, or use <strong>Fetch now</strong> on a batch page.</li>
          </ul>
          <button type="submit" class="btn btn-beige w-100">Queue URLs</button>
        </div>
      </div>
    </div>
  </div>
</form>
@endsection
