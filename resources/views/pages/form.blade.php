@extends('layouts.admin')
@section('title', $page->exists ? 'Edit Page' : 'Create Page')
@section('section', 'Content')

@section('content')
<x-admin.form-shell :title="$page->exists ? 'Edit Standalone Page' : 'Create Standalone Page'" subtitle="Static page with custom HTML content."
  :action="$page->exists ? route('pages.update', $page) : route('pages.store')" :back="route('pages.index')"
  :editing="$page->exists" main-title="Page Content" side-title="Publishing" submit="Save Page">
  <x-admin.field name="title" label="Page Title" :value="$page->title" required placeholder="e.g. Return &amp; Warranty Policy" />
  <x-admin.field name="slug" label="URL Slug" :value="$page->slug" placeholder="return-and-warranty-policy" help="Auto-generated from the title; edit it if you want a custom one." />
  <x-admin.field name="content" label="Page Content (HTML)" type="textarea" rows="14" :value="$page->content" />
  <x-admin.field name="seo_title" label="SEO Title" :value="$page->seo_title" />
  <x-admin.field name="seo_description" label="SEO Description" type="textarea" rows="2" :value="$page->seo_description" />
  <x-slot:side>
    <x-admin.field name="status" label="Publishing Status" type="select" :value="$page->status" :options="['draft' => 'Draft', 'published' => 'Published']" required />
    <x-admin.image-field name="image" label="Featured Image (optional)" :current="$page->featured_image_url" />
    <x-admin.field name="featured_image_url" label="Or existing image URL (https)" type="url" :value="$page->featured_image_url" />
  </x-slot:side>
</x-admin.form-shell>
@endsection
