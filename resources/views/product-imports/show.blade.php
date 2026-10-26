@extends('layouts.admin')
@section('title', $batch->name ?: 'Import batch #'.$batch->id)
@section('section', 'Catalog')

@section('content')
<x-admin.page-header :title="$batch->name ?: 'Import batch #'.$batch->id" subtitle="Created {{ $batch->created_at?->format('d M Y H:i') }}. Review each item, then save it as a draft or publish it.">
  <a href="{{ route('product-imports.index') }}" class="btn btn-outline-secondary">All imports</a>
  <a href="{{ route('product-imports.create') }}" class="btn btn-beige">New Import</a>
</x-admin.page-header>

@include('product-imports._result')

<div class="d-flex flex-wrap gap-2 mb-3">
  @foreach (['queued' => 'Extracting', 'processing' => 'Extracting', 'needs_review' => 'Needs review', 'ready' => 'Ready', 'draft_saved' => 'Draft saved', 'published' => 'Published', 'duplicate' => 'Duplicate', 'failed' => 'Failed'] as $st => $label)
    @if (($counts[$st] ?? 0) > 0)
      <span class="badge fs-8 {{ \App\Support\Fmt::badge($st) }}">{{ $label }}: {{ $counts[$st] }}</span>
    @endif
  @endforeach
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Image</th><th>Source URL</th><th>Extracted</th><th>Status</th><th>Attempts</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        @forelse ($items as $i)
          <tr data-row-href="{{ route('product-imports.item', $i) }}">
            <td><x-admin.thumb :src="$i->ex_image" /></td>
            <td style="max-width: 320px;"><a href="{{ route('product-imports.item', $i) }}" class="text-dark fs-8 text-break">{{ \Illuminate\Support\Str::limit($i->source_url, 80) }}</a></td>
            <td class="fs-8">
              @if ($i->ex_name)
                <div class="fw-bold text-dark">{{ $i->ex_name }}</div>
                <div class="text-muted">@if ($i->ex_price){{ $i->ex_currency }} {{ $i->ex_price }} &bull; @endif{{ (int) $i->ex_images }} image(s)</div>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
            <td>
              <x-admin.badge :status="$i->status" />
              @if ($i->last_error)<div class="text-danger fs-9 mt-1">{{ $i->last_error }}</div>@endif
              @if ($i->status === 'queued' && $i->next_attempt_at)<div class="text-muted fs-9">Retry after {{ $i->next_attempt_at->format('H:i') }}</div>@endif
            </td>
            <td>{{ $i->attempts }}</td>
            <td class="text-end">
              @if (in_array($i->status, ['queued', 'failed'], true))
                <form method="POST" action="{{ route('product-imports.run', $i) }}" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-outline-beige">{{ $i->status === 'failed' ? 'Retry' : 'Fetch now' }}</button>
                </form>
              @endif
              @if (in_array($i->status, ['needs_review', 'ready', 'draft_saved', 'published', 'duplicate'], true))
                <a href="{{ route('product-imports.review', $i) }}" class="btn btn-sm btn-beige">{{ in_array($i->status, ['needs_review', 'duplicate'], true) ? 'Review' : 'Open' }}</a>
              @endif
              @if ($i->product_id)<a href="{{ route('products.edit', $i->product_id) }}" class="btn btn-sm btn-outline-secondary" title="Open the product">#{{ $i->product_id }}</a>@endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-4">No URLs in this batch.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($items->hasPages())<div class="card-footer">{{ $items->links() }}</div>@endif
</div>
@endsection
