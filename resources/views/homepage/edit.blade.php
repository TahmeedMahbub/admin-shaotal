@extends('layouts.admin')
@section('title', 'Homepage')
@section('section', 'Content')

@section('content')
@php $v = fn ($k) => old($k, $s[$k] ?? ''); $on = fn ($k) => (bool) old($k, $s[$k] ?? false); @endphp
<x-admin.page-header title="Homepage Content Settings" subtitle="Hero section headline, banner slider toggle, announcement bar and featured section title." />
<form method="POST" action="{{ route('homepage.update') }}" novalidate>
  @csrf @method('PUT')
  <div class="card mb-4">
    <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Hero Section</h5></div>
    <div class="card-body pt-3">
      <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="home_hero_slider_enabled" name="home_hero_slider_enabled" value="1" @checked($on('home_hero_slider_enabled'))><label class="form-check-label fw-bold fs-8" for="home_hero_slider_enabled">Enable Hero Banner Slider</label></div>
      <x-admin.field name="home_hero_title" label="Hero Headline Title" :value="$v('home_hero_title')" />
      <x-admin.field name="home_hero_subtitle" label="Hero Subtitle Text" type="textarea" rows="2" :value="$v('home_hero_subtitle')" />
      <small class="text-muted">Slides themselves are managed under Banners.</small>
    </div>
  </div>
  <div class="card mb-4">
    <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Announcement Bar &amp; Featured Section</h5></div>
    <div class="card-body pt-3">
      <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="home_announcement_enabled" name="home_announcement_enabled" value="1" @checked($on('home_announcement_enabled'))><label class="form-check-label fw-bold fs-8" for="home_announcement_enabled">Enable Top Announcement Bar</label></div>
      <x-admin.field name="home_announcement_text" label="Announcement Bar Message" :value="$v('home_announcement_text')" />
      <x-admin.field name="home_featured_title" label="Featured Products Section Title" :value="$v('home_featured_title')" />
    </div>
  </div>
  <button class="btn btn-beige">Save Homepage Settings</button>
</form>
@endsection
