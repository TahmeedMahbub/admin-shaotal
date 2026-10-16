@extends('layouts.admin')
@section('title', 'Sourcing Requests')
@section('section', 'Requests')

@section('content')
<x-admin.page-header title="Sourcing Requests" subtitle="Manage custom product sourcing inquiries submitted by customers." />

<x-admin.filters :statuses="$statuses" placeholder="Search request, customer, item...">
  <select name="sort" class="form-select" style="max-width: 180px;" onchange="this.form.submit()">
    <option value="">Newest First</option><option value="budget" @selected(request('sort') === 'budget')>Highest Budget</option>
  </select>
</x-admin.filters>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Request ID</th><th>Customer</th><th>Requested Item</th><th>Qty</th><th>Target Budget</th><th>Date</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        @forelse ($rows as $r)
          <tr>
            <td><a href="{{ route('sourcing-requests.show', $r) }}" class="fw-bold text-dark">#{{ $r->request_number }}</a></td>
            <td>{{ $r->customer_name }}<br><small class="text-muted">{{ $r->email }}</small></td>
            <td>{{ $r->product_name ?: '—' }}</td>
            <td>{{ $r->requested_quantity }}</td>
            <td>{{ $r->target_budget !== null ? \App\Support\Fmt::money($r->target_budget) : '—' }}</td>
            <td>{{ $r->created_at->format('M d, Y') }}</td>
            <td><x-admin.badge :status="$r->status" /></td>
            <td class="text-end"><x-admin.row-actions :show="route('sourcing-requests.show', $r)" :delete="route('sourcing-requests.destroy', $r)" label="request" /></td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted py-4">No sourcing requests found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>
@endsection
