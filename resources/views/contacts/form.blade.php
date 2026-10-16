@extends('layouts.admin')
@section('title', $contact->exists ? 'Edit Contact' : 'Log Contact Message')
@section('section', 'Engagement')

@section('content')
<x-admin.form-shell :title="$contact->exists ? 'Edit Contact Message' : 'Create Contact Message'" subtitle="Log a customer support message or phone inquiry."
  :action="$contact->exists ? route('contacts.update', $contact) : route('contacts.store')" :back="route('contacts.index')"
  :editing="$contact->exists" :files="false" main-title="Inquiry" side-title="Handling" submit="Save Message">
  <div class="row">
    <x-admin.field col="col-md-6 mb-3" name="name" label="Sender Full Name" :value="$contact->name" required />
    <x-admin.field col="col-md-6 mb-3" name="email" label="Email Address" type="email" :value="$contact->email" />
    <x-admin.field col="col-md-6 mb-3" name="phone" label="Phone Number (Optional)" :value="$contact->phone" />
    <x-admin.field col="col-md-6 mb-3" name="subject" label="Subject" :value="$contact->subject" />
  </div>
  <x-admin.field name="message" label="Inquiry Message Text" type="textarea" rows="6" :value="$contact->message" required />
  <x-slot:side>
    <x-admin.field name="status" label="Status" type="select" :value="$contact->status" :options="array_combine($statuses, array_map(fn ($s) => ucwords(str_replace('_', ' ', $s)), $statuses))" required />
    <x-admin.field name="assigned_admin_id" label="Assigned To" type="select" :value="$contact->assigned_admin_id" :options="$admins" blank="Unassigned" />
    <x-admin.field name="admin_notes" label="Admin Resolution Notes" type="textarea" :value="$contact->admin_notes" />
  </x-slot:side>
</x-admin.form-shell>
@endsection
