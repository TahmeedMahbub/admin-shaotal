<tr data-id="{{ $s->id }}" data-row-href="{{ route('subcategories.edit', $s) }}">
  @if ($canReorder)<td class="text-muted drag-handle" style="cursor: grab;" title="Drag to reorder"><i class="mdi mdi-drag-vertical fs-5"></i></td>@endif
  <td>
    <div class="d-flex align-items-center gap-2">
      <x-admin.thumb :src="$s->image_url" icon="mdi-shape-outline" />
      <a href="{{ route('subcategories.edit', $s) }}" class="fw-bold text-dark fs-8">{{ $s->name }}</a>
    </div>
  </td>
  <td><span class="badge bg-label-secondary">{{ $s->category->name ?? '—' }}</span></td>
  <td><code>{{ $s->slug }}</code></td>
  <td><span class="fw-bold text-dark">{{ $s->products_count }}</span></td>
  <td class="subcategory-order">{{ $s->sort_order }}</td>
  <td><x-admin.badge :status="$s->status" /></td>
  <td class="text-end"><x-admin.row-actions :edit="route('subcategories.edit', $s)" :delete="route('subcategories.destroy', $s)" label="subcategory" /></td>
</tr>
