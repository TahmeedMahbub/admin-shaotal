@extends('layouts.admin')
@section('title', 'Subcategories')
@section('section', 'Catalog')

@section('content')
<x-admin.page-header title="Subcategories" subtitle="Manage second-level product classification linked to parent categories.">
  <a href="{{ route('subcategories.create') }}" class="btn btn-beige d-flex align-items-center gap-1"><i class="mdi mdi-plus fs-5"></i> Add Subcategory</a>
</x-admin.page-header>

<x-admin.filters :statuses="['active', 'inactive']" placeholder="Search name or slug">
  <select name="category_id" class="form-select" style="max-width: 220px;" onchange="this.form.submit()">
    <option value="">All parent categories</option>
    @foreach ($categories as $id => $name)<option value="{{ $id }}" @selected((string) request('category_id') === (string) $id)>{{ $name }}</option>@endforeach
  </select>
</x-admin.filters>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Subcategory</th><th>Parent Category</th><th>Slug</th><th>Products</th><th>Sort Order</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        @forelse ($rows as $s)
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <x-admin.thumb :src="$s->image_url" icon="mdi-shape-outline" />
                <a href="{{ route('subcategories.edit', $s) }}" class="fw-bold text-dark fs-8">{{ $s->name }}</a>
              </div>
            </td>
            <td><span class="badge bg-label-secondary">{{ $s->category->name ?? '—' }}</span></td>
            <td><code>{{ $s->slug }}</code></td>
            <td><span class="fw-bold text-dark">{{ $s->products_count }}</span></td>
            <td>{{ $s->sort_order }}</td>
            <td><x-admin.badge :status="$s->status" /></td>
            <td class="text-end"><x-admin.row-actions :edit="route('subcategories.edit', $s)" :delete="route('subcategories.destroy', $s)" label="subcategory" /></td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No subcategories found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>
@endsection
