@extends('layouts.admin')
@section('title', 'Brands')
@section('section', 'Catalog')

@section('content')
<x-admin.page-header title="Brands" subtitle="Manage product manufacturers and designer brands.">
  <a href="{{ route('brands.create') }}" class="btn btn-beige d-flex align-items-center gap-1"><i class="mdi mdi-plus fs-5"></i> Add Brand</a>
</x-admin.page-header>

<x-admin.filters :statuses="['active', 'inactive']" placeholder="Search name or slug" />

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Brand Logo</th><th>Brand Name</th><th>Slug</th><th>Products</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        @forelse ($rows as $b)
          <tr data-row-href="{{ route('brands.edit', $b) }}">
            <td><x-admin.thumb :src="$b->logo_url" icon="mdi-tag-outline" /></td>
            <td>
              <a href="{{ route('brands.edit', $b) }}" class="fw-bold text-dark fs-8">{{ $b->name }}</a>
              @if ($b->origin_country)<div class="text-muted fs-8">{{ $b->origin_country }}</div>@endif
            </td>
            <td><code>{{ $b->slug }}</code></td>
            <td><span class="fw-bold text-dark">{{ $b->products_count }} Products</span></td>
            <td><x-admin.badge :status="$b->status" /></td>
            <td class="text-end"><x-admin.row-actions :edit="route('brands.edit', $b)" :delete="route('brands.destroy', $b)" label="brand" /></td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-4">No brands found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>
@endsection
