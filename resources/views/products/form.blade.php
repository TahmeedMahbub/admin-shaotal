@extends('layouts.admin')
@section('title', $product->exists ? 'Edit Product' : 'Create Product')
@section('section', 'Catalog')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/typography.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/editor.css') }}" />
<style>
  .upload-overlay { position: absolute; inset: 0; border-radius: 6px; background: rgba(255,255,255,.82); display: flex; flex-direction: column; align-items: center; justify-content: center; font-size: 11px; padding: 4px; text-align: center; }
  .upload-overlay .progress { width: 90%; height: 5px; }
  .btn-xs { padding: .15rem .45rem; font-size: .75rem; }
  .gallery-thumb-card .make-main { position: absolute; bottom: 2px; left: 2px; right: 2px; font-size: 9px; padding: 0 2px; line-height: 14px; opacity: 0; }
  .gallery-thumb-card:hover .make-main { opacity: 1; }
  .variant-disabled { opacity: .55; }
</style>
@endpush

@section('content')
@php
  $v = fn ($k, $d = null) => old($k, $product->{$k} ?? $d);
  $spec = $product->specification;
  $hasVariants = (bool) old('has_variants', $product->has_variants);
  $scenario = old('variant_scenario', $product->variant_scenario ?? 'color_size');
  $badgeType = old('badge_type', $product->badge['type'] ?? '');
  $subsByCategory = $subcategories->groupBy('category_id')->map(fn ($g) => $g->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values());
  $saleOn = old('selling_price', $product->selling_price) !== null && old('selling_price', $product->selling_price) !== '';
  $draftCta = $product->exists && $product->status === 'draft';
@endphp

