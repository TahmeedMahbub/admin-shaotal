@extends('layouts.admin')
@section('title', $review->exists ? 'Edit Review' : 'Add Review')
@section('section', 'Engagement')

@section('content')
<x-admin.form-shell :title="$review->exists ? 'Edit Product Review' : 'Add Product Review'" subtitle="Create or moderate a customer product review."
  :action="$review->exists ? route('reviews.update', $review) : route('reviews.store')" :back="route('reviews.index')"
  :editing="$review->exists" :files="false" main-title="Review Content" side-title="Moderation" submit="Save Review">
  <x-admin.field name="product_id" label="Product" type="select" :value="$review->product_id" :options="$products" blank="Select Product" required />
  <div class="row">
    <x-admin.field col="col-md-6 mb-3" name="reviewer_name" label="Customer Name" :value="$review->reviewer_name" required />
    <x-admin.field col="col-md-6 mb-3" name="reviewer_email" label="Customer Email" type="email" :value="$review->reviewer_email" />
  </div>
  <x-admin.field name="title" label="Review Title" :value="$review->title" />
  <x-admin.field name="body" label="Review Comment / Text" type="textarea" rows="5" :value="$review->body" />
  <x-admin.field name="admin_reply" label="Admin Reply (shown publicly)" type="textarea" :value="$review->admin_reply" />
  <x-slot:side>
    <x-admin.field name="rating" label="Rating (1 to 5 Stars)" type="select" :value="$review->rating" :options="[5 => '5 Stars - Excellent', 4 => '4 Stars - Good', 3 => '3 Stars - Average', 2 => '2 Stars - Poor', 1 => '1 Star - Terrible']" required />
    <x-admin.field name="status" label="Moderation Status" type="select" :value="$review->status" :options="['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']" required />
  </x-slot:side>
</x-admin.form-shell>
@endsection
