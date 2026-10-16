@props(['title', 'subtitle' => null, 'action', 'back', 'editing' => false, 'mainTitle' => 'Details', 'sideTitle' => 'Settings', 'submit' => 'Save', 'files' => true])
<x-admin.page-header :title="$title" :subtitle="$subtitle">
  <a href="{{ $back }}" class="btn btn-outline-secondary">Cancel</a>
</x-admin.page-header>

<form method="POST" action="{{ $action }}" @if ($files) enctype="multipart/form-data" @endif novalidate>
  @csrf
  @if ($editing) @method('PUT') @endif
  <div class="row g-4">
    <div class="col-12 col-lg-8">
      <div class="card mb-4">
        <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">{{ $mainTitle }}</h5></div>
        <div class="card-body pt-3">{{ $slot }}</div>
      </div>
    </div>
    <div class="col-12 col-lg-4">
      <div class="card mb-4">
        <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">{{ $sideTitle }}</h5></div>
        <div class="card-body pt-3">
          {{ $side ?? '' }}
          <button type="submit" class="btn btn-beige w-100">{{ $submit }}</button>
        </div>
      </div>
    </div>
  </div>
</form>
