@extends('layouts.admin')
@section('title', $guide->exists ? 'Edit Size Guide' : 'Create Size Guide')
@section('section', 'Catalog')

@section('content')
<x-admin.form-shell :title="$guide->exists ? 'Edit Size Guide' : 'Create Size Guide'" subtitle="Measurement attributes and sizes for storefront sizing."
  :action="$guide->exists ? route('size-guides.update', $guide) : route('size-guides.store')" :back="route('size-guides.index')"
  :editing="$guide->exists" :files="false" main-title="Guide Details" side-title="Status" submit="Save Size Guide">
  <x-admin.field name="title" label="Guide Title" :value="$guide->title" required placeholder="e.g. Men's Relaxed Linen Shirts" help="Displayed as the header in the storefront sizing modal." />
  <x-admin.field name="key" label="Unique Key" :value="$guide->key" required placeholder="e.g. mens-linen-shirt" help="Lowercase letters, numbers and dashes." />
  <x-admin.field name="customer_details" label="Fit &amp; Measurement Notes for Shoppers" type="textarea" :value="$guide->customer_details" />
  <x-admin.field name="admin_details" label="Private Notes (internal only)" type="textarea" :value="$guide->admin_details" />

  <h6 class="fw-bold mt-4">Measurement Chart</h6>
  <p class="fs-9 text-muted">Columns are measurement attributes; rows are sizes. Every cell needs a number (38, 38.5) or a range (71.1-76.2). Shoppers can view the chart in Inch or CM; the storefront converts from the unit saved here (1 in = 2.54 cm).</p>
  <div class="row g-3 align-items-end mb-3">
    <div class="col-12 col-md-5">
      <label class="form-label fw-semibold" for="measurement_unit">Measurement Unit <span class="text-danger">*</span></label>
      <select name="measurement_unit" id="measurement_unit" class="form-select @error('measurement_unit') is-invalid @enderror" required>
        @foreach (\App\Models\SizeGuide::UNIT_LABELS as $code => $label)
          <option value="{{ $code }}" @selected(old('measurement_unit', $matrix['unit']) === $code)>{{ $label }}</option>
        @endforeach
      </select>
      @error('measurement_unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-7">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="convertOnSwitch" checked>
        <label class="form-check-label fs-9" for="convertOnSwitch">When I switch the unit, convert the numbers below (1 in = 2.54 cm). Untick to keep the numbers and only change their unit.</label>
      </div>
    </div>
  </div>
  <div class="table-responsive"><table class="table table-bordered align-middle" id="matrix"></table></div>
  <div class="d-flex gap-2">
    <button type="button" class="btn btn-sm btn-outline-beige" id="addAttr"><i class="mdi mdi-plus"></i> Add attribute</button>
    <button type="button" class="btn btn-sm btn-outline-beige" id="addSize"><i class="mdi mdi-plus"></i> Add size</button>
  </div>

  <x-slot:side>
    <x-admin.field name="status" label="Status" type="select" :value="$guide->status" :options="['active' => 'Active', 'inactive' => 'Inactive']" required />
  </x-slot:side>
</x-admin.form-shell>
@endsection

@push('scripts')
<script>
(function () {
  var state = @json($matrix);
  var table = document.getElementById('matrix');
  var unitSel = document.getElementById('measurement_unit');
  var unit = unitSel.value, FACTOR = 2.54;
  var RANGE = /^(\d+(?:\.\d{1,2})?)(?:\s*-\s*(\d+(?:\.\d{1,2})?))?$/;
  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;'); }
  function fmt(n) { return String(Math.round(n * 100) / 100); }
  // "38", "27.5" or "71.1-76.2": each end converted; anything else is left untouched (the server validates).
  function convert(v, from, to) {
    var m = String(v).trim().match(RANGE);
    if (!m || from === to) return v;
    var k = from === 'in' ? FACTOR : 1 / FACTOR;
    return fmt(parseFloat(m[1]) * k) + (m[2] !== undefined ? '-' + fmt(parseFloat(m[2]) * k) : '');
  }
  // Each cell remembers the value as saved/typed (v) and the unit it is in (u). What is shown is always derived from
  // that original, so Inch -> CM -> Inch returns the exact original: no cumulative rounding.
  function disp(c) { return c.u === unit ? c.v : convert(c.v, c.u, unit); }
  state.sizes.forEach(function (s) {
    s.values = (s.values || []).map(function (v) { return { v: v == null ? '' : String(v), u: unit }; });
  });
  function read() {
    state.attributes = Array.from(table.querySelectorAll('thead th[data-a]')).map(function (th) {
      return { name: th.querySelector('.an').value };
    });
    state.sizes = Array.from(table.querySelectorAll('tbody tr')).map(function (tr, j) {
      var prev = (state.sizes[j] || {}).values || [];
      return {
        label: tr.querySelector('.sl').value,
        values: Array.from(tr.querySelectorAll('.sv')).map(function (inp, i) {
          var p = prev[i] || { v: '', u: unit };
          return inp.value === disp(p) ? p : { v: inp.value, u: unit };
        })
      };
    });
  }
  function blank() { return { v: '', u: unit }; }
  unitSel.addEventListener('change', function () {
    var to = unitSel.value;
    read();
    if (!document.getElementById('convertOnSwitch').checked) {
      // keep the numbers, only relabel: freeze what is shown as the new original
      state.sizes.forEach(function (s) { s.values = s.values.map(function (c) { return { v: disp(c), u: to }; }); });
    }
    unit = to;
    render();
  });
  function render() {
    var h = '<thead><tr><th style="min-width:110px">Size</th>';
    state.attributes.forEach(function (a, i) {
      h += '<th data-a="' + i + '"><input class="form-control form-control-sm an mb-1" name="attributes[' + i + '][name]" placeholder="e.g. Chest" value="' + esc(a.name) + '">' +
        '<div class="input-group input-group-sm"><span class="form-control bg-light text-muted unit-label">' + unit + '</span>' +
        '<button type="button" class="btn btn-outline-danger" data-rm-a="' + i + '" title="Remove">&times;</button></div></th>';
    });
    h += '<th></th></tr></thead><tbody>';
    state.sizes.forEach(function (s, j) {
      h += '<tr><td><input class="form-control form-control-sm sl" name="sizes[' + j + '][label]" placeholder="e.g. M" value="' + esc(s.label) + '"></td>';
      state.attributes.forEach(function (a, i) {
        h += '<td><div class="input-group input-group-sm"><input type="text" inputmode="decimal" maxlength="50" placeholder="38 or 71.1-76.2" class="form-control sv" name="sizes[' + j + '][values][' + i + ']" value="' + esc(disp((s.values || [])[i] || blank())) + '"><span class="input-group-text unit-label">' + unit + '</span></div></td>';
      });
      h += '<td><button type="button" class="btn btn-sm btn-outline-danger" data-rm-s="' + j + '" title="Remove">&times;</button></td></tr>';
    });
    table.innerHTML = h + '</tbody>';
  }
  document.getElementById('addAttr').onclick = function () { read(); state.attributes.push({ name: '' }); state.sizes.forEach(function (s) { s.values.push(blank()); }); render(); };
  document.getElementById('addSize').onclick = function () { read(); state.sizes.push({ label: '', values: state.attributes.map(blank) }); render(); };
  table.addEventListener('click', function (e) {
    var a = e.target.closest('[data-rm-a]'), s = e.target.closest('[data-rm-s]');
    if (a && state.attributes.length > 1) { read(); var i = +a.dataset.rmA; state.attributes.splice(i, 1); state.sizes.forEach(function (x) { x.values.splice(i, 1); }); render(); }
    if (s && state.sizes.length > 1) { read(); state.sizes.splice(+s.dataset.rmS, 1); render(); }
  });
  render();
})();
</script>
@endpush
