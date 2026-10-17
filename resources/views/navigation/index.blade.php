@extends('layouts.admin')
@section('title', 'Navigation')
@section('section', 'Content')

@section('content')
<x-admin.page-header title="Navigation Menu Management" subtitle="Configure store header menus, dropdown links, sub-items, and footer links.">
  <a href="{{ route('navigation.create') }}" class="btn btn-beige d-flex align-items-center gap-1"><i class="mdi mdi-plus fs-5"></i> Add Item</a>
</x-admin.page-header>

<x-admin.filters :statuses="['active', 'inactive']" placeholder="Search title or URL...">
  <select name="location" class="form-select" style="max-width: 230px;" onchange="this.form.submit()">
    <option value="">All Locations</option>
    @foreach ($locations as $k => $l)<option value="{{ $k }}" @selected(request('location') === $k)>{{ $l }}</option>@endforeach
  </select>
</x-admin.filters>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Order</th><th>Navigation Title</th><th>Link / Destination</th><th>Location</th><th>Parent Item</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        @forelse ($rows as $n)
          <tr data-row-href="{{ route('navigation.edit', $n) }}">
            <td>{{ $n->sort_order }}</td>
            <td><a href="{{ route('navigation.edit', $n) }}" class="fw-bold text-dark fs-8">{{ $n->parent_id ? '↳ ' : '' }}{{ $n->title }}</a></td>
            <td><code class="fs-9">{{ \Illuminate\Support\Str::limit($n->url, 48) }}</code></td>
            <td>{{ $locations[$n->location] ?? $n->location }}</td>
            <td>{{ $n->parent->title ?? '—' }}</td>
            <td><x-admin.badge :status="$n->status" /></td>
            <td class="text-end"><x-admin.row-actions :edit="route('navigation.edit', $n)" :delete="route('navigation.destroy', $n)" label="navigation item" /></td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No navigation items found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>
@endsection
