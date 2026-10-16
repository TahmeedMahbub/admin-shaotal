@foreach (['success' => 'success', 'error' => 'danger'] as $key => $type)
  @if (session($key))
    <div class="alert alert-{{ $type }} alert-dismissible fs-8" role="alert">{{ session($key) }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif
@endforeach
@if ($errors->any())
  <div class="alert alert-danger fs-8" role="alert"><strong>Please fix the following:</strong>
    <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
  </div>
@endif
