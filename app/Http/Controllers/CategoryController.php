<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /** Reordering is drag-and-drop from the list, so keep the whole set on one page whenever possible. */
    private const REORDER_PER_PAGE = 100;

    public function index(Request $request)
    {
        $filtered = $request->filled('q') || $request->filled('status');

        $rows = Category::query()->withCount(['subcategories', 'products'])
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', "%$v%")->orWhere('slug', 'like', "%$v%")))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('sort_order')->orderBy('name')->paginate(self::REORDER_PER_PAGE)->withQueryString();

        // Dragging only makes sense over the full, unfiltered, single-page list; otherwise order is ambiguous.
        $canReorder = ! $filtered && ! $rows->hasPages();

        return view('categories.index', compact('rows', 'canReorder'));
    }

    /** Persist the drag-and-drop order from the list page. */
    public function reorder(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'distinct', 'exists:categories,id']]);

        DB::transaction(function () use ($data) {
            foreach (array_values($data['ids']) as $i => $id) {
                Category::where('id', $id)->update(['sort_order' => $i + 1, 'updated_by' => auth('admin')->id()]);
            }
        });

        return response()->json(['message' => 'Category order saved.']);
    }

    public function create()
    {
        return view('categories.form', ['category' => new Category(['sort_order' => 0, 'status' => 'active'])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['image_url'] = $this->uploadImage($request, 'image', 'categories');
        Category::create($data);

        return redirect()->route('categories.index')->with('success', 'Category created.');
    }

    public function edit(Category $category)
    {
        return view('categories.form', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validated($request, $category);
        $data['image_url'] = $this->uploadImage($request, 'image', 'categories', $category->image_url);
        $category->update($data);

        return redirect()->route('categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        return $this->destroyWith(fn () => $category->delete(), 'categories.index', 'category');
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:190', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories', 'slug')->ignore($category)],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'image' => ['nullable', 'file'],
        ]);
        $data['slug'] = $this->slugFor('categories', $data['slug'] ?? null, $data['name'], $category?->id);
        unset($data['image']);

        return $data;
    }
}
