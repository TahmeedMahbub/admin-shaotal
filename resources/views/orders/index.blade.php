@extends('layouts.admin')
@section('title', 'Orders')
@section('section', 'Sales')

@section('content')
<x-admin.page-header title="Orders Management" subtitle="Track, process, and manage customer store orders." />

<x-admin.filters :statuses="$orderStatuses" status-name="order_status" placeholder="Search by order no, customer, email...">
  <select name="payment_status" class="form-select" style="max-width: 190px;" onchange="this.form.submit()">
    <option value="">Payment Status</option>
    @foreach ($paymentStatuses as $s)<option value="{{ $s }}" @selected(request('payment_status') === $s)>{{ ucwords(str_replace('_', ' ', $s)) }}</option>@endforeach
  </select>
  <select name="range" class="form-select" style="max-width: 160px;" onchange="this.form.submit()">
    <option value="">Date Range</option>
    @foreach (['today' => 'Today', '7d' => 'Last 7 Days', '30d' => 'Last 30 Days', 'month' => 'This Month'] as $k => $l)<option value="{{ $k }}" @selected(request('range') === $k)>{{ $l }}</option>@endforeach
  </select>
  <select name="sort" class="form-select" style="max-width: 160px;" onchange="this.form.submit()">
    @foreach (['' => 'Newest First', 'oldest' => 'Oldest First', 'amount' => 'Highest Amount'] as $k => $l)<option value="{{ $k }}" @selected((string) request('sort') === (string) $k)>{{ $l }}</option>@endforeach
  </select>
</x-admin.filters>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Order ID</th><th>Date</th><th>Customer</th><th>Items</th><th>Total</th><th>Payment Status</th><th>Order Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        @forelse ($rows as $o)
          <tr data-row-href="{{ route('orders.show', $o) }}">
            <td><a href="{{ route('orders.show', $o) }}" class="fw-bold text-dark">#{{ $o->order_number }}</a></td>
            <td>{{ $o->created_at->format('M d, Y') }}<br><small class="text-muted">{{ $o->created_at->format('h:i A') }}</small></td>
            <td>@if ($o->customer_id)<a href="{{ route('customers.show', $o->customer_id) }}" class="text-dark">{{ $o->shipping_recipient_name }}</a>@else{{ $o->shipping_recipient_name }}@endif<br><small class="text-muted">{{ $o->customer_email }}</small></td>
            <td><span class="badge bg-label-secondary">{{ (int) $o->items_qty }} {{ $o->items_qty == 1 ? 'Item' : 'Items' }}</span></td>
            <td class="fw-bold">{{ \App\Support\Fmt::money($o->grand_total) }}</td>
            <td><x-admin.badge :status="$o->payment_status" /></td>
            <td><x-admin.badge :status="$o->order_status" /></td>
            <td class="text-end"><x-admin.row-actions :show="route('orders.show', $o)" /></td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted py-4">No orders found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>
@endsection
