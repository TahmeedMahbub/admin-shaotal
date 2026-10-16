@extends('layouts.admin')
@section('title', 'Offers')
@section('section', 'Marketing')

@section('content')
@php use App\Support\Fmt; @endphp
<x-admin.page-header title="Offers &amp; Discounts" subtitle="Manage promotional sales, category discounts, and special pricing campaigns.">
  <a href="{{ route('offers.create') }}" class="btn btn-beige d-flex align-items-center gap-1"><i class="mdi mdi-plus fs-5"></i> Create Offer</a>
</x-admin.page-header>

<x-admin.filters :statuses="['active', 'scheduled', 'expired', 'inactive']" placeholder="Search by offer name...">
  <select name="type" class="form-select" style="max-width: 190px;" onchange="this.form.submit()">
    <option value="">All Discount Types</option>
    <option value="percentage" @selected(request('type') === 'percentage')>Percentage (%)</option>
    <option value="fixed_amount" @selected(request('type') === 'fixed_amount')>Fixed Amount (A$)</option>
  </select>
</x-admin.filters>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Offer ID</th><th>Offer Name</th><th>Discount</th><th>Applicable Scope</th><th>Validity Period</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        @forelse ($rows as $o)
          @php $state = $o->status === 'active' ? ($o->starts_at && $o->starts_at->isFuture() ? 'scheduled' : ($o->ends_at && $o->ends_at->isPast() ? 'expired' : 'active')) : 'inactive'; @endphp
          <tr>
            <td><a href="{{ route('offers.show', $o) }}" class="fw-bold text-dark">#OFF-{{ $o->id }}</a></td>
            <td class="fw-bold fs-8">{{ $o->name }}</td>
            <td class="fw-bold">{{ $o->discount_type === 'percentage' ? (float) $o->discount_value.'%' : Fmt::money($o->discount_value) }}</td>
            <td>@if (! $o->products_count && ! $o->categories_count)<span class="badge bg-label-secondary">Whole catalog</span>@else
              @if ($o->categories_count)<span class="badge bg-label-info">{{ $o->categories_count }} categories</span>@endif
              @if ($o->products_count)<span class="badge bg-label-info">{{ $o->products_count }} products</span>@endif @endif</td>
            <td><small>{{ $o->starts_at?->format('M d, Y') ?? 'Any time' }} → {{ $o->ends_at?->format('M d, Y') ?? 'No end' }}</small></td>
            <td><x-admin.badge :status="$state" /></td>
            <td class="text-end"><x-admin.row-actions :show="route('offers.show', $o)" :edit="route('offers.edit', $o)" :delete="route('offers.destroy', $o)" label="offer" /></td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No offers found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>
@endsection
