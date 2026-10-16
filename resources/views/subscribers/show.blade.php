@extends('layouts.admin')
@section('title', 'Subscriber')
@section('section', 'Subscribers')

@section('content')
<x-admin.page-header :title="$subscriber->email" :subtitle="'Subscriber #'.$subscriber->id">
  <a href="{{ route('subscribers.index') }}" class="btn btn-outline-secondary">Back</a>
  <a href="{{ route('subscribers.edit', $subscriber) }}" class="btn btn-beige">Edit</a>
</x-admin.page-header>
<div class="card"><div class="card-body fs-8 row g-3">
  <div class="col-md-3"><div class="text-muted">Status</div><x-admin.badge :status="$subscriber->status" /></div>
  <div class="col-md-3"><div class="text-muted">Opt-in source</div>{{ \App\Http\Controllers\SubscriberController::SOURCES[$subscriber->source] ?? ($subscriber->source ?: '—') }}</div>
  <div class="col-md-3"><div class="text-muted">Subscribed</div>{{ $subscriber->subscribed_at?->format('M d, Y h:i A') ?? '—' }}</div>
  <div class="col-md-3"><div class="text-muted">Unsubscribed</div>{{ $subscriber->unsubscribed_at?->format('M d, Y h:i A') ?? '—' }}</div>
</div></div>
@endsection
