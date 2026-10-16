@extends('layouts.admin')
@section('title', 'Request #'.$req->request_number)
@section('section', 'Sourcing Requests')

@section('content')
<x-admin.page-header :title="'Sourcing Request #'.$req->request_number" :subtitle="'Submitted on '.$req->created_at->format('M d, Y h:i A')">
  <a href="{{ route('sourcing-requests.index') }}" class="btn btn-outline-secondary">Back</a>
</x-admin.page-header>

<div class="row g-4">
  <div class="col-12 col-lg-8">
    <div class="card mb-4">
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Request Details</h5></div>
      <div class="card-body pt-3 fs-8">
        <div class="row mb-3">
          <div class="col-md-6"><div class="text-muted">Requested Product / Item</div><div class="fw-bold">{{ $req->product_name ?: '—' }}</div></div>
          <div class="col-md-3"><div class="text-muted">Quantity</div><div class="fw-bold">{{ $req->requested_quantity }}</div></div>
          <div class="col-md-3"><div class="text-muted">Target Budget</div><div class="fw-bold">{{ $req->target_budget !== null ? \App\Support\Fmt::money($req->target_budget) : '—' }}</div></div>
        </div>
        @if ($req->product_url)<div class="mb-3"><div class="text-muted">Product URL</div><a href="{{ $req->product_url }}" target="_blank" rel="noopener noreferrer nofollow">{{ $req->product_url }}</a></div>@endif
        <div class="text-muted">Detailed Requirements</div>
        <div class="mb-3">{!! nl2br(e($req->details ?: '—')) !!}</div>
        <div class="text-muted mb-1">Customer Reference Images</div>
        <div class="d-flex flex-wrap gap-2">
          @forelse ($req->images as $img)<a href="{{ $img->image_url }}" target="_blank" rel="noopener"><x-admin.thumb :src="$img->image_url" :size="96" /></a>@empty<span class="text-muted">None uploaded.</span>@endforelse
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-4">
    <div class="card mb-4">
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Customer</h5></div>
      <div class="card-body pt-3 fs-8">
        <div class="fw-bold">{{ $req->customer_name }}</div><div>{{ $req->email }}</div><div>{{ $req->phone }}</div>
        @if ($req->customer_id)<a href="{{ route('customers.show', $req->customer_id) }}">View Profile</a>@endif
      </div>
    </div>
    <form method="POST" action="{{ route('sourcing-requests.update', $req) }}" class="card">@csrf @method('PUT')
      <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Manage Request</h5></div>
      <div class="card-body pt-3">
        <x-admin.field name="status" label="Request Status" type="select" :value="$req->status" :options="array_combine($statuses, array_map('ucfirst', $statuses))" required />
        <x-admin.field name="assigned_admin_id" label="Assigned To" type="select" :value="$req->assigned_admin_id" :options="$admins" blank="Unassigned" />
        <x-admin.field name="admin_notes" label="Internal Notes" type="textarea" :value="$req->admin_notes" />
        <button class="btn btn-beige w-100">Save Changes</button>
      </div>
    </form>
  </div>
</div>
@endsection
