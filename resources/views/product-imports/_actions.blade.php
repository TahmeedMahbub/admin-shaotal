@php
  $cls = $compact ? '' : 'w-100 mb-2';
  $blocked = (bool) $importDuplicate;
@endphp
@if ($import->status === 'published')
  <button type="submit" name="import_action" value="update" class="btn btn-beige {{ $cls }}" @disabled($blocked)><i class="mdi mdi-check me-1"></i> Update Product</button>
@else
  @unless ($importProduct)
    <button type="submit" name="import_action" value="progress" class="btn btn-outline-secondary {{ $cls }}" title="Keeps your edits on this import only; nothing is created in the catalogue"><i class="mdi mdi-content-save-outline me-1"></i> Save Progress</button>
    <button type="submit" name="import_action" value="approve" class="btn btn-outline-beige {{ $cls }}" @disabled($blocked) title="Runs every publish check and marks this item Ready"><i class="mdi mdi-check-circle-outline me-1"></i> Approve</button>
  @endunless
  <button type="submit" name="import_action" value="draft" class="btn btn-outline-beige {{ $cls }}" @disabled($blocked) title="Creates (or updates) the product as a hidden draft"><i class="mdi mdi-file-document-edit-outline me-1"></i> Save Draft</button>
  <button type="submit" name="import_action" value="publish" class="btn btn-beige {{ $cls }}" @disabled($blocked) onclick="return confirm('Publish this product to the live storefront now?')"><i class="mdi mdi-rocket-launch-outline me-1"></i> Publish</button>
@endif
