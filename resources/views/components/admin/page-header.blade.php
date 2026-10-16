@props(['title', 'subtitle' => null])
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
  <div>
    <h4 class="fw-bold mb-1" style="color: var(--beige-text-dark);">{{ $title }}</h4>
    @if ($subtitle)<p class="text-muted mb-0 fs-7">{{ $subtitle }}</p>@endif
  </div>
  <div class="d-flex gap-2">{{ $slot }}</div>
</div>
