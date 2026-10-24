<?php

namespace App\Http\Controllers;

use App\Models\SizeGuide;
use App\Models\SizeGuideAttribute;
use App\Models\SizeGuideValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SizeGuideController extends Controller
{
    public function index(Request $request)
    {
        $rows = SizeGuide::query()->with('sizeAttributes:id,size_guide_id,attribute_name,unit')->withCount('products')
            ->addSelect(['size_count' => SizeGuideValue::query()->selectRaw('COUNT(DISTINCT size_label)')->whereColumn('size_guide_id', 'size_guides.id')])
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('title', 'like', "%$v%")->orWhere('key', 'like', "%$v%")))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('title')->paginate($this->perPage())->withQueryString();

        return view('size-guides.index', compact('rows'));
    }

    public function create()
    {
        return view('size-guides.form', ['guide' => new SizeGuide(['status' => 'active']), 'matrix' => $this->matrix(null)]);
    }

    public function store(Request $request)
    {
        [$data, $attrs, $sizes] = $this->validated($request);
        $guide = DB::transaction(function () use ($data, $attrs, $sizes) {
            $guide = SizeGuide::create($data);
            $this->saveMatrix($guide, $attrs, $sizes);

            return $guide;
        });

        return redirect()->route('size-guides.show', $guide)->with('success', 'Size guide created.');
    }

    public function show(SizeGuide $size_guide)
    {
        $size_guide->load(['sizeAttributes', 'sizeValues']);
        $products = $size_guide->products()->select('id', 'name', 'sku', 'price', 'selling_price', 'stock_qty', 'has_variants')->orderBy('name')->limit(50)->get();

        return view('size-guides.show', ['guide' => $size_guide, 'products' => $products]);
    }

    public function edit(SizeGuide $size_guide)
    {
        return view('size-guides.form', ['guide' => $size_guide, 'matrix' => $this->matrix($size_guide)]);
    }

    public function update(Request $request, SizeGuide $size_guide)
    {
        [$data, $attrs, $sizes] = $this->validated($request, $size_guide);
        DB::transaction(function () use ($size_guide, $data, $attrs, $sizes) {
            $size_guide->update($data);
            SizeGuideValue::where('size_guide_id', $size_guide->id)->delete();
            SizeGuideAttribute::where('size_guide_id', $size_guide->id)->delete();
            $this->saveMatrix($size_guide, $attrs, $sizes);
        });

        return redirect()->route('size-guides.show', $size_guide)->with('success', 'Size guide updated.');
    }

    public function destroy(SizeGuide $size_guide)
    {
        // products.size_guide_id is ON DELETE SET NULL: assigned products simply lose the guide.
        return $this->destroyWith(fn () => $size_guide->delete(), 'size-guides.index', 'size guide');
    }

    /** Initial editor state: attribute columns + size rows (from old input, the guide, or a blank starter). */
    private function matrix(?SizeGuide $guide): array
    {
        if (old('attributes')) {
            return ['attributes' => array_values(old('attributes')), 'sizes' => array_values(old('sizes', []))];
        }
        if (! $guide) {
            return ['attributes' => [['name' => '', 'unit' => 'cm']], 'sizes' => [['label' => '', 'values' => ['']]]];
        }

        $guide->load(['sizeAttributes', 'sizeValues']);
        $attrs = $guide->sizeAttributes->map(fn ($a) => ['name' => $a->attribute_name, 'unit' => $a->unit])->values()->all();
        $sizes = $guide->sizeValues->groupBy('size_label')->map(fn ($vals, $label) => [
            'label' => $label,
            'values' => $guide->sizeAttributes->map(fn ($a) => (string) ($vals->firstWhere('attribute_id', $a->id)->measurement_value ?? ''))->all(),
        ])->values()->all();

        return ['attributes' => $attrs, 'sizes' => $sizes];
    }

    private function validated(Request $request, ?SizeGuide $guide = null): array
    {
        $in = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('size_guides', 'key')->ignore($guide)],
            'customer_details' => ['nullable', 'string', 'max:5000'],
            'admin_details' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'attributes' => ['required', 'array', 'min:1', 'max:20'],
            'attributes.*.name' => ['required', 'string', 'max:100'],
            'attributes.*.unit' => ['required', 'string', 'max:20'],
            'sizes' => ['required', 'array', 'min:1', 'max:60'],
            'sizes.*.label' => ['required', 'string', 'max:80'],
            'sizes.*.values' => ['required', 'array'],
            'sizes.*.values.*' => ['required', 'string', 'max:50'],
        ], [
            'sizes.*.values.*.required' => 'Every size needs a measurement for every attribute.',
            'sizes.*.values.*.string' => 'Measurements must be text, e.g. 38 or 38-40.',
        ]);

        $attrs = array_values($in['attributes']);
        $sizes = array_values($in['sizes']);

        $keys = collect($attrs)->map(fn ($a) => Str::slug($a['name']));
        if ($keys->contains('') || $keys->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['attributes' => 'Attribute names must be unique.']);
        }
        if (collect($sizes)->pluck('label')->map(fn ($l) => mb_strtolower(trim($l)))->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['sizes' => 'Size labels must be unique.']);
        }
        foreach ($sizes as $s) {
            if (count($s['values']) !== count($attrs)) {
                throw ValidationException::withMessages(['sizes' => 'Each size needs one value per attribute.']);
            }
        }

        return [collect($in)->only(['title', 'key', 'customer_details', 'admin_details', 'status'])->all(), $attrs, $sizes];
    }

    private function saveMatrix(SizeGuide $guide, array $attrs, array $sizes): void
    {
        $ids = [];
        foreach ($attrs as $i => $a) {
            $ids[$i] = SizeGuideAttribute::create([
                'size_guide_id' => $guide->id, 'attribute_name' => $a['name'], 'attribute_key' => Str::slug($a['name']),
                'unit' => $a['unit'], 'sort_order' => $i + 1,
            ])->id;
        }
        $rows = [];
        foreach ($sizes as $j => $s) {
            foreach ($ids as $i => $attrId) {
                $rows[] = [
                    'size_guide_id' => $guide->id, 'size_label' => trim($s['label']), 'attribute_id' => $attrId,
                    'measurement_value' => $s['values'][$i], 'sort_order' => ($j + 1) * 10 + $i,
                    'created_by' => auth('admin')->id(), 'updated_by' => auth('admin')->id(),
                ];
            }
        }
        SizeGuideValue::insert($rows);
    }
}
