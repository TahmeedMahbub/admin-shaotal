@extends('layouts.admin')
@section('title', 'Abandoned Carts')
@section('section', 'Sales')

@section('content')
<x-admin.page-header title="Abandoned Carts" subtitle="Track cart sessions where items were left without order completion." />

<x-admin.filters placeholder="Search cart ID, customer, email...">
  <select name="type" class="form-select" style="max-width: 170px;" onchange="this.form.submit()">
    <option value="">Customer Type</option>
    <option value="registered" @selected(request('type') === 'registered')>Registered</option>
    <option value="guest" @selected(request('type') === 'guest')>Guest</option>
  </select>
  <select name="sort" class="form-select" style="max-width: 200px;" onchange="this.form.submit()">
    <option value="">Most Recent Abandoned</option>
    <option value="value" @selected(request('sort') === 'value')>Highest Cart Value</option>
  </select>
</x-admin.filters>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Cart ID</th><th>Customer</th><th>Type</th><th>Products</th><th>Total Value</th><th>Last Activity</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        @forelse ($rows as $c)
          <tr data-row-href="{{ route('abandoned-carts.show', $c) }}">
            <td><a href="{{ route('abandoned-carts.show', $c) }}" class="fw-bold text-dark">#CRT-{{ $c->id }}</a></td>
            <td>{{ $c->customer ? trim($c->customer->first_name.' '.$c->customer->last_name) : 'Guest' }}<br><small class="text-muted">{{ $c->customer->email ?? '' }}</small></td>
            <td><span class="badge {{ $c->customer_id ? 'bg-label-info' : 'bg-label-secondary' }}">{{ $c->customer_id ? 'Registered' : 'Guest' }}</span></td>
            <td><span class="badge bg-label-secondary">{{ (int) $c->items_qty }} Items</span></td>
            <td class="fw-bold">{{ \App\Support\Fmt::money($c->cart_value) }}</td>
            <td><small>{{ optional($c->last_activity_at)->diffForHumans() ?? '—' }}</small></td>
            <td><x-admin.badge status="abandoned" /></td>
            <td class="text-end"><x-admin.row-actions :show="route('abandoned-carts.show', $c)" /></td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted py-4">No abandoned carts.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>
@endsection
