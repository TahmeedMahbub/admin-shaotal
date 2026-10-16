@extends('layouts.admin')
@section('title', $subscriber->exists ? 'Edit Subscriber' : 'Add Subscriber')
@section('section', 'Engagement')

@section('content')
<x-admin.form-shell :title="$subscriber->exists ? 'Edit Newsletter Subscriber' : 'Add Newsletter Subscriber'" subtitle="Email subscriber for the newsletter list."
  :action="$subscriber->exists ? route('subscribers.update', $subscriber) : route('subscribers.store')" :back="route('subscribers.index')"
  :editing="$subscriber->exists" :files="false" main-title="Subscriber" side-title="Status" submit="Save Subscriber">
  <x-admin.field name="email" label="Subscriber Email Address" type="email" :value="$subscriber->email" required />
  <x-admin.field name="source" label="Opt-in Source" type="select" :value="$subscriber->source" :options="\App\Http\Controllers\SubscriberController::SOURCES" blank="Unknown" />
  <x-slot:side>
    <x-admin.field name="status" label="Subscription Status" type="select" :value="$subscriber->status" :options="['subscribed' => 'Subscribed', 'unsubscribed' => 'Unsubscribed']" required />
  </x-slot:side>
</x-admin.form-shell>
@endsection