<form method="POST" id="productForm" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" novalidate>
  @csrf
  @if ($product->exists) @method('PUT') @endif
  <div id="dynamicFields"></div>

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
      <h4 class="fw-bold mb-1" style="color: var(--beige-text-dark);">{{ $product->exists ? 'Edit Product: '.$product->name : 'Create New Product' }}</h4>
      <p class="text-muted mb-0 fs-7">
        @if ($product->exists)SKU: <span class="fw-bold text-dark">{{ $product->sku ?: '—' }}</span> &bull; Status: <x-admin.badge :status="$product->status" />
        @else Configure product details, media, pricing, colors, sizes, and variants. @endif
      </p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Cancel</a>
      <button type="submit" name="save_draft" value="1" class="btn btn-outline-beige" title="Saves as a draft; hidden from the storefront"><i class="mdi mdi-file-document-edit-outline me-1"></i> Save Draft</button>
      <button type="submit" class="btn btn-beige js-save"><i class="mdi mdi-check me-1"></i> {{ $product->exists ? 'Update Product' : 'Save Product' }}</button>
    </div>
  </div>

  @isset($duplicateOf)
  <div class="alert alert-info">Duplicated from <strong>{{ $duplicateOf->name }}</strong>. Nothing is saved yet: review the name, slug and SKU, adjust anything else, then save. Variant SKUs are generated automatically if left blank.</div>
  @endisset

  <div class="row g-4">
    <div class="col-12 col-lg-8">

      {{-- 1. Basic information --}}
      <div class="card mb-4">
        <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6"><i class="mdi mdi-information-outline me-2 text-primary"></i> Basic Information</h5></div>
        <div class="card-body pt-3">
          <x-admin.field name="name" label="Product Name" :value="$v('name')" required placeholder="e.g. Minimalist Oak Dining Table" />
          <x-admin.field name="slug" label="Slug" :value="$v('slug')" placeholder="auto-generated-from-name" help="Generated from the name until you edit it; you can change it any time." />
          <div class="row g-3 mb-3">
            <x-admin.field col="col-12 col-md-4" name="category_id" label="Category" type="select" :value="$v('category_id')" :options="$categories" blank="Select Category" required />
            <div class="col-12 col-md-4">
              <label class="form-label fw-bold fs-8" for="f-subcategory_id">Subcategory</label>
              <select id="f-subcategory_id" name="subcategory_id" class="form-select {{ $errors->has('subcategory_id') ? 'is-invalid' : '' }}" data-selected="{{ $v('subcategory_id') }}"></select>
              @error('subcategory_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <x-admin.field col="col-12 col-md-4" name="brand_id" label="Brand" type="select" :value="$v('brand_id')" :options="$brands" blank="Select Brand" />
          </div>
          <x-admin.field name="short_description" label="Short Description" type="textarea" rows="2" :value="$v('short_description')" placeholder="Brief 1-2 sentence overview shown in product summary..." />
          <div class="mb-3">
            <label class="form-label fw-bold fs-8">Full Description</label>
            <div id="descEditor" class="bg-white" style="min-height:180px"></div>
            <input type="hidden" name="description" id="f-description" value="{{ old('description', $v('description')) }}">
            @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
          </div>
        </div>
      </div>

      {{-- 2. Product media --}}
      <div class="card mb-4" id="cardProductMedia">
        <div class="card-header border-bottom py-3">
          <h5 class="card-title mb-0 fs-6"><i class="mdi mdi-image-multiple-outline me-2 text-primary"></i> Product Media</h5>
          <small class="text-muted fs-9">Images upload to Cloudinary as soon as you pick them. They can be assigned to color variants below.</small>
        </div>
        <div class="card-body pt-3">
          @error('images')<div class="alert alert-danger fs-8 py-2">{{ $message }}</div>@enderror
          <div class="mb-4">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <label class="form-label fw-bold fs-8 mb-0">Main Image</label><small class="text-muted fs-9">Primary storefront cover photo</small>
            </div>
            <div id="mainImageContainer"></div>
            <input type="file" id="mainImageFileInput" accept="image/jpeg,image/png,image/webp,image/gif" class="d-none">
          </div>
          <div>
            <div class="d-flex align-items-center justify-content-between mb-2">
              <div><label class="form-label fw-bold fs-8 mb-0">Additional Images</label><small class="text-muted fs-9 d-block">Secondary gallery shots, details, and color angles. Hover a photo to make it the main image.</small></div>
              <span class="badge bg-label-secondary fs-9" id="additionalImagesCountBadge">0 images</span>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center mb-1" id="additionalImagesContainer"></div>
            <input type="file" id="additionalImagesFileInput" multiple accept="image/jpeg,image/png,image/webp,image/gif" class="d-none">
          </div>
          <div id="uploadErrors" class="mt-2"></div>
        </div>
      </div>

      {{-- 3. Colors & swatches --}}
      <div class="card mb-4 variant-only color-only-section" id="cardColors">
        <div class="card-header border-bottom py-3 d-flex align-items-center justify-content-between">
          <div><h5 class="card-title mb-0 fs-6"><i class="mdi mdi-palette-outline me-2 text-primary"></i> Colors &amp; Swatches</h5>
            <small class="text-muted fs-9">Assign color swatches and multiple gallery images per color</small></div>
          <button type="button" class="btn btn-sm btn-outline-beige" id="btnAddColor"><i class="mdi mdi-plus me-1"></i> Add Color</button>
        </div>
        <div class="card-body pt-3">
          <p class="fs-8 text-muted mb-3">Click thumbnails to assign or unassign images to a color. A color with no selection uses the general product images.</p>
          <div id="colorsContainer"></div>
        </div>
      </div>

      {{-- 4. Sizes --}}
      <div class="card mb-4 variant-only size-only-section" id="cardSizes">
        <div class="card-header border-bottom py-3 d-flex align-items-center justify-content-between">
          <h5 class="card-title mb-0 fs-6"><i class="mdi mdi-ruler-square me-2 text-primary"></i> Size Options</h5>
          <a href="{{ route('size-guides.index') }}" target="_blank" class="btn btn-xs btn-outline-beige"><i class="mdi mdi-open-in-new me-1"></i> Manage Guides</a>
        </div>
        <div class="card-body pt-3">
          <div class="row g-3 mb-3">
            <div class="col-12 col-md-8">
              <label class="form-label fw-bold fs-8" for="productSizeGuideSelect">Size Guide <span class="text-muted fw-normal">(fills the sizes below)</span></label>
              <div class="input-group">
                <span class="input-group-text bg-white"><i class="mdi mdi-format-list-bulleted-type text-primary"></i></span>
                <select class="form-select" id="productSizeGuideSelect" name="size_guide_id"></select>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnClearSizeGuide">Clear</button>
              </div>
              @error('size_guide_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
          </div>
          <p class="fs-8 text-muted mb-2">Select the active sizes for this product, or add a custom one.</p>
          <div class="d-flex flex-wrap gap-2 mb-3" id="sizesContainer"></div>
          <div class="d-flex align-items-center gap-2 pt-2 border-top">
            <input type="text" class="form-control form-control-sm" id="customSizeInput" placeholder="Add custom size (e.g. 12-Seater, XXL)" style="max-width: 260px;">
            <button type="button" class="btn btn-sm btn-outline-beige" id="btnAddCustomSize"><i class="mdi mdi-plus me-1"></i> Add Size</button>
          </div>
        </div>
      </div>

      {{-- 5. Variants matrix --}}
      <div class="card mb-4 variant-only" id="cardVariants">
        <div class="card-header border-bottom py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div class="d-flex align-items-center gap-2">
            <h5 class="card-title mb-0 fs-6"><i class="mdi mdi-grid me-2 text-primary"></i> Product Variants Matrix</h5>
            <span class="badge bg-label-primary fs-8" id="variantScenarioBadge">Scenario: Color + Size</span>
            <span class="badge bg-label-info fs-8">Total Stock: <strong id="totalVariantStockCount">0</strong> units</span>
          </div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="btn btn-xs btn-outline-beige" id="btnEnableAllVariants"><i class="mdi mdi-check-all me-1"></i> Enable All</button>
            <button type="button" class="btn btn-xs btn-outline-secondary" id="btnDisableAllVariants"><i class="mdi mdi-close-circle-outline me-1"></i> Disable All</button>
          </div>
        </div>
        @error('variants')<div class="alert alert-danger fs-8 py-2 m-3 mb-0">{{ $message }}</div>@enderror
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table align-middle mb-0 fs-8" id="variantTable"></table>
          </div>
        </div>
      </div>

      {{-- 6. Attributes --}}
      <div class="card mb-4">
        <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6"><i class="mdi mdi-tune-vertical me-2 text-primary"></i> Product Attributes &amp; Details</h5></div>
        <div class="card-body pt-3 row g-3">
          @foreach (['material' => 'Material', 'fabric' => 'Fabric', 'dimensions' => 'Dimensions', 'fit' => 'Fit', 'pattern' => 'Pattern'] as $k => $l)
            <x-admin.field col="col-12 col-md-6" :name="'spec['.$k.']'" :label="$l" :value="$spec->{$k} ?? null" />
          @endforeach
          <x-admin.field col="col-12" name="spec[care_instructions]" label="Care Instructions" type="textarea" rows="2" :value="$spec->care_instructions ?? null" />
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6"><i class="mdi mdi-search-web me-2 text-primary"></i> SEO</h5></div>
        <div class="card-body pt-3">
          <x-admin.field name="seo_title" label="SEO Title" :value="$v('seo_title')" />
          <x-admin.field name="seo_description" label="SEO Description" type="textarea" rows="2" :value="$v('seo_description')" />
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card mb-4">
        <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6"><i class="mdi mdi-toggle-switch-outline me-2 text-primary"></i> Feature Switches</h5></div>
        <div class="card-body pt-3 d-flex flex-column gap-3">
          <div>
            <div class="d-flex align-items-center justify-content-between">
              <div class="fw-bold fs-8 text-dark">Has Variants</div>
              <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" id="toggleVariants" name="has_variants" value="1" @checked($hasVariants)></div>
            </div>
            <div id="variantTypeSelectorContainer" class="mt-2 pt-2 border-top">
              <label class="form-label fw-bold fs-8 mb-2 d-block">Variant Scenario</label>
              <div class="btn-group w-100" role="group">
                @foreach (['color_size' => 'Color + Size', 'color_only' => 'Color-only', 'size_only' => 'Size-only'] as $k => $l)
                  <input type="radio" class="btn-check" name="variant_scenario" id="mode-{{ $k }}" value="{{ $k }}" @checked($scenario === $k)>
                  <label class="btn btn-sm btn-outline-beige fs-9 py-1 px-2" for="mode-{{ $k }}">{{ $l }}</label>
                @endforeach
              </div>
              @error('variant_scenario')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="d-flex align-items-center justify-content-between">
            <div class="fw-bold fs-8 text-dark">On Sale / Offer</div>
            <div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" id="toggleSale" @checked($saleOn)></div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6"><i class="mdi mdi-currency-usd me-2 text-primary"></i> Base Pricing</h5></div>
        <div class="card-body pt-3">
          <div class="alert alert-secondary py-2 px-3 fs-9 mb-3" style="background-color: var(--beige-bg-alt); border: 1px solid var(--beige-border-subtle);"><i class="mdi mdi-information-outline text-primary me-1"></i> Variants inherit these prices unless overridden in the matrix.</div>
          <x-admin.field name="price" label="Regular Price (A$)" type="number" step="0.01" min="0" :value="$v('price')" required />
          <div class="sale-price-field"><x-admin.field name="selling_price" label="Sale / Offer Price (A$)" type="number" step="0.01" min="0" :value="$v('selling_price')" help="Must not exceed the regular price." /></div>
        </div>
      </div>

      <div class="card mb-4" id="cardStock">
        <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6"><i class="mdi mdi-clipboard-text-outline me-2 text-primary"></i> Inventory &amp; SKU</h5></div>
        <div class="card-body pt-3">
          <x-admin.field name="sku" label="Base SKU" :value="$v('sku')" help="Prefix for generated variant SKUs; the product SKU when there are no variants." />
          <div id="variantStockSummary" class="p-3 rounded mb-3" style="background-color: var(--beige-bg-alt); border: 1px solid var(--beige-border-subtle);">
            <div class="fs-8 fw-semibold text-dark"><i class="mdi mdi-layers-outline text-primary me-1"></i> Variant Inventory</div>
            <div class="fs-7 fw-bold text-dark my-1">Total Stock: <span id="sidebarTotalStock">0</span> units</div>
            <small class="text-muted fs-9">Calculated from the matrix.</small>
          </div>
          <div id="simpleStock"><x-admin.field name="stock_qty" label="Base Stock Quantity" type="number" min="0" :value="$v('stock_qty')" help="Product-level inventory for products without variants." /></div>
          <x-admin.field name="availability" label="Stock Availability Status" type="select" :value="$v('availability')" :options="['in_stock' => 'In Stock', 'out_of_stock' => 'Out of Stock', 'preorder' => 'Pre-order', 'coming_soon' => 'Coming Soon']" required />
          <x-admin.field name="preorder_release_date" label="Estimated Dispatch / Release Date" type="date" :value="optional($product->preorder_release_date)->format('Y-m-d') ?? old('preorder_release_date')" />
          <x-admin.field name="low_stock_threshold" label="Low Stock Threshold" type="number" min="0" :value="$v('low_stock_threshold')" />
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header border-bottom py-3"><h5 class="card-title mb-0 fs-6"><i class="mdi mdi-check-circle-outline me-2 text-primary"></i> Publishing Status</h5></div>
        <div class="card-body pt-3">
          <x-admin.field name="status" label="Status" type="select" :value="$v('status')" :options="['active' => 'Active (Published)', 'draft' => 'Draft (Hidden)', 'inactive' => 'Inactive', 'archived' => 'Archived']" required />
          <x-admin.field name="visibility" label="Visibility" type="select" :value="$v('visibility')" :options="['visible' => 'Visible', 'hidden' => 'Hidden']" required />
          <x-admin.field name="badge_type" label="Badge" type="select" :value="$badgeType" :options="$badges" blank="No badge" />
          <button type="submit" class="btn btn-beige w-100 mb-2 js-save"><i class="mdi mdi-content-save me-1"></i> {{ $product->exists ? 'Update Product' : 'Save Product' }}</button>
          <button type="submit" name="save_draft" value="1" class="btn btn-outline-beige w-100"><i class="mdi mdi-file-document-edit-outline me-1"></i> Save Draft</button>
          <div class="fs-9 text-muted mt-2">Drafts are hidden from the storefront until the status is set to Active and visibility to Visible.</div>
        </div>
      </div>
    </div>
  </div>
</form>
@endsection

@push('scripts')
<script>
  window.PRODUCT_FORM = {
    state: @json($state),
    subcategories: @json($subsByCategory),
    scenario: @json($scenario),
    saleOn: @json($saleOn),
    guideId: @json((string) $v('size_guide_id')),
    uploadUrl: @json(route('products.upload-image')),
    csrf: @json(csrf_token()),
    maxMb: {{ config('cloudinary.max_kb') / 1024 }},
  };
</script>
<script src="{{ asset('assets/vendor/libs/quill/quill.js') }}"></script>
<script>
  (function () {
    var input = document.getElementById('f-description');
    var quill = new Quill('#descEditor', {
      theme: 'snow',
      placeholder: 'Detailed product story, craftsmanship, and specs...',
      modules: { toolbar: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['link', 'blockquote'], ['clean']] }
    });
    if (input.value) quill.clipboard.dangerouslyPasteHTML(input.value);
    document.getElementById('productForm').addEventListener('submit', function () {
      input.value = quill.getText().trim() === '' ? '' : quill.root.innerHTML;
    });
  })();
</script>
<script src="{{ asset('assets/js/product-form.js') }}?v={{ filemtime(public_path('assets/js/product-form.js')) }}"></script>
@endpush
