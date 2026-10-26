@php $res = session('import_result'); @endphp
@if ($res && ($res['skipped'] || $res['invalid'] || $res['repeated']))
  <div class="alert alert-warning">
    @if ($res['repeated'])<div class="mb-2">{{ $res['repeated'] }} repeated line(s) in your list were ignored.</div>@endif
    @if ($res['skipped'])
      <div class="fw-bold mb-1">{{ count($res['skipped']) }} URL(s) were already imported and skipped:</div>
      <ul class="mb-2 fs-8">
        @foreach ($res['skipped'] as $s)
          <li><a href="{{ route('product-imports.item', $s['item']) }}">{{ \Illuminate\Support\Str::limit($s['url'], 90) }}</a> <span class="text-muted">(batch #{{ $s['batch'] }}, {{ $s['status'] }})</span></li>
        @endforeach
      </ul>
    @endif
    @if ($res['invalid'])
      <div class="fw-bold mb-1">{{ count($res['invalid']) }} line(s) are not valid http(s) URLs:</div>
      <ul class="mb-0 fs-8">@foreach ($res['invalid'] as $u)<li class="text-break">{{ \Illuminate\Support\Str::limit($u, 120) }}</li>@endforeach</ul>
    @endif
  </div>
@endif
