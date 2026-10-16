<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductImage;
use App\Models\ProductSize;
use App\Models\ProductVariant;
use App\Models\SizeGuide;
use App\Models\Subcategory;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    private const BADGES = [
        'new' => 'NEW', 'launch' => 'LAUNCH OFFER', 'low_stock' => 'LOW STOCK', 'preorder' => 'PRE-ORDER',
    ];

    public function index(Request $request)
    {
        $rows = Product::query()
            ->select('id', 'name', 'sku', 'category_id', 'subcategory_id', 'brand_id', 'price', 'selling_price', 'has_variants', 'stock_qty', 'low_stock_threshold', 'availability', 'status', 'visibility')
            ->with(['category:id,name', 'subcategory:id,name', 'brand:id,name'])
            ->withCount('variants')
            ->withSum(['variants as variants_stock' => fn ($q) => $q->where('is_active', 1)], 'stock_qty')
            ->addSelect(['image_url' => ProductImage::select('image_url')->whereColumn('product_id', 'products.id')->orderByDesc('is_main')->orderBy('sort_order')->limit(1)])
            ->when($request->q, fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', "%$v%")->orWhere('sku', 'like', "%$v%")))
            ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->brand_id, fn ($q, $v) => $q->where('brand_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->stock === 'low', fn ($q) => $q->where(fn ($w) => $w
                ->where(fn ($s) => $s->where('has_variants', 0)->whereRaw('stock_qty <= COALESCE(low_stock_threshold, 5)'))
                ->orWhereExists(fn ($e) => $e->from('product_variants as v')->whereColumn('v.product_id', 'products.id')->where('v.is_active', 1)->whereRaw('v.stock_qty <= COALESCE(products.low_stock_threshold, 5)'))))
            ->latest('id')->paginate($this->perPage())->withQueryString();

        return view('products.index', [
            'rows' => $rows,
            'categories' => Category::orderBy('sort_order')->pluck('name', 'id'),
            'brands' => Brand::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create()
    {
        $product = new Product(['has_variants' => 0, 'availability' => 'in_stock', 'visibility' => 'visible', 'status' => 'draft', 'stock_qty' => 0]);

        return view('products.form', $this->formData($product));
    }

    public function store(Request $request)
    {
        $input = $this->validated($request);
        $uploads = $this->uploadImages($request);
        $product = DB::transaction(fn () => $this->persist(new Product, $input, $request, $uploads));

        return redirect()->route('products.edit', $product)->with('success', 'Product created.');
    }

    public function edit(Product $product)
    {
        return view('products.form', $this->formData($product));
    }

    public function update(Request $request, Product $product)
    {
        $input = $this->validated($request, $product);
        $uploads = $this->uploadImages($request);
        DB::transaction(fn () => $this->persist($product, $input, $request, $uploads));

        return redirect()->route('products.edit', $product)->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        // Order history keeps product snapshots; never hard-delete a product that was ordered.
        if (DB::table('order_items')->where('product_id', $product->id)->exists()) {
            $product->update(['status' => 'archived', 'visibility' => 'hidden']);

            return redirect()->route('products.index')->with('success', 'Product has orders, so it was archived instead of deleted.');
        }

        // Variants hold RESTRICT foreign keys on colours/sizes, so they must go before the product cascade runs.
        return $this->destroyWith(fn () => DB::transaction(function () use ($product) {
            $product->variants()->delete();
            $product->delete();
        }), 'products.index', 'product');
    }

    private function formData(Product $product): array
    {
        $product->loadMissing(['colors', 'sizes', 'variants', 'images.colors:id', 'specification']);

        $colors = old('colors') ?? $product->colors->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'hex_code' => $c->hex_code ?: '#cccccc'])->all();
        $sizes = old('sizes') ?? $product->sizes->map(fn ($s) => ['id' => $s->id, 'size_label' => $s->size_label])->all();
        $colorIdx = $product->colors->pluck('id')->flip();
        $sizeIdx = $product->sizes->pluck('id')->flip();
        $variants = old('variants') ?? $product->variants->map(fn ($v) => [
            'id' => $v->id, 'color' => $colorIdx[$v->color_id] ?? '', 'size' => $sizeIdx[$v->size_id] ?? '', 'sku' => $v->sku,
            'price_override' => $v->price_override, 'selling_price_override' => $v->selling_price_override,
            'stock_qty' => $v->stock_qty, 'is_active' => $v->is_active ? 1 : 0,
        ])->all();

        return [
            'product' => $product,
            'categories' => Category::orderBy('sort_order')->pluck('name', 'id'),
            'subcategories' => Subcategory::orderBy('sort_order')->get(['id', 'category_id', 'name']),
            'brands' => Brand::orderBy('name')->pluck('name', 'id'),
            'sizeGuides' => SizeGuide::orderBy('title')->pluck('title', 'id'),
            'badges' => self::BADGES,
            'state' => ['colors' => array_values($colors), 'sizes' => array_values($sizes), 'variants' => array_values($variants)],
        ];
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $hasVariants = $request->boolean('has_variants');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:220'],
            'slug' => ['nullable', 'string', 'max:240', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->ignore($product)],
            'sku' => ['nullable', 'string', 'max:120', Rule::unique('products', 'sku')->ignore($product)],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'subcategory_id' => ['nullable', 'integer', Rule::exists('subcategories', 'id')->where('category_id', $request->input('category_id'))],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:100000'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'selling_price' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'variant_scenario' => [$hasVariants ? 'required' : 'nullable', Rule::in(['color_size', 'color_only', 'size_only'])],
            'stock_qty' => [$hasVariants ? 'nullable' : 'required', 'integer', 'min:0', 'max:4294967295'],
            'availability' => ['required', Rule::in(['in_stock', 'out_of_stock', 'preorder', 'coming_soon'])],
            'badge_type' => ['nullable', Rule::in(array_keys(self::BADGES))],
            'preorder_release_date' => ['nullable', 'date'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'size_guide_id' => ['nullable', 'integer', 'exists:size_guides,id'],
            'visibility' => ['required', Rule::in(['visible', 'hidden'])],
            'status' => ['required', Rule::in(['draft', 'active', 'inactive', 'archived'])],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'spec' => ['nullable', 'array'],
            'spec.material' => ['nullable', 'string', 'max:160'], 'spec.fabric' => ['nullable', 'string', 'max:160'],
            'spec.dimensions' => ['nullable', 'string', 'max:160'], 'spec.fit' => ['nullable', 'string', 'max:100'],
            'spec.pattern' => ['nullable', 'string', 'max:100'], 'spec.care_instructions' => ['nullable', 'string', 'max:5000'],
            'colors' => ['nullable', 'array', 'max:50'],
            'colors.*.id' => ['nullable', 'integer'],
            'colors.*.name' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'colors.*.hex_code' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sizes' => ['nullable', 'array', 'max:50'],
            'sizes.*.id' => ['nullable', 'integer'],
            'sizes.*.size_label' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'variants' => ['nullable', 'array', 'max:500'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.color' => ['nullable', 'integer', 'min:0'],
            'variants.*.size' => ['nullable', 'integer', 'min:0'],
            'variants.*.sku' => ['nullable', 'string', 'max:120', 'distinct'],
            'variants.*.price_override' => ['nullable', 'numeric', 'min:0'],
            'variants.*.selling_price_override' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_active' => ['nullable'],
            'variants.*.stock_qty' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'main_image' => ['nullable', 'file'],
            'new_images' => ['nullable', 'array', 'max:20'],
            'new_images.*' => ['file'],
            'images' => ['nullable', 'array'],
            'images.*.remove' => ['nullable'],
            'images.*.alt_text' => ['nullable', 'string', 'max:255'],
            'images.*.colors' => ['nullable', 'array'],
            'images.*.colors.*' => ['integer'],
            'main_existing' => ['nullable', 'integer'],
        ]);

        $data['has_variants'] = $hasVariants;
        $this->checkVariants($data, $hasVariants, $product);

        return $data;
    }

    /** Cross-field rules the base validator can't express. */
    private function checkVariants(array $data, bool $hasVariants, ?Product $product): void
    {
        if (! $hasVariants) {
            return;
        }

        $colors = $data['colors'] ?? [];
        $sizes = $data['sizes'] ?? [];
        $variants = $data['variants'] ?? [];
        $scenario = $data['variant_scenario'];
        $fail = fn (string $msg) => throw ValidationException::withMessages(['variants' => $msg]);

        if (in_array($scenario, ['color_size', 'color_only'], true) && ! $colors) {
            $fail('Add at least one color for this variant scenario.');
        }
        if (in_array($scenario, ['color_size', 'size_only'], true) && ! $sizes) {
            $fail('Add at least one size for this variant scenario.');
        }
        if (! $variants) {
            $fail('Enable at least one variant.');
        }

        $seen = [];
        foreach ($variants as $v) {
            $c = $scenario === 'size_only' ? null : ($v['color'] ?? null);
            $s = $scenario === 'color_only' ? null : ($v['size'] ?? null);
            if (($scenario !== 'size_only' && ($c === null || $c === '' || ! isset($colors[$c]))) || ($scenario !== 'color_only' && ($s === null || $s === '' || ! isset($sizes[$s])))) {
                $fail('Each variant must reference a valid color/size for the chosen scenario.');
            }
            if (isset($seen[$c.'|'.$s])) {
                $fail('Duplicate variant combination.');
            }
            $seen[$c.'|'.$s] = true;
            if (isset($v['sku']) && $v['sku'] !== '' && DB::table('product_variants')->where('sku', $v['sku'])->when($v['id'] ?? null, fn ($q, $id) => $q->where('id', '!=', $id))->exists()) {
                $fail("Variant SKU {$v['sku']} is already in use.");
            }
        }
    }

    /** Upload all new files first so a Cloudinary failure aborts before anything is written. */
    private function uploadImages(Request $request): array
    {
        $out = ['main' => null, 'extra' => []];
        if ($file = $request->file('main_image')) {
            $out['main'] = $this->upload($file, 'products', 'main_image');
        }
        foreach ($request->file('new_images', []) as $file) {
            $out['extra'][] = $this->upload($file, 'products', 'new_images');
        }

        return $out;
    }

    private function persist(Product $product, array $in, Request $request, array $uploads): Product
    {
        $has = $in['has_variants'];
        $fields = [
            'name' => $in['name'], 'slug' => $this->slugFor('products', $in['slug'] ?? null, $in['name'], $product->id),
            'sku' => $in['sku'] ?? null ?: null, 'category_id' => $in['category_id'], 'subcategory_id' => $in['subcategory_id'] ?? null,
            'brand_id' => $in['brand_id'] ?? null, 'short_description' => $in['short_description'] ?? null, 'description' => $in['description'] ?? null,
            'price' => $in['price'], 'selling_price' => $in['selling_price'] ?? null, 'has_variants' => $has,
            'variant_scenario' => $has ? $in['variant_scenario'] : null, 'stock_qty' => $has ? null : $in['stock_qty'],
            'availability' => $in['availability'],
            'badge' => ! empty($in['badge_type']) ? ['type' => $in['badge_type'], 'label' => self::BADGES[$in['badge_type']]] : null,
            'preorder_release_date' => $in['preorder_release_date'] ?? null, 'low_stock_threshold' => $in['low_stock_threshold'] ?? null,
            'size_guide_id' => $in['size_guide_id'] ?? null, 'visibility' => $in['visibility'], 'status' => $in['status'],
            'seo_title' => $in['seo_title'] ?? null, 'seo_description' => $in['seo_description'] ?? null,
        ];
        $product->fill($fields)->save();

        $product->specification()->updateOrCreate([], collect($in['spec'] ?? [])->only(['material', 'fabric', 'dimensions', 'fit', 'pattern', 'care_instructions'])->map(fn ($v) => $v ?: null)->all());

        try {
            $this->syncVariants($product, $in);
        } catch (QueryException $e) {
            report($e);
            throw ValidationException::withMessages(['variants' => 'A removed color, size or variant is still in a customer cart. Mark it inactive instead of removing it.']);
        }

        $this->syncImages($product, $in, $request, $uploads);

        return $product;
    }

    private function syncVariants(Product $product, array $in): void
    {
        $has = $in['has_variants'];
        $scenario = $in['variant_scenario'] ?? null;
        $colorsIn = $has && $scenario !== 'size_only' ? array_values($in['colors'] ?? []) : [];
        $sizesIn = $has && $scenario !== 'color_only' ? array_values($in['sizes'] ?? []) : [];
        $variantsIn = $has ? array_values($in['variants'] ?? []) : [];

        $colorIds = $this->syncNamed(ProductColor::class, $product, $colorsIn, 'name', fn ($c, $i) => ['name' => $c['name'], 'hex_code' => $c['hex_code'] ?? null, 'sort_order' => $i + 1], $variantsIn, 'color_id');
        $sizeIds = $this->syncNamed(ProductSize::class, $product, $sizesIn, 'size_label', fn ($s, $i) => ['size_label' => $s['size_label'], 'sort_order' => $i + 1], $variantsIn, 'size_id');

        $keep = [];
        foreach ($variantsIn as $v) {
            $colorId = $scenario === 'size_only' ? null : $colorIds[(int) ($v['color'] ?? 0)];
            $sizeId = $scenario === 'color_only' ? null : $sizeIds[(int) ($v['size'] ?? 0)];
            $existing = $product->variants()->where(fn ($q) => $q->where('id', $v['id'] ?? 0)->orWhere(fn ($w) => $w->where('color_key', $colorId ?? 0)->where('size_key', $sizeId ?? 0)))->first();

            $sku = ($v['sku'] ?? null) ?: $this->variantSku($product, $colorsIn[(int) ($v['color'] ?? -1)]['name'] ?? null, $sizesIn[(int) ($v['size'] ?? -1)]['size_label'] ?? null, $existing?->id);
            $row = $existing ?? new ProductVariant(['product_id' => $product->id]);
            $row->fill([
                'color_id' => $colorId, 'size_id' => $sizeId, 'sku' => $sku,
                'price_override' => $v['price_override'] ?? null, 'selling_price_override' => $v['selling_price_override'] ?? null,
                'stock_qty' => $v['stock_qty'] ?? 0, 'is_active' => ! empty($v['is_active']),
            ])->save();
            $keep[] = $row->id;
        }

        // Variants first (they hold the colour/size FKs), then the colours/sizes no longer referenced.
        $product->variants()->whereNotIn('id', $keep)->delete();
        $this->deleteStale(ProductColor::class, $product, $colorIds);
        $this->deleteStale(ProductSize::class, $product, $sizeIds);
    }

    /** Upsert colours or sizes by submitted id; returns [submittedIndex => rowId]. */
    private function syncNamed(string $model, Product $product, array $items, string $nameCol, callable $map, array $variants, string $fk): array
    {
        $existing = $model::where('product_id', $product->id)->get()->keyBy('id');
        $ids = [];
        foreach ($items as $i => $item) {
            $row = $existing->get($item['id'] ?? 0) ?? $existing->firstWhere($nameCol, $item[$nameCol]) ?? new $model(['product_id' => $product->id]);
            $row->fill($map($item, $i))->save();
            $ids[$i] = $row->id;
        }

        return $ids;
    }

    private function deleteStale(string $model, Product $product, array $keepIds): void
    {
        $model::where('product_id', $product->id)->whereNotIn('id', $keepIds ?: [0])->delete();
    }

    private function variantSku(Product $product, ?string $color, ?string $size, ?int $ignoreId): string
    {
        $base = strtoupper($product->sku ?: 'P'.$product->id);
        $parts = array_filter([$base, $color ? Str::upper(Str::limit(Str::slug($color), 6, '')) : null, $size ? Str::upper(Str::slug($size)) : null]);
        $sku = $candidate = implode('-', $parts);
        for ($i = 2; DB::table('product_variants')->where('sku', $candidate)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists(); $i++) {
            $candidate = $sku.'-'.$i;
        }

        return $candidate;
    }

    private function syncImages(Product $product, array $in, Request $request, array $uploads): void
    {
        $submitted = $in['images'] ?? [];
        $images = $product->images()->get()->keyBy('id');
        $validColorIds = ProductColor::where('product_id', $product->id)->pluck('id')->all();

        foreach ($images as $id => $img) {
            $row = $submitted[$id] ?? null;
            if ($row && ! empty($row['remove'])) {
                $img->delete();
                $images->forget($id);

                continue;
            }
            if ($row) {
                $img->update(['alt_text' => $row['alt_text'] ?? null]);
                $img->colors()->sync(array_values(array_intersect(array_map('intval', $row['colors'] ?? []), $validColorIds)));
            }
        }

        $order = ($images->max('sort_order') ?? 0);
        $newMain = null;
        if ($uploads['main']) {
            $newMain = ProductImage::create(['product_id' => $product->id, 'image_url' => $uploads['main'], 'alt_text' => $product->name, 'is_main' => 0, 'sort_order' => 0]);
        }
        foreach ($uploads['extra'] as $url) {
            ProductImage::create(['product_id' => $product->id, 'image_url' => $url, 'alt_text' => $product->name, 'is_main' => 0, 'sort_order' => ++$order]);
        }

        // Exactly one main image: a fresh main upload wins, then the radio choice, then the first image.
        $all = $product->images()->get();
        $main = $newMain ?? $all->firstWhere('id', (int) ($in['main_existing'] ?? 0)) ?? $all->firstWhere('is_main', 1) ?? $all->first();
        if ($main) {
            $product->images()->where('id', '!=', $main->id)->update(['is_main' => 0]);
            $main->update(['is_main' => 1]);
        }
    }
}
