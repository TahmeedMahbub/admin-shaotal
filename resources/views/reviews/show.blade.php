@extends('layouts.admin')
@section('title', 'Review #'.$review->id)
@section('section', 'Reviews')

@section('content')
<x-admin.page-header title="Review Details" :subtitle="'Submitted on '.$review->created_at->format('M d, Y h:i A')">
  <a href="{{ route('reviews.index') }}" class="btn btn-outline-secondary">Back</a>
  <a href="{{ route('reviews.edit', $review) }}" class="btn btn-beige">Edit / Moderate</a>
</x-admin.page-header>
<div class="row g-4">
  <div class="col-12 col-lg-8"><div class="card"><div class="card-body fs-8">
    <div class="text-warning fs-5">{{ str_repeat('★', $review->rating) }}<span class="text-muted">{{ str_repeat('★', 5 - $review->rating) }}</span></div>
    <h5 class="fw-bold mt-2">{{ $review->title }}</h5>
    <div class="text-muted text-uppercase fs-9">Review comment</div>
    <p>{!! nl2br(e($review->body ?: '—')) !!}</p>
    @if ($review->admin_reply)<div class="border-start ps-3"><div class="text-muted fs-9 text-uppercase">Admin reply · {{ $review->replied_at?->format('M d, Y') }}</div>{!! nl2br(e($review->admin_reply)) !!}</div>@endif
  </div></div></div>
  <div class="col-12 col-lg-4">
    <div class="card mb-4"><div class="card-body fs-8">
      <div class="mb-2"><x-admin.badge :status="$review->status" /></div>
      <div class="text-muted">Customer</div><div class="fw-bold">{{ $review->reviewer_name }}</div><div>{{ $review->reviewer_email }}</div>
      @if ($review->customer_id)<a href="{{ route('customers.show', $review->customer_id) }}">View profile</a>@endif
      <div class="text-muted mt-3">Product</div>
      @if ($review->product)<a href="{{ route('products.edit', $review->product_id) }}" class="fw-bold">{{ $review->product->name }}</a><div>SKU: {{ $review->product->sku ?: '—' }}</div>@endif
    </div></div>
    <div class="d-flex gap-2">
      @foreach (['approved' => 'Approve', 'rejected' => 'Reject'] as $st => $l)
        @if ($review->status !== $st)
          <form method="POST" action="{{ route('reviews.update', $review) }}" class="flex-fill">@csrf @method('PUT')
            <input type="hidden" name="product_id" value="{{ $review->product_id }}"><input type="hidden" name="reviewer_name" value="{{ $review->reviewer_name }}">
            <input type="hidden" name="reviewer_email" value="{{ $review->reviewer_email }}"><input type="hidden" name="title" value="{{ $review->title }}">
            <input type="hidden" name="body" value="{{ $review->body }}"><input type="hidden" name="admin_reply" value="{{ $review->admin_reply }}">
            <input type="hidden" name="rating" value="{{ $review->rating }}"><input type="hidden" name="status" value="{{ $st }}">
            <button class="btn {{ $st === 'approved' ? 'btn-beige' : 'btn-outline-danger' }} w-100">{{ $l }}</button>
          </form>
        @endif
      @endforeach
    </div>
  </div>
</div>
@endsection
