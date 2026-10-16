@extends('layouts.admin')
@section('title', 'Customers')
@section('section', 'Sales')

@section('content')
<x-admin.page-header title="Customers Directory" subtitle="Manage registered store customer accounts and profiles." />

<x-admin.filters :statuses="['active', 'inactive', 'blocked']" placeholder="Search by name, email, phone...">
  <select name="sort" class="form-select" style="max-width: 190px;" onchange="this.form.submit()">
    @foreach (['' => 'Newest Account', 'spent' => 'Highest Total Spent', 'orders' => 'Most Orders'] as $k => $l)<option value="{{ $k }}" @selected((string) request('sort') === (string) $k)>{{ $l }}</option>@endforeach
  </select>
</x-admin.filters>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Customer</th><th>Contact</th><th>Orders</th><th>Total Spent</th><th>Last Order</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        @forelse ($rows as $c)
          <tr>
            <td><a href="{{ route('customers.show', $c) }}" class="fw-bold text-dark fs-8">{{ trim($c->first_name.' '.$c->last_name) }}</a><div><small class="text-muted">ID #{{ $c->id }}</small></div></td>
            <td>{{ $c->email }}<br><small class="text-muted">{{ $c->phone }}</small></td>
            <td><span class="badge bg-label-secondary">{{ $c->orders_count }} {{ $c->orders_count == 1 ? 'Order' : 'Orders' }}</span></td>
            <td class="fw-bold">{{ \App\Support\Fmt::money($c->total_spent) }}</td>
            <td>{{ $c->last_order_at ? \Illuminate\Support\Carbon::parse($c->last_order_at)->format('M d, Y') : '—' }}</td>
            <td><x-admin.badge :status="$c->status" /></td>
            <td class="text-end"><x-admin.row-actions :show="route('customers.show', $c)" :edit="route('customers.edit', $c)" /></td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No customers found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>
@endsection
