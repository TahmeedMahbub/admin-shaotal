@extends('layouts.admin')
@section('title', 'Edit Site Content')
@section('section', 'Site Content')

@section('content')
<x-admin.form-shell title="Edit Site Content Location" :subtitle="'Location: '.$item->slug" :action="route('site-contents.update', $item)" :back="route('site-contents.index')"
  :editing="true" :files="false" main-title="Content" side-title="Status" submit="Save Content">
  <div class="mb-3"><label class="form-label fw-bold fs-8">Location Slug</label><input class="form-control" value="{{ $item->slug }}" disabled></div>
  <x-admin.field name="title" label="Title / Location Label" :value="$item->title" required />
  <x-admin.field name="content" label="HTML / Rich Text Content" type="textarea" rows="12" :value="$item->content" />
  <x-slot:side>
    <x-admin.field name="status" label="Status" type="select" :value="$item->status" :options="['enabled' => 'Enabled', 'disabled' => 'Disabled']" required />
  </x-slot:side>
</x-admin.form-shell>
@endsection
