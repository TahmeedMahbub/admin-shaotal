@extends('layouts.admin')
@section('title', trim($customer->first_name.' '.$customer->last_name))
@section('section', 'Customers')

@section('content')
@php use App\Support\Fmt; $avg = $stats->c ? $stats->s / $stats->c : 0; @endphp
<x-admin.page-header :title="trim($customer->first_name.' '.$customer->last_name)" :subtitle="'Customer ID: #'.$customer->id">
  <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">Back</a>
  <a href="{{ route('customers.edit', $customer) }}" class="btn btn-beige">Edit Customer</a>
</x-admin.page-header>

<div class="row g-4 mb-4">
  @foreach ([['Orders', $stats->c.' Orders', 'Excluding cancelled / returned'], ['Total Spent', Fmt::money($stats->s), 'Lifetime customer value'], ['Average Order', Fmt::money($avg), 'Per transaction average'], ['Last Order', $stats->last ? \Illuminate\Support\Carbon::parse($stats->last)->format('M d, Y') : '—', '']] as [$l, $v, $s])
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><span class="card-metric-label">{{ $l }}</span><h3 class="fw-bold mt-2 mb-1">{{ $v }}</h3><small class="text-muted">{{ $s }}</small></div></div></div>
  @endforeach
</div>

<div class="row g-4">
  <div class="col-12 col-lg-8">
    <div class="card mb-4">
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Recent Orders</h5></div>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Order ID</th><th>Date</th><th>Total</th><th>Payment</th><th>Order Status</th><th class="text-end">Action</th></tr></thead>
        <tbody>
          @forelse ($orders as $o)
            <tr data-row-href="{{ route('orders.show', $o->id) }}"><td><a href="{{ route('orders.show', $o->id) }}" class="fw-bold text-dark">#{{ $o->order_number }}</a></td><td>{{ $o->created_at->format('M d, Y') }}</td><td>{{ Fmt::money($o->grand_total) }}</td>
              <td><x-admin.badge :status="$o->payment_status" /></td><td><x-admin.badge :status="$o->order_status" /></td><td class="text-end"><x-admin.row-actions :show="route('orders.show', $o->id)" /></td></tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-4">No orders yet.</td></tr>
          @endforelse
        </tbody></table></div>
    </div>
    <div class="card">
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Requests</h5></div>
      <div class="card-body pt-3 fs-8">
        @forelse ($requests['sourcing'] as $r)<div class="mb-2">Sourcing <a href="{{ route('sourcing-requests.show', $r->id) }}">#{{ $r->request_number }}</a> · {{ $r->product_name }} <x-admin.badge :status="$r->status" /></div>@empty @endforelse
        @forelse ($requests['bulk'] as $r)<div class="mb-2">Bulk <a href="{{ route('bulk-orders.show', $r->id) }}">#{{ $r->request_number }}</a> · {{ $r->company_name }} <x-admin.badge :status="$r->status" /></div>@empty @endforelse
        @if ($requests['sourcing']->isEmpty() && $requests['bulk']->isEmpty())<span class="text-muted">No requests.</span>@endif
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-4">
    <div class="card mb-4">
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Account Details</h5></div>
      <div class="card-body pt-3 fs-8">
        <div class="mb-2"><span class="text-muted">Email</span><br>{{ $customer->email ?: '—' }} @if ($customer->email_verified_at)<i class="mdi mdi-check-decagram text-success"></i>@endif</div>
        <div class="mb-2"><span class="text-muted">Phone</span><br>{{ $customer->phone ?: '—' }}</div>
        <div class="mb-2"><span class="text-muted">Joined</span><br>{{ $customer->created_at->format('M d, Y') }}</div>
        <div class="mb-2"><span class="text-muted">Account Status</span><br><x-admin.badge :status="$customer->status" /></div>
        @if ($customer->notes)<div><span class="text-muted">Notes</span><br>{{ $customer->notes }}</div>@endif
      </div>
    </div>
    <div class="card">
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Addresses</h5></div>
      <div class="card-body pt-3 fs-8">
        @forelse ($customer->addresses as $a)
          <div class="mb-3"><strong>{{ $a->label ?: 'Address' }}</strong> @if ($a->is_default)<span class="badge bg-label-success">Default</span>@endif<br>{{ $a->recipient_name }}<br>{{ $a->street_address }}<br>{{ $a->suburb }} {{ $a->state }} {{ $a->postcode }}</div>
        @empty
          <span class="text-muted">No saved addresses.</span>
        @endforelse
      </div>
    </div>
  </div>
</div>
@endsection
