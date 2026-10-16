@props(['show' => null, 'edit' => null, 'delete' => null, 'label' => 'item'])
<div class="d-flex align-items-center justify-content-end gap-1">
  @if ($show)<a href="{{ $show }}" class="btn btn-sm btn-icon btn-outline-beige" title="View"><i class="mdi mdi-eye-outline fs-6"></i></a>@endif
  @if ($edit)<a href="{{ $edit }}" class="btn btn-sm btn-icon btn-outline-beige" title="Edit"><i class="mdi mdi-pencil-outline fs-6"></i></a>@endif
  @if ($delete)<button type="button" class="btn btn-sm btn-icon btn-outline-beige text-danger" title="Delete" data-bs-toggle="modal" data-bs-target="#deleteModal" data-delete-url="{{ $delete }}" data-delete-label="{{ $label }}"><i class="mdi mdi-trash-can-outline fs-6"></i></button>@endif
</div>
