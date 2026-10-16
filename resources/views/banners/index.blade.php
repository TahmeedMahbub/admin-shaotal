@extends('layouts.admin')
@section('title', 'Banners')
@section('section', 'Content')

@section('content')
<x-admin.page-header title="Promotional Store Banners" subtitle="Manage homepage hero slides and promo banners.">
  <a href="{{ route('banners.create') }}" class="btn btn-beige d-flex align-items-center gap-1"><i class="mdi mdi-plus fs-5"></i> Create Banner</a>
</x-admin.page-header>

<x-admin.filters :statuses="['active', 'inactive']" placeholder="Search banner title..." />

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Banner Preview</th><th>Banner Title</th><th>Linked Destination</th><th>Order</th><th>Schedule</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        @forelse ($rows as $b)
          <tr>
            <td><img src="{{ $b->image_url }}" alt="" loading="lazy" class="rounded border" style="width: 120px; height: 60px; object-fit: cover;"></td>
            <td><a href="{{ route('banners.show', $b) }}" class="fw-bold text-dark fs-8">{{ $b->title ?: '(untitled)' }}</a><div><small class="text-muted">{{ $b->subtitle }}</small></div></td>
            <td>@if ($b->button_url)<code class="fs-9">{{ \Illuminate\Support\Str::limit($b->button_url, 38) }}</code>@else — @endif</td>
            <td>{{ $b->sort_order }}</td>
            <td><small>{{ $b->starts_at?->format('M d, Y') ?? 'Any time' }} → {{ $b->ends_at?->format('M d, Y') ?? 'No end' }}</small></td>
            <td><x-admin.badge :status="$b->status" /></td>
            <td class="text-end"><x-admin.row-actions :show="route('banners.show', $b)" :edit="route('banners.edit', $b)" :delete="route('banners.destroy', $b)" label="banner" /></td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No banners found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>
@endsection
