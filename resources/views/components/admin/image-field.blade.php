@props(['name' => 'image', 'label' => 'Image', 'current' => null, 'multiple' => false])
@php $pid = 'prev-'.preg_replace('/\W/', '', $name); @endphp
<div class="mb-3">
  <label class="form-label fw-bold fs-8" for="f-{{ $name }}">{{ $label }}</label>
  <div class="border rounded p-3 text-center" style="background-color: var(--beige-bg-alt); border-style: dashed !important;">
    @unless ($multiple)
      <img id="{{ $pid }}" src="{{ $current }}" alt="" class="img-fluid rounded mb-2 {{ $current ? '' : 'd-none' }}" style="max-height: 160px;">
    @endunless
    <input id="f-{{ $name }}" type="file" name="{{ $name }}{{ $multiple ? '[]' : '' }}" accept="image/jpeg,image/png,image/webp,image/gif" class="form-control form-control-sm {{ $errors->has($name) || $errors->has($name.'.*') ? 'is-invalid' : '' }}" @if (! $multiple) data-preview="#{{ $pid }}" @else multiple @endif>
    <div class="fs-9 text-muted mt-1">JPG, PNG, WebP or GIF, up to {{ config('cloudinary.max_kb') / 1024 }} MB. Uploaded to Cloudinary.</div>
  </div>
  @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
  @error($name.'.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
