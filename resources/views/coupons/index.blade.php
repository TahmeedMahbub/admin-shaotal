@extends('layouts.admin')
@section('title', 'Coupons')
@section('section', 'Marketing')

@section('content')
@php use App\Support\Fmt; @endphp
<x-admin.page-header title="Coupons &amp; Promo Vouchers" subtitle="Create and manage coupon codes, usage limits, discount rules, and redemptions.">
  <a href="{{ route('coupons.create') }}" class="btn btn-beige d-flex align-items-center gap-1"><i class="mdi mdi-plus fs-5"></i> Create Coupon</a>
</x-admin.page-header>

<x-admin.filters :statuses="['active', 'scheduled', 'expired', 'inactive']" placeholder="Search coupon code...">
  <select name="type" class="form-select" style="max-width: 190px;" onchange="this.form.submit()">
    <option value="">All Discount Types</option>
    @foreach (['percentage' => 'Percentage (%)', 'fixed_amount' => 'Fixed Amount (A$)', 'free_shipping' => 'Free Shipping'] as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach
  </select>
</x-admin.filters>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Coupon Code</th><th>Discount Type</th><th>Discount Value</th><th>Redemptions</th><th>Validity Period</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        @forelse ($rows as $c)
          @php $state = $c->status === 'active' ? ($c->starts_at && $c->starts_at->isFuture() ? 'scheduled' : ($c->expires_at && $c->expires_at->isPast() ? 'expired' : 'active')) : $c->status; @endphp
          <tr data-row-href="{{ route('coupons.edit', $c) }}">
            <td><a href="{{ route('coupons.show', $c) }}" class="fw-bold text-dark"><code>{{ $c->code }}</code></a></td>
            <td>{{ ucwords(str_replace('_', ' ', $c->discount_type)) }}</td>
            <td class="fw-bold">{{ $c->discount_type === 'percentage' ? rtrim(rtrim($c->discount_value, '0'), '.').'%' : ($c->discount_type === 'free_shipping' ? 'Free shipping' : Fmt::money($c->discount_value)) }}</td>
            <td>{{ $c->redemptions }}{{ $c->usage_limit ? ' / '.$c->usage_limit : '' }}</td>
            <td><small>{{ $c->starts_at?->format('M d, Y') ?? 'Any time' }} → {{ $c->expires_at?->format('M d, Y') ?? 'No expiry' }}</small></td>
            <td><x-admin.badge :status="$state" /></td>
            <td class="text-end"><x-admin.row-actions :show="route('coupons.show', $c)" :edit="route('coupons.edit', $c)" :delete="route('coupons.destroy', $c)" label="coupon" /></td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No coupons found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>
@endsection
