@extends('layouts.admin')
@section('title', $guide->title)
@section('section', 'Size Guides')

@section('content')
<x-admin.page-header :title="$guide->title" :subtitle="'Key: '.$guide->key">
  <a href="{{ route('size-guides.index') }}" class="btn btn-outline-secondary">Back</a>
  <a href="{{ route('size-guides.edit', $guide) }}" class="btn btn-beige">Edit Guide</a>
</x-admin.page-header>

<div class="row g-4">
  <div class="col-12 col-lg-8">
    <div class="card mb-4">
      <div class="card-header border-bottom py-3 d-flex justify-content-between"><h5 class="card-title mb-0 fs-6">Measurement Chart</h5><x-admin.badge :status="$guide->status" /></div>
      <div class="table-responsive">
        <table class="table table-bordered align-middle mb-0 text-center">
          <thead><tr><th>Size</th>@foreach ($guide->sizeAttributes as $a)<th>{{ $a->attribute_name }} ({{ $a->unit }})</th>@endforeach</tr></thead>
          <tbody>
            @foreach ($guide->sizeValues->groupBy('size_label') as $label => $vals)
              <tr><td class="fw-bold">{{ $label }}</td>@foreach ($guide->sizeAttributes as $a)<td>{{ rtrim(rtrim((string) ($vals->firstWhere('attribute_id', $a->id)->measurement_value ?? ''), '0'), '.') }}</td>@endforeach</tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    <div class="card">
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Assigned Products <span class="badge bg-label-secondary">{{ $guide->products()->count() }}</span></h5></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Product</th><th>SKU</th><th>Price</th><th>Stock</th><th class="text-end">Action</th></tr></thead>
          <tbody>
            @forelse ($products as $p)
              <tr>
                <td class="fw-bold text-dark fs-8">{{ $p->name }}</td><td>{{ $p->sku ?: '—' }}</td>
                <td>{{ \App\Support\Fmt::money($p->selling_price ?? $p->price) }}</td>
                <td>{{ $p->has_variants ? 'Variants' : $p->stock_qty }}</td>
                <td class="text-end"><x-admin.row-actions :edit="route('products.edit', $p->id)" /></td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center text-muted py-4">No products use this guide.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-4">
    <div class="card mb-4">
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Shopper Notes <span class="badge bg-label-success ms-1">Storefront Displayed</span></h5></div>
      <div class="card-body pt-3 fs-8">{!! nl2br(e($guide->customer_details ?: 'No shopper notes.')) !!}</div>
    </div>
    <div class="card">
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Private Notes <span class="badge bg-label-secondary ms-1">Internal Only</span></h5></div>
      <div class="card-body pt-3 fs-8">{!! nl2br(e($guide->admin_details ?: 'No private notes recorded for this guide.')) !!}</div>
    </div>
  </div>
</div>
@endsection
