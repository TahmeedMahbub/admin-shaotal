@extends('layouts.admin')
@section('title', 'Categories')
@section('section', 'Catalog')

@section('content')
<x-admin.page-header title="Categories" subtitle="Manage main product categories and organization.">
  <a href="{{ route('categories.create') }}" class="btn btn-beige d-flex align-items-center gap-1"><i class="mdi mdi-plus fs-5"></i> Add Category</a>
</x-admin.page-header>

<x-admin.filters :statuses="['active', 'inactive']" placeholder="Search name or slug" />

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Category</th><th>Slug</th><th>Subcategories</th><th>Products</th><th>Sort Order</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        @forelse ($rows as $c)
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <x-admin.thumb :src="$c->image_url" icon="mdi-shape-outline" />
                <a href="{{ route('categories.edit', $c) }}" class="fw-bold text-dark fs-8">{{ $c->name }}</a>
              </div>
            </td>
            <td><code>{{ $c->slug }}</code></td>
            <td><span class="badge bg-label-secondary">{{ $c->subcategories_count }} Subcategories</span></td>
            <td><span class="fw-bold text-dark">{{ $c->products_count }}</span></td>
            <td>{{ $c->sort_order }}</td>
            <td><x-admin.badge :status="$c->status" /></td>
            <td class="text-end"><x-admin.row-actions :edit="route('categories.edit', $c)" :delete="route('categories.destroy', $c)" label="category" /></td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No categories found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>
@endsection
