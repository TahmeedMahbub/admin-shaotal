@extends('layouts.admin')
@section('title', 'Pages')
@section('section', 'Content')

@section('content')
<x-admin.page-header title="Standalone Store Pages" subtitle="Manage static pages like About Us, Privacy Policy, Terms, and custom landing pages.">
  <a href="{{ route('pages.create') }}" class="btn btn-beige d-flex align-items-center gap-1"><i class="mdi mdi-plus fs-5"></i> Create Page</a>
</x-admin.page-header>

<x-admin.filters :statuses="['published', 'draft']" placeholder="Search by title, slug, content..." />

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Featured Image</th><th>Page Title</th><th>URL Slug</th><th>Updated</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        @forelse ($rows as $p)
          <tr>
            <td><x-admin.thumb :src="$p->featured_image_url" icon="mdi-file-document-outline" :size="48" /></td>
            <td><a href="{{ route('pages.show', $p) }}" class="fw-bold text-dark fs-8">{{ $p->title }}</a></td>
            <td><code>/pages/{{ $p->slug }}</code></td>
            <td>{{ $p->updated_at->format('M d, Y') }}</td>
            <td><x-admin.badge :status="$p->status" /></td>
            <td class="text-end"><x-admin.row-actions :show="route('pages.show', $p)" :edit="route('pages.edit', $p)" :delete="route('pages.destroy', $p)" label="page" /></td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-4">No pages found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>
@endsection
