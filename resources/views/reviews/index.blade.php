@extends('layouts.admin')
@section('title', 'Reviews')
@section('section', 'Engagement')

@section('content')
<x-admin.page-header title="Product Reviews" subtitle="Manage, approve, edit, and moderate store customer reviews.">
  <a href="{{ route('reviews.create') }}" class="btn btn-beige d-flex align-items-center gap-1"><i class="mdi mdi-plus fs-5"></i> Add Review</a>
</x-admin.page-header>

<x-admin.filters :statuses="['approved', 'pending', 'rejected']" placeholder="Search reviewer, product, text...">
  <select name="rating" class="form-select" style="max-width: 150px;" onchange="this.form.submit()">
    <option value="">All Ratings</option>
    @foreach ([5, 4, 3, 2, 1] as $r)<option value="{{ $r }}" @selected((string) request('rating') === (string) $r)>{{ $r }} Star{{ $r > 1 ? 's' : '' }}</option>@endforeach
  </select>
  <select name="sort" class="form-select" style="max-width: 190px;" onchange="this.form.submit()">
    @foreach (['' => 'Newest First', 'oldest' => 'Oldest First', 'high' => 'Rating: High to Low', 'low' => 'Rating: Low to High'] as $k => $l)<option value="{{ $k }}" @selected((string) request('sort') === (string) $k)>{{ $l }}</option>@endforeach
  </select>
</x-admin.filters>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Review ID</th><th>Customer</th><th>Product</th><th>Rating</th><th>Review Snippet</th><th>Date</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        @forelse ($rows as $r)
          <tr data-row-href="{{ route('reviews.edit', $r) }}">
            <td><a href="{{ route('reviews.show', $r) }}" class="fw-bold text-dark">#REV-{{ $r->id }}</a></td>
            <td>{{ $r->reviewer_name }}<br><small class="text-muted">{{ $r->reviewer_email }}</small></td>
            <td>{{ $r->product->name ?? '—' }}</td>
            <td class="text-warning text-nowrap">{{ str_repeat('★', $r->rating) }}<span class="text-muted">{{ str_repeat('★', 5 - $r->rating) }}</span></td>
            <td style="max-width: 280px;"><div class="fw-bold fs-8">{{ $r->title }}</div><small class="text-muted">{{ \Illuminate\Support\Str::limit($r->body, 70) }}</small></td>
            <td>{{ $r->created_at->format('M d, Y') }}</td>
            <td><x-admin.badge :status="$r->status" /></td>
            <td class="text-end"><x-admin.row-actions :show="route('reviews.show', $r)" :edit="route('reviews.edit', $r)" :delete="route('reviews.destroy', $r)" label="review" /></td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted py-4">No reviews found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>
@endsection
