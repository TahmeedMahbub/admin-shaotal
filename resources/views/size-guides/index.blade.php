@extends('layouts.admin')
@section('title', 'Size Guides')
@section('section', 'Catalog')

@section('content')
<x-admin.page-header title="Size Guides" subtitle="Manage dynamic measurement charts, attributes, and size standards for products.">
  <a href="{{ route('size-guides.create') }}" class="btn btn-beige d-flex align-items-center gap-1"><i class="mdi mdi-plus fs-5"></i> Create Size Guide</a>
</x-admin.page-header>

<x-admin.filters :statuses="['active', 'inactive']" placeholder="Search by title or key" />

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Guide Title &amp; Key</th><th>Measurement Attributes</th><th>Available Sizes</th><th>Assigned</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        @forelse ($rows as $g)
          <tr>
            <td><a href="{{ route('size-guides.show', $g) }}" class="fw-bold text-dark fs-8">{{ $g->title }}</a><div><code>{{ $g->key }}</code></div></td>
            <td>@foreach ($g->sizeAttributes as $a)<span class="badge bg-label-secondary me-1">{{ $a->attribute_name }} ({{ $a->unit }})</span>@endforeach</td>
            <td>{{ $g->size_count }} sizes</td>
            <td><span class="fw-bold text-dark">{{ $g->products_count }} Products</span></td>
            <td><x-admin.badge :status="$g->status" /></td>
            <td class="text-end"><x-admin.row-actions :show="route('size-guides.show', $g)" :edit="route('size-guides.edit', $g)" :delete="route('size-guides.destroy', $g)" label="size guide" /></td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-4">No size guides found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>
@endsection
