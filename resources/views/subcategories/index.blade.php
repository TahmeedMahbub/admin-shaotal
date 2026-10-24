@extends('layouts.admin')
@section('title', 'Subcategories')
@section('section', 'Catalog')

@section('content')
<x-admin.page-header title="Subcategories" subtitle="Manage second-level product classification linked to parent categories.">
  <a href="{{ route('subcategories.create') }}" class="btn btn-beige d-flex align-items-center gap-1"><i class="mdi mdi-plus fs-5"></i> Add Subcategory</a>
</x-admin.page-header>

<x-admin.filters :statuses="['active', 'inactive']" placeholder="Search name or slug">
  <select name="category_id" class="form-select" style="max-width: 220px;" onchange="this.form.submit()">
    <option value="">All parent categories</option>
    @foreach ($categories as $id => $name)<option value="{{ $id }}" @selected((string) request('category_id') === (string) $id)>{{ $name }}</option>@endforeach
  </select>
  <select name="section" class="form-select" style="max-width: 220px;" onchange="this.form.submit()">
    <option value="">All sections</option>
    @foreach ($sectionOptions as $sec)<option value="{{ $sec }}" @selected(request('section') === $sec)>{{ $sec }}</option>@endforeach
  </select>
</x-admin.filters>

@if ($canReorder)
  <div class="d-none justify-content-start py-2" id="reorderDoneBar">
    <button type="button" class="btn btn-sm btn-beige" id="btnDoneReorder">Done Reordering</button>
  </div>
@endif

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr>@if ($canReorder)<th style="width:32px;"></th>@endif<th>Subcategory</th><th>Parent Category</th><th>Section</th><th>Slug</th><th>Products</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      @if ($canReorder)
        @forelse (collect($rows->items())->groupBy('category_id') as $categoryId => $items)
          <tbody class="subcategory-sortable" data-category-id="{{ $categoryId }}">
            @foreach ($items as $s)
              @include('subcategories._row', ['s' => $s, 'canReorder' => true])
            @endforeach
          </tbody>
        @empty
          <tbody><tr><td colspan="8" class="text-center text-muted py-4">No subcategories found.</td></tr></tbody>
        @endforelse
      @else
        <tbody>
          @forelse ($rows as $s)
            @include('subcategories._row', ['s' => $s, 'canReorder' => false])
          @empty
            <tr><td colspan="7" class="text-center text-muted py-4">No subcategories found.</td></tr>
          @endforelse
        </tbody>
      @endif
    </table>
  </div>
  @if ($rows->hasPages())<div class="card-footer">{{ $rows->links() }}</div>@endif
</div>

@if ($canReorder)
  <div class="modal fade" id="reorderConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-header border-bottom pb-2">
          <h5 class="modal-title fs-6 fw-bold">Save new subcategory order?</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body py-3 fs-8 text-muted">This changes the order subcategories appear in on the storefront, within each parent category.</div>
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
    var tbodies = Array.prototype.slice.call(document.querySelectorAll('tbody.subcategory-sortable'));
    if (!tbodies.length || !window.Sortable) return;

    var rowIds = function (tb) { return Array.prototype.map.call(tb.querySelectorAll('tr[data-id]'), function (tr) { return tr.dataset.id; }); };
    var applyOrder = function (tb, ids) { ids.forEach(function (id) { tb.appendChild(tb.querySelector('tr[data-id="' + id + '"]')); }); };

    // Sort order is scoped per parent category, so each category's rows live in their own
    // <tbody> with an independent Sortable instance: rows can't be dragged between categories.
    var lastOrders = {};
    tbodies.forEach(function (tb) { lastOrders[tb.dataset.categoryId] = rowIds(tb); });

    var pendingGroups = null, confirmed = false;
    var modalEl = document.getElementById('reorderConfirmModal');
    var modal = new bootstrap.Modal(modalEl);
    var btnDone = document.getElementById('btnDoneReorder');
    var doneBar = document.getElementById('reorderDoneBar');
    var toggleDoneBar = function (show) { doneBar.classList.toggle('d-none', !show); doneBar.classList.toggle('d-flex', show); };

    function anyChanged() {
      return tbodies.some(function (tb) { return rowIds(tb).join(',') !== lastOrders[tb.dataset.categoryId].join(','); });
    }

    function saveOrder(groups) {
      fetch(@json(route('subcategories.reorder')), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' },
        body: JSON.stringify({ groups: groups }),
      }).then(function (r) {
        if (!r.ok) throw new Error();
        groups.forEach(function (g) {
          var tb = document.querySelector('tbody.subcategory-sortable[data-category-id="' + g.category_id + '"]');
          lastOrders[g.category_id] = g.ids;
        });
      }).catch(function () {
        tbodies.forEach(function (tb) { applyOrder(tb, lastOrders[tb.dataset.categoryId]); });
        alert('Could not save the new subcategory order. Reload the page and try again.');
      }).finally(function () {
        toggleDoneBar(false);
      });
    }

    tbodies.forEach(function (tb) {
      Sortable.create(tb, {
        handle: '.drag-handle', animation: 150,
        onEnd: function () { toggleDoneBar(anyChanged()); },
      });
    });

    btnDone.addEventListener('click', function () {
      pendingGroups = tbodies.map(function (tb) { return { category_id: Number(tb.dataset.categoryId), ids: rowIds(tb) }; });
      confirmed = false;
      modal.show();
    });

    document.getElementById('btnConfirmReorder').addEventListener('click', function () {
      confirmed = true;
      modal.hide();
      saveOrder(pendingGroups);
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
      if (!confirmed) {
        tbodies.forEach(function (tb) { applyOrder(tb, lastOrders[tb.dataset.categoryId]); });
        toggleDoneBar(false);
      }
      pendingGroups = null;
    });
  })();
</script>
@endpush
@endif
