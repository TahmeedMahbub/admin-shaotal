@extends('layouts.admin')
@section('title', 'Cart #CRT-'.$cart->id)
@section('section', 'Abandoned Carts')

@section('content')
@php use App\Support\Fmt; @endphp
<x-admin.page-header :title="'Abandoned Cart #CRT-'.$cart->id" :subtitle="'Cart created '.$cart->created_at->format('M d, Y h:i A').' · last activity '.optional($cart->last_activity_at)->diffForHumans()">
  <a href="{{ route('abandoned-carts.index') }}" class="btn btn-outline-secondary">Back</a>
</x-admin.page-header>

<div class="row g-4">
  <div class="col-12 col-lg-8">
    <div class="card">
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Cart Items</h5></div>
      <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Item</th><th>SKU</th><th>Qty</th><th class="text-end">Unit Price</th></tr></thead>
        <tbody>
          @foreach ($items as $i)
            @php $unit = (float) ($i->variant->selling_price_override ?? $i->product->selling_price ?? $i->variant->price_override ?? $i->product->price); @endphp
            <tr><td><a href="{{ route('products.edit', $i->product_id) }}" class="fw-bold text-dark fs-8">{{ $i->product->name }}</a>
                @if ($i->variant)<div>@if ($i->variant->color)<span class="badge bg-label-secondary">{{ $i->variant->color->name }}</span>@endif @if ($i->variant->size)<span class="badge bg-label-secondary">{{ $i->variant->size->size_label }}</span>@endif</div>@endif</td>
              <td>{{ $i->variant->sku ?? $i->product->sku }}</td><td>{{ $i->quantity }}</td><td class="text-end">{{ Fmt::money($unit) }}</td></tr>
          @endforeach
        </tbody>
        <tfoot><tr><th colspan="3" class="text-end">Cart value</th><th class="text-end">{{ Fmt::money($total) }}</th></tr></tfoot>
      </table></div>
    </div>
  </div>
  <div class="col-12 col-lg-4">
    <div class="card mb-4">
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Customer</h5></div>
      <div class="card-body pt-3 fs-8">
        @if ($cart->customer)<div class="fw-bold">{{ trim($cart->customer->first_name.' '.$cart->customer->last_name) }}</div><div>{{ $cart->customer->email }}</div><div>{{ $cart->customer->phone }}</div>
          <a href="{{ route('customers.show', $cart->customer_id) }}">View Profile</a>
        @else<span class="text-muted">Guest cart (no account)</span>@endif
      </div>
    </div>
    <div class="card">
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Recovery Reminders</h5></div>
      <div class="card-body pt-3 fs-8">
        @forelse ($cart->logs as $l)<div class="mb-1"><x-admin.badge :status="$l->reminder_status" /> <small class="text-muted">{{ $l->reminder_sent_at ?? $l->created_at }}</small></div>@empty<div class="text-muted mb-2">No reminders recorded.</div>@endforelse
        <form method="POST" action="{{ route('abandoned-carts.update', $cart) }}" class="mt-3">@csrf @method('PUT')<input type="hidden" name="reminder_status" value="sent">
          <button class="btn btn-beige w-100">Mark Reminder Sent</button><div class="fs-9 text-muted mt-1">Records the reminder only; no email is sent from the admin.</div></form>
      </div>
    </div>
  </div>
</div>
@endsection
