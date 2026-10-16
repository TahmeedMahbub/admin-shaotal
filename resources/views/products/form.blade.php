@extends('layouts.admin')
@section('title', $product->exists ? 'Edit Product' : 'Create Product')
@section('section', 'Catalog')

@section('content')
@php
  $v = fn ($k, $d = null) => old($k, $product->{$k} ?? $d);
  $spec = $product->specification;
  $hasVariants = (bool) old('has_variants', $product->has_variants);
  $scenario = old('variant_scenario', $product->variant_scenario ?? 'color_size');
  $badgeType = old('badge_type', $product->badge['type'] ?? '');
  $subsByCategory = $subcategories->groupBy('category_id')->map(fn ($g) => $g->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values());
@endphp
<x-admin.page-header :title="$product->exists ? 'Edit Product' : 'Create New Product'" subtitle="Configure product specifications, media, pricing, colors, sizes, and variants.">
  <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Cancel</a>
</x-admin.page-header>

<form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" enctype="multipart/form-data" novalidate>
  @csrf
  @if ($product->exists) @method('PUT') @endif
  <div class="row g-4">
    <div class="col-12 col-lg-8">

      <div class="card mb-4">
        <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Basic Information</h5></div>
        <div class="card-body pt-3">
          <x-admin.field name="name" label="Product Name" :value="$v('name')" required placeholder="e.g. Minimalist Oak Dining Table" />
          <x-admin.field name="slug" label="Slug" :value="$v('slug')" placeholder="Leave blank to generate from the name" />
          <div class="row">
            <x-admin.field col="col-md-4 mb-3" name="category_id" label="Category" type="select" :value="$v('category_id')" :options="$categories" blank="Select Category" required />
            <div class="col-md-4 mb-3">
              <label class="form-label fw-bold fs-8" for="f-subcategory_id">Subcategory</label>
              <select id="f-subcategory_id" name="subcategory_id" class="form-select" data-selected="{{ $v('subcategory_id') }}"></select>
              @error('subcategory_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <x-admin.field col="col-md-4 mb-3" name="brand_id" label="Brand" type="select" :value="$v('brand_id')" :options="$brands" blank="Select Brand" />
          </div>
          <x-admin.field name="short_description" label="Short Description" type="textarea" rows="2" :value="$v('short_description')" placeholder="Brief 1-2 sentence overview shown in product summary..." />
          <x-admin.field name="description" label="Full Description" type="textarea" rows="6" :value="$v('description')" placeholder="Detailed product story, craftsmanship, and specs..." />
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Media</h5><small class="text-muted">Images upload to Cloudinary. Assign gallery images to colors after the colors are saved.</small></div>
        <div class="card-body pt-3">
          <div class="row">
            <div class="col-md-5"><x-admin.image-field name="main_image" label="Main Image" :current="null" /></div>
            <div class="col-md-7"><x-admin.image-field name="new_images" label="Additional Images" :multiple="true" /></div>
          </div>
          @if ($product->images->isNotEmpty())
            <div class="row g-3">
              @foreach ($product->images as $img)
                <div class="col-sm-6 col-xl-4">
                  <div class="border rounded p-2 h-100 {{ $img->is_main ? 'border-primary' : '' }}">
                    <img src="{{ $img->image_url }}" alt="" loading="lazy" class="img-fluid rounded mb-2" style="height: 120px; width: 100%; object-fit: cover;">
                    <input type="text" name="images[{{ $img->id }}][alt_text]" value="{{ old("images.{$img->id}.alt_text", $img->alt_text) }}" class="form-control form-control-sm mb-2" placeholder="Alt text">
                    <div class="d-flex justify-content-between fs-9 mb-1">
                      <label><input type="radio" name="main_existing" value="{{ $img->id }}" @checked((int) old('main_existing', $product->images->firstWhere('is_main', 1)->id ?? 0) === $img->id)> Main</label>
                      <label class="text-danger"><input type="checkbox" name="images[{{ $img->id }}][remove]" value="1"> Remove</label>
                    </div>
                    @if ($product->colors->isNotEmpty())
                      <div class="fs-9 text-muted">Shown for colors (none = all):</div>
                      @foreach ($product->colors as $c)
                        <label class="fs-9 me-2"><input type="checkbox" name="images[{{ $img->id }}][colors][]" value="{{ $c->id }}" @checked($img->colors->contains('id', $c->id))> {{ $c->name }}</label>
                      @endforeach
                    @endif
                  </div>
                </div>
              @endforeach
            </div>
          @endif
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0 fs-6">Variants</h5>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="hasVariants" name="has_variants" value="1" @checked($hasVariants)>
            <label class="form-check-label fs-8" for="hasVariants">This product has variants</label>
          </div>
        </div>
        <fieldset class="card-body pt-3" id="variantBox" @disabled(! $hasVariants)>
          <div class="mb-3">
            <label class="form-label fw-bold fs-8 d-block">Variant Scenario</label>
            @foreach (['color_size' => 'Color + Size', 'color_only' => 'Color-only', 'size_only' => 'Size-only'] as $k => $l)
              <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="variant_scenario" id="sc-{{ $k }}" value="{{ $k }}" @checked($scenario === $k)><label class="form-check-label" for="sc-{{ $k }}">{{ $l }}</label></div>
            @endforeach
            @error('variant_scenario')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
          </div>
          <div id="colorsPanel" class="mb-3">
            <label class="form-label fw-bold fs-8">Colors</label>
            <div id="colorsList" class="d-flex flex-column gap-2 mb-2"></div>
            <button type="button" class="btn btn-sm btn-outline-beige" id="addColor"><i class="mdi mdi-plus"></i> Add color</button>
          </div>
          <div id="sizesPanel" class="mb-3">
            <label class="form-label fw-bold fs-8">Sizes</label>
            <div class="mb-2">@foreach (['XS', 'S', 'M', 'L', 'XL', 'XXL'] as $p)<button type="button" class="btn btn-xs btn-outline-secondary me-1 preset-size" data-size="{{ $p }}">{{ $p }}</button>@endforeach</div>
            <div id="sizesList" class="d-flex flex-wrap gap-2 mb-2"></div>
            <div class="input-group input-group-sm" style="max-width: 340px;"><input type="text" id="customSize" class="form-control" placeholder="Add custom size (e.g. 12-Seater)"><button type="button" class="btn btn-outline-beige" id="addSize">Add</button></div>
          </div>
          @error('variants')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
          <div class="table-responsive">
            <table class="table table-sm align-middle" id="variantTable"></table>
          </div>
          <div class="fs-9 text-muted">Leave a variant price empty to use the product price. Leave SKU empty to auto-generate. Total stock: <strong id="totalStock">0</strong></div>
        </fieldset>
      </div>

      <div class="card mb-4">
        <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Specifications</h5></div>
        <div class="card-body pt-3 row">
          @foreach (['material' => 'Material', 'fabric' => 'Fabric', 'dimensions' => 'Dimensions', 'fit' => 'Fit', 'pattern' => 'Pattern'] as $k => $l)
            <x-admin.field col="col-md-6 mb-3" :name="'spec['.$k.']'" :label="$l" :value="$spec->{$k} ?? null" />
          @endforeach
          <x-admin.field col="col-12 mb-3" name="spec[care_instructions]" label="Care Instructions" type="textarea" rows="2" :value="$spec->care_instructions ?? null" />
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">SEO</h5></div>
        <div class="card-body pt-3">
          <x-admin.field name="seo_title" label="SEO Title" :value="$v('seo_title')" />
          <x-admin.field name="seo_description" label="SEO Description" type="textarea" rows="2" :value="$v('seo_description')" />
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card mb-4">
        <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Status &amp; Visibility</h5></div>
        <div class="card-body pt-3">
          <x-admin.field name="status" label="Status" type="select" :value="$v('status')" :options="['active' => 'Active (Published)', 'draft' => 'Draft', 'inactive' => 'Inactive', 'archived' => 'Archived']" required />
          <x-admin.field name="visibility" label="Visibility" type="select" :value="$v('visibility')" :options="['visible' => 'Visible', 'hidden' => 'Hidden']" required />
          <x-admin.field name="badge_type" label="Badge" type="select" :value="$badgeType" :options="$badges" blank="No badge" />
        </div>
      </div>
      <div class="card mb-4">
        <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Pricing</h5></div>
        <div class="card-body pt-3">
          <x-admin.field name="price" label="Regular Price (A$)" type="number" step="0.01" min="0" :value="$v('price')" required />
          <x-admin.field name="selling_price" label="Sale Price (A$)" type="number" step="0.01" min="0" :value="$v('selling_price')" help="Leave empty for no discount. Must not exceed the regular price." />
        </div>
      </div>
      <div class="card mb-4">
        <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Inventory</h5></div>
        <div class="card-body pt-3">
          <x-admin.field name="sku" label="Base SKU" :value="$v('sku')" help="Used as the SKU for simple products and as the prefix for generated variant SKUs." />
          <div id="simpleStock"><x-admin.field name="stock_qty" label="Stock Quantity" type="number" min="0" :value="$v('stock_qty')" help="Variant products track stock per variant." /></div>
          <x-admin.field name="availability" label="Availability" type="select" :value="$v('availability')" :options="['in_stock' => 'In Stock', 'out_of_stock' => 'Out of Stock', 'preorder' => 'Pre-order', 'coming_soon' => 'Coming Soon']" required />
          <x-admin.field name="preorder_release_date" label="Estimated Dispatch / Release Date" type="date" :value="optional($product->preorder_release_date)->format('Y-m-d') ?? old('preorder_release_date')" />
          <x-admin.field name="low_stock_threshold" label="Low Stock Threshold" type="number" min="0" :value="$v('low_stock_threshold')" />
        </div>
      </div>
      <div class="card mb-4">
        <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6">Size Guide</h5></div>
        <div class="card-body pt-3">
          <x-admin.field name="size_guide_id" label="Assigned Catalog Guide" type="select" :value="$v('size_guide_id')" :options="$sizeGuides" blank="-- No Size Guide Assigned --" />
          <a href="{{ route('size-guides.index') }}" class="fs-9">Manage size guides</a>
        </div>
      </div>
      <button type="submit" class="btn btn-beige w-100 mb-4">Save Product</button>
    </div>
  </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
  var state = @json($state);
  var subs = @json($subsByCategory);
  var $ = function (id) { return document.getElementById(id); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;'); };

  // ---- dependent subcategory select
  var cat = $('f-category_id'), sub = $('f-subcategory_id');
  function fillSubs() {
    var sel = sub.dataset.selected || sub.value, list = subs[cat.value] || [];
    sub.innerHTML = '<option value="">Select Subcategory</option>' + list.map(function (s) { return '<option value="' + s.id + '"' + (String(s.id) === String(sel) ? ' selected' : '') + '>' + esc(s.name) + '</option>'; }).join('');
    sub.dataset.selected = '';
  }
  cat.addEventListener('change', fillSubs); fillSubs();

  // ---- variants editor
  var has = $('hasVariants'), box = $('variantBox');
  var scenario = function () { return document.querySelector('input[name=variant_scenario]:checked').value; };
  var vmap = {}; // "color|size" -> variant data
  state.variants.forEach(function (v) {
    var c = v.color === '' || v.color == null ? '' : (state.colors[v.color] || {}).name;
    var s = v.size === '' || v.size == null ? '' : (state.sizes[v.size] || {}).size_label;
    vmap[(c || '') + '|' + (s || '')] = v;
  });

  function readAll() {
    state.colors = Array.from(document.querySelectorAll('#colorsList .crow')).map(function (r) {
      return { id: r.dataset.id || '', name: r.querySelector('.cn').value, hex_code: r.querySelector('.ch').value };
    });
    state.sizes = Array.from(document.querySelectorAll('#sizesList .srow')).map(function (r) {
      return { id: r.dataset.id || '', size_label: r.querySelector('.sn').value };
    });
    Array.from(document.querySelectorAll('#variantTable tbody tr')).forEach(function (tr) {
      vmap[tr.dataset.key] = {
        id: tr.dataset.id || '', sku: tr.querySelector('.vsku').value, price_override: tr.querySelector('.vp').value,
        selling_price_override: tr.querySelector('.vs').value, stock_qty: tr.querySelector('.vst').value, is_active: tr.querySelector('.va').checked ? 1 : 0
      };
    });
  }
  function renderLists() {
    $('colorsList').innerHTML = state.colors.map(function (c, i) {
      return '<div class="crow input-group input-group-sm" data-id="' + esc(c.id) + '"><input type="hidden" name="colors[' + i + '][id]" value="' + esc(c.id) + '">' +
        '<input type="color" class="form-control form-control-color ch" style="max-width:48px" name="colors[' + i + '][hex_code]" value="' + esc(c.hex_code || '#cccccc') + '">' +
        '<input type="text" class="form-control cn" name="colors[' + i + '][name]" placeholder="Color name" value="' + esc(c.name) + '">' +
        '<button type="button" class="btn btn-outline-danger" data-rm-c="' + i + '">&times;</button></div>';
    }).join('');
    $('sizesList').innerHTML = state.sizes.map(function (s, i) {
      return '<div class="srow input-group input-group-sm" style="width:auto" data-id="' + esc(s.id) + '"><input type="hidden" name="sizes[' + i + '][id]" value="' + esc(s.id) + '">' +
        '<input type="text" class="form-control sn" style="width:110px" name="sizes[' + i + '][size_label]" value="' + esc(s.size_label) + '">' +
        '<button type="button" class="btn btn-outline-danger" data-rm-s="' + i + '">&times;</button></div>';
    }).join('');
  }
  function renderMatrix() {
    var sc = scenario(), rows = [];
    var cs = sc === 'size_only' ? [null] : state.colors.map(function (c, i) { return i; });
    var ss = sc === 'color_only' ? [null] : state.sizes.map(function (s, i) { return i; });
    cs.forEach(function (ci) { ss.forEach(function (si) {
      var cn = ci == null ? '' : state.colors[ci].name, sn = si == null ? '' : state.sizes[si].size_label;
      if ((ci != null && !cn) || (si != null && !sn)) return;
      var key = cn + '|' + sn, d = vmap[key] || { id: '', sku: '', price_override: '', selling_price_override: '', stock_qty: 0, is_active: 1 };
      var n = rows.length;
      rows.push('<tr data-key="' + esc(key) + '" data-id="' + esc(d.id || '') + '">' +
        '<td class="fs-8">' + (ci != null ? '<span class="badge" style="background:' + esc(state.colors[ci].hex_code) + ';color:#fff">' + esc(cn) + '</span> ' : '') + esc(sn) +
        '<input type="hidden" name="variants[' + n + '][id]" value="' + esc(d.id || '') + '"><input type="hidden" name="variants[' + n + '][color]" value="' + (ci == null ? '' : ci) + '"><input type="hidden" name="variants[' + n + '][size]" value="' + (si == null ? '' : si) + '"></td>' +
        '<td><input class="form-control form-control-sm vsku" style="min-width:130px" name="variants[' + n + '][sku]" placeholder="auto" value="' + esc(d.sku) + '"></td>' +
        '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm vp" style="width:90px" name="variants[' + n + '][price_override]" value="' + esc(d.price_override) + '"></td>' +
        '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm vs" style="width:90px" name="variants[' + n + '][selling_price_override]" value="' + esc(d.selling_price_override) + '"></td>' +
        '<td><input type="number" min="0" class="form-control form-control-sm vst" style="width:80px" name="variants[' + n + '][stock_qty]" value="' + esc(d.stock_qty) + '"></td>' +
        '<td><input type="hidden" name="variants[' + n + '][is_active]" value="0"><input type="checkbox" class="form-check-input va" name="variants[' + n + '][is_active]" value="1"' + (+d.is_active ? ' checked' : '') + '></td></tr>');
    }); });
    $('variantTable').innerHTML = '<thead><tr><th>Variant</th><th>SKU</th><th>Price (A$)</th><th>Sale (A$)</th><th>Stock</th><th>Active</th></tr></thead><tbody>' + rows.join('') + '</tbody>';
    updatePanels(); totals();
  }
  function totals() { $('totalStock').textContent = Array.from(document.querySelectorAll('.vst')).reduce(function (a, i) { return a + (+i.value || 0); }, 0); }
  function updatePanels() {
    var sc = scenario();
    $('colorsPanel').style.display = sc === 'size_only' ? 'none' : '';
    $('sizesPanel').style.display = sc === 'color_only' ? 'none' : '';
    $('simpleStock').style.display = has.checked ? 'none' : '';
    box.disabled = !has.checked;
    var st = $('f-stock_qty'); if (st) st.disabled = has.checked;
  }
  function refresh() { readAll(); renderLists(); renderMatrix(); }
  $('addColor').onclick = function () { readAll(); state.colors.push({ id: '', name: '', hex_code: '#c8a27a' }); renderLists(); renderMatrix(); };
  function addSize(label) { readAll(); label = (label || '').trim(); if (!label || state.sizes.some(function (s) { return s.size_label.toLowerCase() === label.toLowerCase(); })) return; state.sizes.push({ id: '', size_label: label }); renderLists(); renderMatrix(); }
  $('addSize').onclick = function () { addSize($('customSize').value); $('customSize').value = ''; };
  document.querySelectorAll('.preset-size').forEach(function (b) { b.onclick = function () { addSize(b.dataset.size); }; });
  document.addEventListener('click', function (e) {
    var c = e.target.closest('[data-rm-c]'), s = e.target.closest('[data-rm-s]');
    if (c) { readAll(); state.colors.splice(+c.dataset.rmC, 1); renderLists(); renderMatrix(); }
    if (s) { readAll(); state.sizes.splice(+s.dataset.rmS, 1); renderLists(); renderMatrix(); }
  });
  $('variantBox').addEventListener('input', function (e) { if (e.target.closest('#colorsList,#sizesList')) { readAll(); renderMatrix(); } else totals(); });
  document.querySelectorAll('input[name=variant_scenario]').forEach(function (r) { r.onchange = refresh; });
  has.onchange = updatePanels;
  renderLists(); renderMatrix();
})();
</script>
@endpush
