@extends('layouts.admin')
@section('title', 'Request #'.$req->request_number)
@section('section', 'Bulk Orders')

@section('content')
@php use App\Support\Fmt; @endphp
<x-admin.page-header :title="'Bulk Order Request #'.$req->request_number" :subtitle="'Submitted on '.$req->created_at->format('M d, Y h:i A')">
  <a href="{{ route('bulk-orders.index') }}" class="btn btn-outline-secondary">Back</a>
</x-admin.page-header>

<div class="row g-4">
  <div class="col-12 col-lg-8">
    <div class="card mb-4">
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Requested Products</h5></div>
      <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Product Details</th><th>Color &amp; Variant</th><th>Quantity</th><th>Target Unit Price</th><th class="text-end">Total Value</th></tr></thead>
        <tbody>
          @php $grand = 0; @endphp
          @foreach ($req->items as $it)
            @php $line = $it->target_price !== null ? $it->target_price * $it->quantity : null; $grand += $line ?? 0; @endphp
            <tr>
              <td>@if ($it->product_id)<a href="{{ route('products.edit', $it->product_id) }}" class="fw-bold text-dark fs-8">{{ $it->product_name }}</a>@else<span class="fw-bold fs-8">{{ $it->product_name }}</span>@endif
                @if ($it->notes)<div><small class="text-muted">{{ $it->notes }}</small></div>@endif</td>
              <td>@forelse ($it->variants as $v)<div class="fs-8">{{ trim($v->color_name.' '.$v->size_label) ?: 'Any' }} × {{ $v->quantity }}</div>@empty<span class="text-muted">—</span>@endforelse</td>
              <td>{{ number_format($it->quantity) }}</td>
              <td>{{ $it->target_price !== null ? Fmt::money($it->target_price) : '—' }}</td>
              <td class="text-end">{{ $line !== null ? Fmt::money($line) : '—' }}</td>
            </tr>
          @endforeach
        </tbody>
        <tfoot><tr><th colspan="4" class="text-end">Target total</th><th class="text-end">{{ Fmt::money($grand) }}</th></tr></tfoot>
      </table></div>
    </div>
    @if ($req->message)<div class="card"><div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Customer Message</h5></div><div class="card-body pt-3 fs-8">{!! nl2br(e($req->message)) !!}</div></div>@endif
  </div>
  <div class="col-12 col-lg-4">
    <div class="card mb-4">
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Contact</h5></div>
      <div class="card-body pt-3 fs-8">
        <div class="fw-bold">{{ $req->contact_name }}</div>@if ($req->company_name)<div>{{ $req->company_name }}</div>@endif<div>{{ $req->email }}</div><div>{{ $req->phone }}</div>
        @if ($req->customer_id)<a href="{{ route('customers.show', $req->customer_id) }}">View Profile</a>@endif
      </div>
    </div>
    <form method="POST" action="{{ route('bulk-orders.update', $req) }}" class="card">@csrf @method('PUT')
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Manage Request</h5></div>
      <div class="card-body pt-3">
        <x-admin.field name="status" label="Order Request Status" type="select" :value="$req->status" :options="array_combine($statuses, array_map('ucfirst', $statuses))" required />
        <x-admin.field name="assigned_admin_id" label="Assigned To" type="select" :value="$req->assigned_admin_id" :options="$admins" blank="Unassigned" />
        <x-admin.field name="admin_notes" label="Internal Notes" type="textarea" :value="$req->admin_notes" />
        <button class="btn btn-beige w-100">Save Changes</button>
      </div>
    </form>
  </div>
</div>
@endsection
