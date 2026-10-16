@props(['statuses' => [], 'statusName' => 'status', 'placeholder' => 'Search...'])
<form method="GET" class="card mb-4">
  <div class="card-body py-3 d-flex flex-wrap gap-2 align-items-center">
    <div class="input-group input-group-merge" style="max-width: 320px;">
      <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
      <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ $placeholder }}">
    </div>
    @if ($statuses)
      <select name="{{ $statusName }}" class="form-select" style="max-width: 200px;" onchange="this.form.submit()">
        <option value="">All statuses</option>
        @foreach ($statuses as $s)
          <option value="{{ $s }}" @selected(request($statusName) === $s)>{{ ucwords(str_replace('_', ' ', $s)) }}</option>
        @endforeach
      </select>
    @endif
    {{ $slot }}
    <button class="btn btn-beige">Filter</button>
    @if (request()->hasAny(['q', $statusName]) && (request('q') || request($statusName)))
      <a href="{{ url()->current() }}" class="btn btn-outline-secondary">Reset</a>
    @endif
  </div>
</form>
