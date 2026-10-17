<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SubcategoryController extends Controller
{
    /** Reordering is drag-and-drop from the list, so keep the whole set on one page whenever possible. */
    private const REORDER_PER_PAGE = 100;

    public function index(Request $request)
    {
        $filtered = $request->filled('q') || $request->filled('status');

        $rows = Subcategory::query()->with('category:id,name')->withCount('products')
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', "%$v%")->orWhere('slug', 'like', "%$v%")))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
            ->orderBy('category_id')->orderBy('sort_order')->paginate(self::REORDER_PER_PAGE)->withQueryString();

        // Dragging only makes sense over the full, unfiltered, single-page list; otherwise order is ambiguous.
        // Sort order is scoped per parent category, so the list is dragged as independent per-category groups.
        $canReorder = ! $filtered && ! $rows->hasPages();

        return view('subcategories.index', ['rows' => $rows, 'categories' => $this->categories(), 'canReorder' => $canReorder]);
    }

    /** Persist the drag-and-drop order from the list page, one or more per-category groups at once. */
    public function reorder(Request $request)
    {
        $data = $request->validate([
            'groups' => ['required', 'array', 'min:1'],
            'groups.*.category_id' => ['required', 'integer', 'exists:categories,id'],
            'groups.*.ids' => ['required', 'array'],
            'groups.*.ids.*' => ['integer', 'distinct', 'exists:subcategories,id'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['groups'] as $group) {
                foreach (array_values($group['ids']) as $i => $id) {
                    Subcategory::where('id', $id)->where('category_id', $group['category_id'])->update(['sort_order' => $i + 1, 'updated_by' => auth('admin')->id()]);
                }
            }
        });

        return response()->json(['message' => 'Subcategory order saved.']);
    }

    public function create()
    {
        return view('subcategories.form', ['subcategory' => new Subcategory(['sort_order' => 0, 'status' => 'active']), 'categories' => $this->categories()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['image_url'] = $this->uploadImage($request, 'image', 'subcategories');
        Subcategory::create($data);

        return redirect()->route('subcategories.index')->with('success', 'Subcategory created.');
    }

    public function edit(Subcategory $subcategory)
    {
        return view('subcategories.form', ['subcategory' => $subcategory, 'categories' => $this->categories()]);
    }

    public function update(Request $request, Subcategory $subcategory)
    {
        $data = $this->validated($request, $subcategory);
        $data['image_url'] = $this->uploadImage($request, 'image', 'subcategories', $subcategory->image_url);
        $subcategory->update($data);

        return redirect()->route('subcategories.index')->with('success', 'Subcategory updated.');
    }

    public function destroy(Subcategory $subcategory)
    {
        return $this->destroyWith(fn () => $subcategory->delete(), 'subcategories.index', 'subcategory');
    }

    private function categories()
    {
        return Category::orderBy('sort_order')->pluck('name', 'id');
    }

    private function validated(Request $request, ?Subcategory $sub = null): array
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:190', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('subcategories', 'slug')->ignore($sub)],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'image' => ['nullable', 'file'],
        ]);
        $data['slug'] = $this->slugFor('subcategories', $data['slug'] ?? null, $data['name'], $sub?->id);
        unset($data['image']);

        return $data;
    }
}
