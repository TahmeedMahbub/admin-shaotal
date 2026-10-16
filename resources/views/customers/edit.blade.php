@extends('layouts.admin')
@section('title', 'Edit Customer')
@section('section', 'Customers')

@section('content')
<x-admin.form-shell title="Edit Customer Information" :subtitle="'Customer ID: #'.$customer->id" :action="route('customers.update', $customer)" :back="route('customers.show', $customer)"
  :editing="true" :files="false" main-title="Personal &amp; Contact Info" side-title="Account Status" submit="Update Profile">
  <div class="row">
    <x-admin.field col="col-md-6 mb-3" name="first_name" label="First Name" :value="$customer->first_name" required />
    <x-admin.field col="col-md-6 mb-3" name="last_name" label="Last Name" :value="$customer->last_name" />
    <x-admin.field col="col-md-6 mb-3" name="email" label="Email Address" type="email" :value="$customer->email" />
    <x-admin.field col="col-md-6 mb-3" name="phone" label="Phone Number" :value="$customer->phone" />
  </div>
  <h6 class="fw-bold mt-2">Primary Shipping Address</h6>
  <x-admin.field name="street_address" label="Street Address" :value="$address->street_address ?? null" />
  <div class="row">
    <x-admin.field col="col-md-5 mb-3" name="suburb" label="Suburb / City" :value="$address->suburb ?? null" />
    <x-admin.field col="col-md-4 mb-3" name="state" label="State" :value="$address->state ?? null" />
    <x-admin.field col="col-md-3 mb-3" name="postcode" label="Postcode" :value="$address->postcode ?? null" />
  </div>
  <x-slot:side>
    <x-admin.field name="status" label="Status" type="select" :value="$customer->status" :options="['active' => 'Active (Enabled)', 'inactive' => 'Inactive (Disabled)', 'blocked' => 'Blocked']" required />
    <x-admin.field name="notes" label="Internal Notes" type="textarea" :value="$customer->notes" />
  </x-slot:side>
</x-admin.form-shell>
@endsection
