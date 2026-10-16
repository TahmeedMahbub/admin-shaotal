@extends('layouts.admin')
@section('title', $coupon->code)
@section('section', 'Coupons')

@section('content')
@php use App\Support\Fmt; @endphp
<x-admin.page-header :title="$coupon->code" :subtitle="'Coupon ID: #'.$coupon->id">
  <a href="{{ route('coupons.index') }}" class="btn btn-outline-secondary">Back</a>
  <a href="{{ route('coupons.edit', $coupon) }}" class="btn btn-beige">Edit Coupon</a>
</x-admin.page-header>
<div class="row g-4">
  <div class="col-12 col-lg-8">
    <div class="card"><div class="card-body fs-8"><div class="row g-3">
      @foreach ([
        'Discount Type' => ucwords(str_replace('_', ' ', $coupon->discount_type)),
        'Discount Value' => $coupon->discount_type === 'percentage' ? (float) $coupon->discount_value.'%' : Fmt::money($coupon->discount_value),
        'Minimum Order Value' => $coupon->minimum_order_amount !== null ? Fmt::money($coupon->minimum_order_amount) : 'None',
        'Maximum Discount Cap' => $coupon->maximum_discount_amount !== null ? Fmt::money($coupon->maximum_discount_amount) : 'None',
        'Start Date' => $coupon->starts_at?->format('M d, Y h:i A') ?? 'Any time',
        'End Date' => $coupon->expires_at?->format('M d, Y h:i A') ?? 'No expiry',
        'Total Usage Limit' => $coupon->usage_limit ?? 'Unlimited',
        'Per-Customer Limit' => $coupon->usage_limit_per_customer ?? 'Unlimited',
      ] as $l => $v)<div class="col-md-6"><div class="text-muted text-uppercase fs-9">{{ $l }}</div><div class="fw-bold">{{ $v }}</div></div>@endforeach
      <div class="col-12"><div class="text-muted text-uppercase fs-9">Description</div><div>{{ $coupon->description ?: '—' }}</div></div>
      <div class="col-md-6"><div class="text-muted text-uppercase fs-9">Categories</div>@forelse ($coupon->categories as $c)<span class="badge bg-label-secondary me-1">{{ $c->name }}</span>@empty All @endforelse</div>
      <div class="col-md-6"><div class="text-muted text-uppercase fs-9">Products</div>@forelse ($coupon->products as $p)<span class="badge bg-label-secondary me-1">{{ $p->name }}</span>@empty All @endforelse</div>
    </div></div></div>
  </div>
  <div class="col-12 col-lg-4">
    <div class="card mb-4"><div class="card-body"><x-admin.badge :status="$coupon->status" />
      <h3 class="fw-bold mt-3 mb-0">{{ $redemptions->c }} redemptions</h3><small class="text-muted">{{ Fmt::money($redemptions->d) }} discounted in total</small></div></div>
    <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#deleteModal" data-delete-url="{{ route('coupons.destroy', $coupon) }}" data-delete-label="coupon">Delete Coupon</button>
  </div>
</div>
@endsection
