@extends('layouts.admin')
@section('title', 'Banners')
@section('section', 'Content')

@section('content')
<x-admin.page-header title="Promotional Store Banners" subtitle="Manage homepage hero slides and promo banners.">
  <a href="{{ route('banners.create') }}" class="btn btn-beige d-flex align-items-center gap-1"><i class="mdi mdi-plus fs-5"></i> Create Banner</a>
</x-admin.page-header>

<x-admin.filters :statuses="['active', 'inactive']" placeholder="Search banner title..." />

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr>@if ($canReorder)<th style="width:32px;"></th>@endif<th>Banner Preview</th><th>Banner Title</th><th>Linked Destination</th><th>Order</th><th>Schedule</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody id="bannerRows">
        @forelse ($rows as $b)
          <tr data-id="{{ $b->id }}">
            @if ($canReorder)<td class="text-muted drag-handle" style="cursor: grab;" title="Drag to reorder"><i class="mdi mdi-drag-vertical fs-5"></i></td>@endif
            <td><img src="{{ $b->image_url }}" alt="" loading="lazy" class="rounded border" style="width: 120px; height: 60px; object-fit: cover;"></td>
            <td><a href="{{ route('banners.show', $b) }}" class="fw-bold text-dark fs-8">{{ $b->title ?: '(untitled)' }}</a><div><small class="text-muted">{{ $b->subtitle }}</small></div></td>
            <td>@if ($b->button_url)<code class="fs-9">{{ \Illuminate\Support\Str::limit($b->button_url, 38) }}</code>@else — @endif</td>
            <td class="banner-order">{{ $b->sort_order }}</td>
            <td><small>{{ $b->starts_at?->format('M d, Y') ?? 'Any time' }} → {{ $b->ends_at?->format('M d, Y') ?? 'No end' }}</small></td>
            <td><x-admin.badge :status="$b->status" /></td>
            <td class="text-end"><x-admin.row-actions :show="route('banners.show', $b)" :edit="route('banners.edit', $b)" :delete="route('banners.destroy', $b)" label="banner" /></td>
          </tr>
        @empty
          <tr><td colspan="{{ $canReorder ? 8 : 7 }}" class="text-center text-muted py-4">No banners found.</td></tr>
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
          <h5 class="modal-title fs-6 fw-bold">Save new banner order?</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body py-3 fs-8 text-muted">This changes the order banners appear in on the storefront.</div>
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
    var body = document.getElementById('bannerRows');
    if (!body || !window.Sortable) return;

    var rowIds = function () { return Array.prototype.map.call(body.querySelectorAll('tr[data-id]'), function (tr) { return tr.dataset.id; }); };
    var applyOrder = function (ids) { ids.forEach(function (id) { body.appendChild(body.querySelector('tr[data-id="' + id + '"]')); }); };

    var lastOrder = rowIds();
    var pendingOrder = null, confirmed = false;
    var modalEl = document.getElementById('reorderConfirmModal');
    var modal = new bootstrap.Modal(modalEl);

    function saveOrder(ids) {
      fetch(@json(route('banners.reorder')), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' },
        body: JSON.stringify({ ids: ids }),
      }).then(function (r) {
        if (!r.ok) throw new Error();
        body.querySelectorAll('tr[data-id] .banner-order').forEach(function (el, i) { el.textContent = i + 1; });
        lastOrder = ids;
      }).catch(function () {
        applyOrder(lastOrder);
        alert('Could not save the new banner order. Reload the page and try again.');
      });
    }

    Sortable.create(body, {
      handle: '.drag-handle', animation: 150,
      onEnd: function () {
        var ids = rowIds();
        if (ids.join(',') === lastOrder.join(',')) return; // dropped back in the same place
        pendingOrder = ids;
        confirmed = false;
        modal.show();
      },
    });

    document.getElementById('btnConfirmReorder').addEventListener('click', function () {
      confirmed = true;
      modal.hide();
      saveOrder(pendingOrder);
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
      if (!confirmed) applyOrder(lastOrder); // cancelled, dismissed, or closed without confirming
      pendingOrder = null;
    });
  })();
</script>
@endpush
@endif
