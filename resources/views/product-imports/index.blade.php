@extends('layouts.admin')
@section('title', 'Import Products')
@section('section', 'Catalog')

@section('content')
<x-admin.page-header title="Product Imports" subtitle="Batches of external product URLs and their extraction status.">
  <a href="{{ route('product-imports.create') }}" class="btn btn-beige d-flex align-items-center gap-1"><i class="mdi mdi-plus fs-5"></i> New Import</a>
</x-admin.page-header>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Batch</th><th>Created</th><th>URLs</th><th>Status</th></tr></thead>
      <tbody>
        @forelse ($batches as $b)
          <tr data-row-href="{{ route('product-imports.show', $b) }}">
            <td><a href="{{ route('product-imports.show', $b) }}" class="fw-bold text-dark fs-8">{{ $b->name ?: 'Batch #'.$b->id }}</a></td>
            <td class="fs-8">{{ $b->created_at?->format('d M Y H:i') }}</td>
            <td><span class="fw-bold text-dark">{{ $b->items_count }}</span></td>
            <td>
              @foreach (($counts[$b->id] ?? collect()) as $status => $n)
                <span class="badge fs-8 me-1 {{ \App\Support\Fmt::badge($status) }}">{{ ucfirst(str_replace('_', ' ', $status)) }} {{ $n }}</span>
              @endforeach
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="text-center text-muted py-4">No imports yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($batches->hasPages())<div class="card-footer">{{ $batches->links() }}</div>@endif
</div>
@endsection
