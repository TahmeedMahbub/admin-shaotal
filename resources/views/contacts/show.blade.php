@extends('layouts.admin')
@section('title', 'Contact #'.$contact->id)
@section('section', 'Contacts')

@section('content')
<x-admin.page-header title="Contact Details" :subtitle="'Received '.$contact->created_at->format('M d, Y h:i A')">
  <a href="{{ route('contacts.index') }}" class="btn btn-outline-secondary">Back</a>
  <a href="{{ route('contacts.edit', $contact) }}" class="btn btn-beige">Edit</a>
  <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-delete-url="{{ route('contacts.destroy', $contact) }}" data-delete-label="contact message">Delete</button>
</x-admin.page-header>
<div class="row g-4">
  <div class="col-12 col-lg-8"><div class="card"><div class="card-body fs-8">
    <div class="text-muted text-uppercase fs-9">Inquiry message content</div>
    <h5 class="fw-bold mt-1">{{ $contact->subject ?: '(no subject)' }}</h5>
    <p>{!! nl2br(e($contact->message)) !!}</p>
    @if ($contact->admin_notes)<hr><div class="text-muted text-uppercase fs-9">Admin notes</div>{!! nl2br(e($contact->admin_notes)) !!}@endif
  </div></div></div>
  <div class="col-12 col-lg-4"><div class="card"><div class="card-body fs-8">
    <div class="mb-2"><x-admin.badge :status="$contact->status" /></div>
    <div class="text-muted">Full name</div><div class="fw-bold mb-2">{{ $contact->name }}</div>
    <div class="text-muted">Email</div><div class="mb-2">{{ $contact->email ?: '—' }}</div>
    <div class="text-muted">Phone</div><div class="mb-2">{{ $contact->phone ?: '—' }}</div>
    <div class="text-muted">Assigned to</div><div>{{ $contact->assignee->name ?? 'Unassigned' }}</div>
  </div></div></div>
</div>
@endsection
