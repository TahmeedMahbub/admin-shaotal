@extends('layouts.admin')
@section('title', 'Bulk Orders')
@section('section', 'Requests')

@section('content')
<x-admin.page-header title="Bulk Orders" subtitle="Manage wholesale and bulk order requests." />

<x-admin.filters :statuses="$statuses" placeholder="Search request, contact, company..." />

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Request ID</th><th>Contact / Company</th><th>Products</th><th>Total Qty</th><th>Date</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        @forelse ($rows as $r)
          <tr>
            <td><a href="{{ route('bulk-orders.show', $r) }}" class="fw-bold text-dark">#{{ $r->request_number }}</a></td>
            <td>{{ $r->contact_name }}<br><small class="text-muted">{{ $r->company_name ?: $r->email }}</small></td>
            <td><span class="badge bg-label-secondary">{{ $r->items_count }} Products</span></td>
            <td>{{ number_format($r->total_qty) }}</td>
            <td>{{ $r->created_at->format('M d, Y') }}</td>
            <td><x-admin.badge :status="$r->status" /></td>
            <td class="text-end"><x-admin.row-actions :show="route('bulk-orders.show', $r)" :delete="route('bulk-orders.destroy', $r)" label="request" /></td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No bulk order requests found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>
@endsection
