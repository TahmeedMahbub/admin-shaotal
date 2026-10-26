@extends('layouts.admin')
@section('title', 'Import item #'.$item->id)
@section('section', 'Catalog')

@section('content')
@php $d = $item->raw_data ?? []; @endphp
<x-admin.page-header :title="$d['name'] ?? 'Import item #'.$item->id" subtitle="Extracted data from the source page. Read-only view of what was found. Use Review &amp; edit to change it.">
  <a href="{{ route('product-imports.show', $item->batch_id) }}" class="btn btn-outline-secondary">Back to batch</a>
  @if (in_array($item->status, ['needs_review', 'ready', 'draft_saved', 'published', 'duplicate'], true))
    <a href="{{ route('product-imports.review', $item) }}" class="btn btn-beige">Review &amp; edit</a>
  @endif
  @if (in_array($item->status, ['queued', 'failed'], true))
    <form method="POST" action="{{ route('product-imports.run', $item) }}">@csrf
      <button type="submit" class="btn btn-beige">{{ $item->status === 'failed' ? 'Retry' : 'Fetch now' }}</button>
    </form>
  @endif
</x-admin.page-header>

<div class="row g-4">
  <div class="col-12 col-lg-8">
    <div class="card mb-4">
      <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0 fs-6">Product data</h5><x-admin.badge :status="$item->status" />
      </div>
      <div class="card-body pt-3 fs-8">
        <dl class="row mb-0">
          <dt class="col-sm-3">Source</dt><dd class="col-sm-9 text-break"><a href="{{ $item->source_url }}" target="_blank" rel="noopener noreferrer">{{ $item->source_url }}</a></dd>
          @if ($item->last_error)<dt class="col-sm-3 text-danger">Error</dt><dd class="col-sm-9 text-danger">{{ $item->last_error }} (attempt {{ $item->attempts }})</dd>@endif
          @if ($d)
            <dt class="col-sm-3">Name</dt><dd class="col-sm-9">{{ $d['name'] ?? '—' }}</dd>
            <dt class="col-sm-3">Brand</dt><dd class="col-sm-9">{{ $d['brand'] ?? '—' }}</dd>
            <dt class="col-sm-3">SKU</dt><dd class="col-sm-9">{{ $d['sku'] ?? '—' }}</dd>
            <dt class="col-sm-3">Price</dt><dd class="col-sm-9">{{ isset($d['price']) ? ($d['currency'] ?? '').' '.$d['price'] : '—' }}@if (! empty($d['list_price'])) <span class="text-muted">(list {{ $d['list_price'] }})</span>@endif</dd>
            <dt class="col-sm-3">Availability</dt><dd class="col-sm-9">{{ $d['availability'] ?? '—' }}</dd>
            <dt class="col-sm-3">Material</dt><dd class="col-sm-9">{{ $d['material'] ?? '—' }}</dd>
            <dt class="col-sm-3">Category hint</dt><dd class="col-sm-9">{{ $d['category_hint'] ?? '—' }}</dd>
            <dt class="col-sm-3">Colours</dt><dd class="col-sm-9">{{ $d['colors'] ? implode(', ', $d['colors']) : '—' }}</dd>
            <dt class="col-sm-3">Sizes</dt><dd class="col-sm-9">{{ $d['sizes'] ? implode(', ', $d['sizes']) : '—' }}</dd>
            <dt class="col-sm-3">Description</dt><dd class="col-sm-9" style="white-space: pre-line;">{{ $d['description'] ?? '—' }}</dd>
          @endif
        </dl>
      </div>
    </div>

    @if (! empty($d['variants']))
      <div class="card mb-4">
        <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Variants found ({{ count($d['variants']) }})</h5></div>
        <div class="table-responsive">
          <table class="table table-sm mb-0 fs-8">
            <thead><tr><th>Name</th><th>SKU</th><th>Colour</th><th>Size</th><th>Price</th><th>Availability</th></tr></thead>
            <tbody>@foreach ($d['variants'] as $v)<tr><td>{{ $v['name'] ?? '—' }}</td><td>{{ $v['sku'] ?? '—' }}</td><td>{{ $v['color'] ?? '—' }}</td><td>{{ $v['size'] ?? '—' }}</td><td>{{ $v['price'] ?? '—' }}</td><td>{{ $v['availability'] ?? '—' }}</td></tr>@endforeach</tbody>
          </table>
        </div>
      </div>
    @endif

    @if (! empty($d['images']))
      <div class="card mb-4">
        <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Source images ({{ count($d['images']) }})</h5></div>
        <div class="card-body d-flex flex-wrap gap-2">
          @foreach ($d['images'] as $img)
            <a href="{{ $img['url'] }}" target="_blank" rel="noopener noreferrer" title="{{ $img['via'] }}: {{ $img['url'] }}"><x-admin.thumb :src="$img['url']" :size="96" /></a>
          @endforeach
        </div>
      </div>
    @endif
  </div>

  <div class="col-12 col-lg-4">
    <div class="card mb-4">
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Extraction</h5></div>
      <div class="card-body pt-3 fs-8">
        <div class="mb-2"><span class="text-muted">Found via:</span> {{ ! empty($d['extracted_via']) ? implode(', ', $d['extracted_via']) : '—' }}</div>
        <div class="mb-2"><span class="text-muted">Final URL:</span> <span class="text-break">{{ $d['final_url'] ?? '—' }}</span></div>
        <div class="mb-2"><span class="text-muted">Normalized:</span> <span class="text-break">{{ $item->normalized_url }}</span></div>
        @if (! empty($d['warnings']))
          <div class="fw-bold mt-3 mb-1">Warnings</div>
          <ul class="ps-3 mb-0 text-warning-emphasis">@foreach ($d['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
