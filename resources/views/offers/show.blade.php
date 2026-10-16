@extends('layouts.admin')
@section('title', $offer->name)
@section('section', 'Offers')

@section('content')
<x-admin.page-header :title="$offer->name" :subtitle="'Offer ID: #OFF-'.$offer->id">
  <a href="{{ route('offers.index') }}" class="btn btn-outline-secondary">Back</a>
  <a href="{{ route('offers.edit', $offer) }}" class="btn btn-beige">Edit Offer</a>
  <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-delete-url="{{ route('offers.destroy', $offer) }}" data-delete-label="offer">Delete</button>
</x-admin.page-header>
<div class="card"><div class="card-body fs-8"><div class="row g-3">
  <div class="col-md-3"><div class="text-muted text-uppercase fs-9">Discount type</div><div class="fw-bold">{{ ucwords(str_replace('_', ' ', $offer->discount_type)) }}</div></div>
  <div class="col-md-3"><div class="text-muted text-uppercase fs-9">Discount value</div><div class="fw-bold">{{ $offer->discount_type === 'percentage' ? (float) $offer->discount_value.'%' : \App\Support\Fmt::money($offer->discount_value) }}</div></div>
  <div class="col-md-3"><div class="text-muted text-uppercase fs-9">Start</div>{{ $offer->starts_at?->format('M d, Y h:i A') ?? 'Any time' }}</div>
  <div class="col-md-3"><div class="text-muted text-uppercase fs-9">End</div>{{ $offer->ends_at?->format('M d, Y h:i A') ?? 'No end' }}</div>
  <div class="col-12"><div class="text-muted text-uppercase fs-9">Description</div>{{ $offer->description ?: '—' }}</div>
  <div class="col-md-6"><div class="text-muted text-uppercase fs-9">Applicable categories</div>@forelse ($offer->categories as $c)<span class="badge bg-label-secondary me-1">{{ $c->name }}</span>@empty All @endforelse</div>
  <div class="col-md-6"><div class="text-muted text-uppercase fs-9">Applicable products</div>@forelse ($offer->products as $p)<span class="badge bg-label-secondary me-1">{{ $p->name }}</span>@empty All @endforelse</div>
  <div class="col-12"><x-admin.badge :status="$offer->status" /></div>
</div></div></div>
@endsection
