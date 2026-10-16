@props(['src' => null, 'icon' => 'mdi-image-outline', 'size' => 44])
<div class="rounded border bg-white overflow-hidden d-flex align-items-center justify-content-center" style="width: {{ $size }}px; height: {{ $size }}px;">
  @if ($src)<img src="{{ $src }}" alt="" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">@else<i class="mdi {{ $icon }} fs-3 text-muted"></i>@endif
</div>
