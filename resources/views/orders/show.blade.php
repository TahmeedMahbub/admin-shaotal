@extends('layouts.admin')
@section('title', 'Order #'.$order->order_number)
@section('section', 'Orders')

@section('content')
@php use App\Support\Fmt; @endphp
<x-admin.page-header :title="'Order #'.$order->order_number" :subtitle="'Placed on '.($order->placed_at ?? $order->created_at)->format('M d, Y \a\t h:i A')">
  <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary">Back</a>
</x-admin.page-header>

<div class="row g-4">
  <div class="col-12 col-lg-8">
    <div class="card mb-4">
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Order Items</h5></div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Item Details</th><th>SKU</th><th>Variant</th><th>Qty</th><th class="text-end">Price</th></tr></thead>
          <tbody>
            @foreach ($order->items as $i)
              <tr>
                <td><div class="d-flex align-items-center gap-2"><x-admin.thumb :src="$i->image_url" icon="mdi-package-variant" />
                  @if ($i->product_id)<a href="{{ route('products.edit', $i->product_id) }}" class="fw-bold text-dark fs-8">{{ $i->product_name }}</a>@else<span class="fw-bold fs-8">{{ $i->product_name }}</span>@endif</div></td>
                <td>{{ $i->sku ?: '—' }}</td>
                <td>@if ($i->variant_color)<span class="badge bg-label-secondary">{{ $i->variant_color }}</span>@endif @if ($i->variant_size)<span class="badge bg-label-secondary">{{ $i->variant_size }}</span>@endif</td>
                <td>{{ $i->quantity }}</td>
                <td class="text-end"><div class="fw-bold">{{ Fmt::money($i->line_total) }}</div><small class="text-muted">{{ Fmt::money($i->unit_selling_price) }} each</small></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="card-body border-top">
        <dl class="row mb-0 fs-8 justify-content-end" style="max-width: 340px; margin-left: auto;">
          <dt class="col-7">Subtotal</dt><dd class="col-5 text-end">{{ Fmt::money($order->subtotal) }}</dd>
          <dt class="col-7">Discount @if ($order->coupon_code)<span class="badge bg-label-success">{{ $order->coupon_code }}</span>@endif</dt><dd class="col-5 text-end">-{{ Fmt::money($order->discount_total) }}</dd>
          <dt class="col-7">Shipping</dt><dd class="col-5 text-end">{{ Fmt::money($order->shipping_total) }}</dd>
          <dt class="col-7">Tax</dt><dd class="col-5 text-end">{{ Fmt::money($order->tax_total) }}</dd>
          <dt class="col-7 fs-6">Total</dt><dd class="col-5 text-end fw-bold fs-6">{{ Fmt::money($order->grand_total) }}</dd>
        </dl>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Order Timeline</h5></div>
      <div class="card-body pt-3">
        @forelse ($order->timeline as $t)
          <div class="mb-3"><x-admin.badge :status="str_replace('payment:', '', $t->new_status)" />
            @if (str_starts_with($t->new_status, 'payment:'))<small class="text-muted">(payment)</small>@endif
            <div><small class="text-muted">{{ $t->created_at->format('M d, Y \a\t h:i A') }} &bull; {{ $t->admin->name ?? 'Customer / system' }}@if ($t->note) &bull; {{ $t->note }}@endif</small></div></div>
        @empty
          <div class="text-muted">No history yet.</div>
        @endforelse
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-4">
    <div class="card mb-4">
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Manage Order</h5></div>
      <div class="card-body pt-3">
        <form method="POST" action="{{ route('orders.status', $order) }}" class="mb-3">@csrf
          <label class="form-label fw-bold fs-8">Order Status</label>
          <select name="order_status" class="form-select mb-2">@foreach ($orderStatuses as $s)<option value="{{ $s }}" @selected($order->order_status === $s)>{{ ucwords($s) }}</option>@endforeach</select>
          <input type="text" name="note" class="form-control mb-2" maxlength="500" placeholder="Optional note for the timeline">
          <button class="btn btn-beige w-100">Update Status</button>
        </form>
        <form method="POST" action="{{ route('orders.payment', $order) }}">@csrf
          <label class="form-label fw-bold fs-8">Payment Status</label>
          <select name="payment_status" class="form-select mb-2">@foreach ($paymentStatuses as $s)<option value="{{ $s }}" @selected($order->payment_status === $s)>{{ ucwords(str_replace('_', ' ', $s)) }}</option>@endforeach</select>
          <button class="btn btn-outline-beige w-100">Update Payment</button>
        </form>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Customer</h5></div>
      <div class="card-body pt-3 fs-8">
        <div class="fw-bold">{{ $order->shipping_recipient_name }}</div>
        <div>{{ $order->customer_email }}</div><div>{{ $order->customer_phone ?: $order->shipping_phone }}</div>
        @if ($order->customer_id)<a href="{{ route('customers.show', $order->customer_id) }}">View Profile</a>@else<span class="text-muted">Guest checkout</span>@endif
        <hr>
        <div class="fw-bold mb-1">Shipping ({{ ucfirst($order->fulfillment_type) }})</div>
        <div>{{ $order->shipping_street_address }}<br>{{ $order->shipping_suburb }} {{ $order->shipping_state }} {{ $order->shipping_postcode }}</div>
        @if ($order->customer_note)<hr><div class="fw-bold mb-1">Customer note</div><div>{{ $order->customer_note }}</div>@endif
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Payments</h5></div>
      <div class="card-body pt-3 fs-8">
        <div class="mb-2">Method: <strong>{{ strtoupper($order->payment_method ?: '—') }}</strong></div>
        @foreach ($order->payments as $p)
          <div class="d-flex justify-content-between"><span>{{ $p->provider ?: $p->payment_method }} · {{ Fmt::money($p->amount) }}</span><x-admin.badge :status="$p->status" /></div>
        @endforeach
      </div>
    </div>

    <div class="card">
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Internal Note</h5></div>
      <form method="POST" action="{{ route('orders.note', $order) }}" class="card-body pt-3">@csrf
        <textarea name="admin_note" rows="3" class="form-control mb-2" maxlength="5000">{{ old('admin_note', $order->admin_note) }}</textarea>
        <button class="btn btn-outline-beige w-100">Save Note</button>
      </form>
    </div>
  </div>
</div>
@endsection
