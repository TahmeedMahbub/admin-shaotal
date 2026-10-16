@extends('layouts.admin')
@section('title', $item->title)
@section('section', 'Site Content')

@section('content')
<x-admin.page-header :title="$item->title" :subtitle="'Location: '.$item->slug">
  <a href="{{ route('site-contents.index') }}" class="btn btn-outline-secondary">Back</a>
  <a href="{{ route('site-contents.edit', $item) }}" class="btn btn-beige">Edit</a>
</x-admin.page-header>
<div class="card"><div class="card-body"><x-admin.badge :status="$item->status" />
  <pre class="mt-3 mb-0 fs-8" style="white-space: pre-wrap;">{{ $item->content ?: 'No content yet.' }}</pre></div></div>
@endsection
