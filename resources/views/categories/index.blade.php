@extends('layouts.admin')
@section('title', 'Categories')
@section('section', 'Catalog')

@section('content')
<x-admin.page-header title="Categories" subtitle="Manage main product categories and organization.">
  <a href="{{ route('categories.create') }}" class="btn btn-beige d-flex align-items-center gap-1"><i class="mdi mdi-plus fs-5"></i> Add Category</a>
</x-admin.page-header>

<x-admin.filters :statuses="['active', 'inactive']" placeholder="Search name or slug" />

@if ($canReorder)
  <div class="d-none justify-content-start py-2" id="reorderDoneBar">
    <button type="button" class="btn btn-sm btn-beige" id="btnDoneReorder">Done Reordering</button>
  </div>
@endif

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr>@if ($canReorder)<th style="width:32px;"></th>@endif<th>Category</th><th>Slug</th><th>Subcategories</th><th>Products</th><th>Sort Order</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody id="categoryRows">
        @forelse ($rows as $c)
          <tr data-id="{{ $c->id }}" data-row-href="{{ route('categories.edit', $c) }}">
            @if ($canReorder)<td class="text-muted drag-handle" style="cursor: grab;" title="Drag to reorder"><i class="mdi mdi-drag-vertical fs-5"></i></td>@endif
            <td>
              <div class="d-flex align-items-center gap-2">
                <x-admin.thumb :src="$c->image_url" icon="mdi-shape-outline" />
                <a href="{{ route('categories.edit', $c) }}" class="fw-bold text-dark fs-8">{{ $c->name }}</a>
              </div>
            </td>
            <td><code>{{ $c->slug }}</code></td>
            <td><span class="badge bg-label-secondary">{{ $c->subcategories_count }} Subcategories</span></td>
            <td><span class="fw-bold text-dark">{{ $c->products_count }}</span></td>
            <td class="category-order">{{ $c->sort_order }}</td>
            <td><x-admin.badge :status="$c->status" /></td>
            <td class="text-end"><x-admin.row-actions :edit="route('categories.edit', $c)" :delete="route('categories.destroy', $c)" label="category" /></td>
          </tr>
        @empty
          <tr><td colspan="{{ $canReorder ? 8 : 7 }}" class="text-center text-muted py-4">No categories found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>

@if ($canReorder)
  <div class="modal fade" id="reorderConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-header border-bottom pb-2">
          <h5 class="modal-title fs-6 fw-bold">Save new category order?</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body py-3 fs-8 text-muted">This changes the order categories appear in on the storefront.</div>
        <div class="modal-footer border-top pt-2">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-sm btn-beige" id="btnConfirmReorder">Save Order</button>
        </div>
      </div>
    </div>
  </div>
@endif
@endsection

@if ($canReorder)
@push('scripts')
<script src="{{ asset('assets/vendor/libs/sortablejs/sortable.js') }}"></script>
<script>
  (function () {
    var body = document.getElementById('categoryRows');
    if (!body || !window.Sortable) return;

    var rowIds = function () { return Array.prototype.map.call(body.querySelectorAll('tr[data-id]'), function (tr) { return tr.dataset.id; }); };
    var applyOrder = function (ids) { ids.forEach(function (id) { body.appendChild(body.querySelector('tr[data-id="' + id + '"]')); }); };

    var lastOrder = rowIds();
    var pendingOrder = null, confirmed = false;
    var modalEl = document.getElementById('reorderConfirmModal');
    var modal = new bootstrap.Modal(modalEl);
    var btnDone = document.getElementById('btnDoneReorder');
    var doneBar = document.getElementById('reorderDoneBar');
    var toggleDoneBar = function (show) { doneBar.classList.toggle('d-none', !show); doneBar.classList.toggle('d-flex', show); };

    function saveOrder(ids) {
      fetch(@json(route('categories.reorder')), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' },
        body: JSON.stringify({ ids: ids }),
      }).then(function (r) {
        if (!r.ok) throw new Error();
        body.querySelectorAll('tr[data-id] .category-order').forEach(function (el, i) { el.textContent = i + 1; });
        lastOrder = ids;
      }).catch(function () {
        applyOrder(lastOrder);
        alert('Could not save the new category order. Reload the page and try again.');
      }).finally(function () {
        toggleDoneBar(false);
      });
    }

    Sortable.create(body, {
      handle: '.drag-handle', animation: 150,
      onEnd: function () {
        var ids = rowIds();
        toggleDoneBar(ids.join(',') !== lastOrder.join(','));
      },
    });

    btnDone.addEventListener('click', function () {
      pendingOrder = rowIds();
      confirmed = false;
      modal.show();
    });

    document.getElementById('btnConfirmReorder').addEventListener('click', function () {
      confirmed = true;
      modal.hide();
      saveOrder(pendingOrder);
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
      if (!confirmed) {
        applyOrder(lastOrder); // cancelled, dismissed, or closed without confirming
        toggleDoneBar(false);
      }
      pendingOrder = null;
    });
  })();
</script>
@endpush
@endif
