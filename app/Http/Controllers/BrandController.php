<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $rows = Brand::query()->withCount('products')
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', "%$v%")->orWhere('slug', 'like', "%$v%")))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('name')->paginate($this->perPage())->withQueryString();

        return view('brands.index', compact('rows'));
    }

    public function create()
    {
        return view('brands.form', ['brand' => new Brand(['status' => 'active'])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['logo_url'] = $this->uploadImage($request, 'image', 'brands');
        Brand::create($data);

        return redirect()->route('brands.index')->with('success', 'Brand created.');
    }

    public function edit(Brand $brand)
    {
        return view('brands.form', compact('brand'));
    }

    public function update(Request $request, Brand $brand)
    {
        $data = $this->validated($request, $brand);
        $data['logo_url'] = $this->uploadImage($request, 'image', 'brands', $brand->logo_url);
        $brand->update($data);

        return redirect()->route('brands.index')->with('success', 'Brand updated.');
    }

    public function destroy(Brand $brand)
    {
        return $this->destroyWith(fn () => $brand->delete(), 'brands.index', 'brand');
    }

    private function validated(Request $request, ?Brand $brand = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:190', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('brands', 'slug')->ignore($brand)],
            'origin_country' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'image' => ['nullable', 'file'],
        ]);
        $data['slug'] = $this->slugFor('brands', $data['slug'] ?? null, $data['name'], $brand?->id);
        unset($data['image']);

        return $data;
    }
}
