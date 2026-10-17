@extends('layouts.admin')
@section('title', 'Site Content')
@section('section', 'Content')

@section('content')
<x-admin.page-header title="Predefined Site Content Locations" subtitle="Manage global site text sections, footer info blocks, and announcements." />

<x-admin.filters :statuses="['enabled', 'disabled']" placeholder="Search by slug, title, content..." />

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Location Slug</th><th>Title</th><th>Content Preview</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        @forelse ($rows as $c)
          <tr data-row-href="{{ route('site-contents.edit', $c) }}">
            <td><a href="{{ route('site-contents.show', $c) }}"><code>{{ $c->slug }}</code></a></td>
            <td class="fw-bold fs-8">{{ $c->title }}</td>
            <td class="text-muted">{{ \Illuminate\Support\Str::limit(strip_tags((string) $c->content), 70) ?: '—' }}</td>
            <td><x-admin.badge :status="$c->status" /></td>
            <td class="text-end"><x-admin.row-actions :show="route('site-contents.show', $c)" :edit="route('site-contents.edit', $c)" /></td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-center text-muted py-4">No content locations found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>
@endsection
