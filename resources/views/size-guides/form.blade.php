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
  <p class="fs-9 text-muted">Columns are measurement attributes; rows are sizes. Every cell needs a number.</p>
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
  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;'); }
  function read() {
    state.attributes = Array.from(table.querySelectorAll('thead th[data-a]')).map(function (th) {
      return { name: th.querySelector('.an').value, unit: th.querySelector('.au').value };
    });
    state.sizes = Array.from(table.querySelectorAll('tbody tr')).map(function (tr) {
      return { label: tr.querySelector('.sl').value, values: Array.from(tr.querySelectorAll('.sv')).map(function (i) { return i.value; }) };
    });
  }
  function render() {
    var h = '<thead><tr><th style="min-width:110px">Size</th>';
    state.attributes.forEach(function (a, i) {
      h += '<th data-a="' + i + '"><input class="form-control form-control-sm an mb-1" name="attributes[' + i + '][name]" placeholder="e.g. Chest" value="' + esc(a.name) + '">' +
        '<div class="input-group input-group-sm"><input class="form-control au" name="attributes[' + i + '][unit]" value="' + esc(a.unit || 'cm') + '">' +
        '<button type="button" class="btn btn-outline-danger" data-rm-a="' + i + '" title="Remove">&times;</button></div></th>';
    });
    h += '<th></th></tr></thead><tbody>';
    state.sizes.forEach(function (s, j) {
      h += '<tr><td><input class="form-control form-control-sm sl" name="sizes[' + j + '][label]" placeholder="e.g. M" value="' + esc(s.label) + '"></td>';
      state.attributes.forEach(function (a, i) {
        h += '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm sv" name="sizes[' + j + '][values][' + i + ']" value="' + esc((s.values || [])[i]) + '"></td>';
      });
      h += '<td><button type="button" class="btn btn-sm btn-outline-danger" data-rm-s="' + j + '" title="Remove">&times;</button></td></tr>';
    });
    table.innerHTML = h + '</tbody>';
  }
  document.getElementById('addAttr').onclick = function () { read(); state.attributes.push({ name: '', unit: 'cm' }); state.sizes.forEach(function (s) { s.values.push(''); }); render(); };
  document.getElementById('addSize').onclick = function () { read(); state.sizes.push({ label: '', values: state.attributes.map(function () { return ''; }) }); render(); };
  table.addEventListener('click', function (e) {
    var a = e.target.closest('[data-rm-a]'), s = e.target.closest('[data-rm-s]');
    if (a && state.attributes.length > 1) { read(); var i = +a.dataset.rmA; state.attributes.splice(i, 1); state.sizes.forEach(function (x) { x.values.splice(i, 1); }); render(); }
    if (s && state.sizes.length > 1) { read(); state.sizes.splice(+s.dataset.rmS, 1); render(); }
  });
  render();
})();
</script>
@endpush
