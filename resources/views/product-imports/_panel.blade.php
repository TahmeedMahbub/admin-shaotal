@php
  $r = $importRaw;
  $host = $r['host'] ?? parse_url($import->source_url, PHP_URL_HOST);
  $sourceImages = collect($r['images'] ?? []);
  $chart = $r['size_chart'] ?? [];
@endphp
<div class="card mb-4" id="importPanel" style="border-color: var(--beige-border-medium);">
  <div class="card-header border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <h5 class="card-title mb-0 fs-6"><i class="mdi mdi-link-variant me-2 text-primary"></i> Imported from {{ $host }}</h5>
    <div class="d-flex gap-2 fs-8">
      <a href="{{ $import->source_url }}" target="_blank" rel="noopener noreferrer">Source page</a>
      <a href="{{ route('product-imports.item', $import) }}">Extraction details</a>
    </div>
  </div>
  <div class="card-body pt-3">
    @if ($importDuplicate)
      <div class="alert alert-danger fs-8 py-2">
        {{ \App\Services\ProductImport\DuplicateGuard::message($importDuplicate) }}
        <a href="{{ route('products.edit', $importDuplicate) }}" target="_blank">Open #{{ $importDuplicate->id }}</a>.
        Saving, approving and publishing are blocked until the name and SKU differ.
      </div>
    @endif

    <div class="row g-3 fs-8 mb-3">
      <div class="col-md-6">
        <div class="text-muted">Source price (reference only)</div>
        <div class="fw-bold text-dark">
          @if (! empty($r['price'])){{ $r['currency'] ?? '' }} {{ $r['price'] }}@if (! empty($r['list_price'])) <span class="text-muted fw-normal">(list {{ $r['list_price'] }})</span>@endif @else Not found @endif
        </div>
        <div class="text-muted fs-9">No exchange rate is applied. Enter the A$ price and optional sale price below.</div>
      </div>
      <div class="col-md-6">
        <div class="text-muted">Source reference</div>
        <div class="fw-bold text-dark">{{ $r['source_id'] ?? '—' }} @if (! empty($r['brand']))<span class="text-muted fw-normal">&bull; {{ $r['brand'] }}</span>@endif</div>
        @if (! empty($r['category_hint']))<div class="text-muted fs-9">Source category: {{ $r['category_hint'] }}</div>@endif
      </div>
    </div>

    @if (! empty($r['warnings']))
      <ul class="fs-8 ps-3 mb-3 text-warning-emphasis">@foreach ($r['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul>
    @endif

    <div class="mb-3">
      <div class="fw-bold fs-8 mb-1">Not on the source page &mdash; enter manually</div>
      <div class="d-flex flex-wrap gap-1" id="manualList">
        @forelse ($manualFields as $field => $label)
          <span class="badge bg-label-warning fs-9" data-manual="{{ $field }}">{{ $label }}</span>
        @empty
          <span class="badge bg-label-success fs-9">Everything is filled in</span>
        @endforelse
      </div>
    </div>

    <div class="border-top pt-3">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <div class="fw-bold fs-8">Source images ({{ $sourceImages->count() }}) &mdash; select, then add to the product</div>
        <div class="d-flex flex-wrap gap-1">
          <button type="button" class="btn btn-xs btn-outline-secondary" id="imgSelectAll">Select all</button>
          <button type="button" class="btn btn-xs btn-outline-beige" data-img-mode="original" title="Uploads the selected originals to Cloudinary unchanged">Use original</button>
          <button type="button" class="btn btn-xs btn-beige" data-img-mode="gemini" @disabled(! config('services.gemini.key')) title="{{ config('services.gemini.key') ? 'Re-renders the product on a warm beige #F5EDE3 background with AI, using the selected photo as the reference' : 'Set GEMINI_API_KEY in .env to enable' }}">Generate Shaotal-style (AI)</button>
          <button type="button" class="btn btn-xs btn-outline-beige" data-img-mode="local" title="Replaces only a plain background with #F5EDE3; the product pixels are untouched">Quick clean (no AI)</button>
        </div>
      </div>
      @if (! config('services.gemini.key'))
        <div class="text-muted fs-9 mb-2">AI generation is off: add <code>GEMINI_API_KEY</code> to <code>.env</code>. Quick clean works without it for photos with a plain background.</div>
      @endif
      <div class="d-flex flex-wrap gap-2" id="sourceImages">
        @forelse ($sourceImages as $i => $img)
          <label class="position-relative border rounded bg-white p-1" style="width:110px;cursor:pointer;">
            <input type="checkbox" class="form-check-input position-absolute src-pick" style="top:6px;left:6px;z-index:2" value="{{ $img['url'] }}" @checked($i === 0)>
            <img src="{{ $img['url'] }}" alt="" loading="lazy" referrerpolicy="no-referrer" style="width:100%;height:100px;object-fit:cover;border-radius:4px;">
            <span class="d-block text-center fs-9 text-muted">#{{ $i + 1 }}</span>
          </label>
        @empty
          <span class="text-muted fs-8">No source images were found.</span>
        @endforelse
      </div>
      <div id="imgJobs" class="mt-3 d-grid gap-2"></div>
    </div>

    @if ($chart)
      <details class="border-top pt-3 mt-3 fs-8">
        <summary class="fw-bold" style="cursor:pointer">Source size chart (reference)</summary>
        @foreach ($chart as $unit => $c)
          <div class="text-muted mt-2 text-uppercase fs-9">{{ $unit }}</div>
          <div class="table-responsive"><table class="table table-sm mb-1 fs-9">
            <thead><tr>@foreach ($c['headers'] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
            <tbody>@foreach ($c['rows'] as $row)<tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endforeach</tbody>
          </table></div>
        @endforeach
        <div class="text-muted fs-9">Pick or create a matching size guide in the Sizes card; the chart is not imported automatically.</div>
      </details>
    @endif

    @if (! empty($r['attributes']))
      <details class="border-top pt-3 mt-3 fs-8">
        <summary class="fw-bold" style="cursor:pointer">Other details listed on the page</summary>
        <dl class="row mb-0 mt-2 fs-9">@foreach ($r['attributes'] as $k => $v)<dt class="col-sm-3 text-capitalize">{{ $k }}</dt><dd class="col-sm-9">{{ $v }}</dd>@endforeach</dl>
      </details>
    @endif
  </div>
</div>
