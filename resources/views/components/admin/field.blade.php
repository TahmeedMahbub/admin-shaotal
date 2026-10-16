@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'placeholder' => '', 'help' => null, 'rows' => 3, 'options' => [], 'blank' => null, 'col' => null])
@php
  $key = preg_replace('/\[(.*?)\]/', '.$1', $name);
  $val = old($key, $value);
  $cls = 'form-control'.($errors->has($key) ? ' is-invalid' : '');
@endphp
<div class="{{ $col ?? 'mb-3' }}">
  <label class="form-label fw-bold fs-8" for="f-{{ $name }}">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
  @if ($type === 'textarea')
    <textarea id="f-{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" class="{{ $cls }}" placeholder="{{ $placeholder }}" @required($required)>{{ $val }}</textarea>
  @elseif ($type === 'select')
    <select id="f-{{ $name }}" name="{{ $name }}" class="form-select{{ $errors->has($key) ? ' is-invalid' : '' }}" @required($required)>
      @if ($blank !== null)<option value="">{{ $blank }}</option>@endif
      @foreach ($options as $k => $text)
        <option value="{{ $k }}" @selected((string) $val === (string) $k)>{{ $text }}</option>
      @endforeach
    </select>
  @else
    <input id="f-{{ $name }}" type="{{ $type }}" name="{{ $name }}" value="{{ $val }}" class="{{ $cls }}" placeholder="{{ $placeholder }}" {{ $attributes }} @required($required)>
  @endif
  @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
  @if ($help)<div class="form-text fs-9">{{ $help }}</div>@endif
</div>
