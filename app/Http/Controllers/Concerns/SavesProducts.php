<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductImage;
use App\Models\ProductSize;
use App\Models\ProductVariant;
use App\Support\HtmlCleaner;
use App\Support\ProductLookups;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Product form state, validation and persistence, shared by the product screens and the URL importer. */
trait SavesProducts
{
    /** @var array<class-string,int> per sync: how many existing colour/size rows were left unreferenced */
    private array $staleCounts = [];

    private const BADGES = [
        'new' => 'NEW', 'launch' => 'LAUNCH OFFER', 'low_stock' => 'LOW STOCK', 'preorder' => 'PRE-ORDER',
    ];

    private function formData(Product $product): array
    {
        $product->loadMissing(['colors', 'sizes', 'variants', 'images.colors:id', 'specification']);

        // Images: from the submitted form after a validation error, otherwise from the database.
        $dbImages = $product->images->keyBy('id');
        if (is_array(old('images'))) {
            $images = collect(old('images'))->values()->map(fn ($e, $k) => [
                'id' => ($e['id'] ?? null) ?: null,
                'url' => ! empty($e['id']) ? ($dbImages[(int) $e['id']]->image_url ?? null) : ($e['url'] ?? null),
                'main' => (string) old('main_key') === (string) $k,
            ])->filter(fn ($i) => $i['url'])->values()->all();
        } else {
            $images = $product->images->values()->map(fn ($i) => ['id' => $i->id, 'url' => $i->image_url, 'main' => (bool) $i->is_main])->all();
        }

        if (is_array(old('colors'))) {
            $colors = collect(old('colors'))->values()->map(fn ($c) => [
                'id' => $c['id'] ?? '', 'name' => $c['name'] ?? '', 'hex' => $c['hex_code'] ?? '#cccccc',
                'images' => array_map('intval', $c['images'] ?? []),
            ])->all();
        } else {
            $imageIdx = $product->images->values()->pluck('id')->flip();
            $colors = $product->colors->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'hex' => $c->hex_code ?: '#cccccc',
                'images' => $product->images->filter(fn ($i) => $i->colors->contains('id', $c->id))->map(fn ($i) => $imageIdx[$i->id])->values()->all(),
            ])->all();
        }

        $sizes = old('sizes') ?? $product->sizes->map(fn ($s) => ['id' => $s->id, 'size_label' => $s->size_label])->all();
        $colorIdx = collect($colors)->pluck('id')->filter()->flip(); // new rows have a blank (null) id
        $sizeIdx = collect($sizes)->pluck('id')->filter()->flip();
        $variants = old('variants') ?? $product->variants->map(fn ($v) => [
            'id' => $v->id, 'color' => $colorIdx[$v->color_id] ?? '', 'size' => $sizeIdx[$v->size_id] ?? '', 'sku' => $v->sku,
            'price_override' => $v->price_override, 'selling_price_override' => $v->selling_price_override,
            'stock_qty' => $v->stock_qty, 'is_active' => $v->is_active ? 1 : 0,
        ])->all();

        $lookups = ProductLookups::all();

        return [
            'product' => $product,
            'categories' => collect($lookups['categories']),
            'subcategories' => collect($lookups['subcategories'])->map(fn ($s) => (object) $s),
            'brands' => collect($lookups['brands']),
            'badges' => self::BADGES,
            'state' => [
                'images' => $images, 'colors' => $colors, 'sizes' => array_values($sizes), 'variants' => array_values($variants),
                'guides' => $lookups['guides'], 'slugManual' => $this->slugWasEdited(old('slug', $product->slug), old('name', $product->name)),
            ],
        ];
    }

    /** True when the stored/submitted slug differs from what the name would generate, i.e. it was edited by hand. */
    private function cleanHtml(?string $html): ?string
    {
        return HtmlCleaner::clean($html);
    }

    private function slugWasEdited(?string $slug, ?string $name): bool
    {
        return filled($slug) && $slug !== Str::slug((string) $name);
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $hasVariants = $request->boolean('has_variants');
        $draft = $request->boolean('save_draft');
        $required = $draft ? 'nullable' : 'required';

        $lookups = ProductLookups::all();
        $subcategoryOk = function (string $attr, mixed $value, \Closure $fail) use ($lookups, $request) {
            $match = collect($lookups['subcategories'])->firstWhere('id', (int) $value);
            if (! $match || (string) $match['category_id'] !== (string) $request->input('category_id')) {
                $fail('The selected subcategory is invalid.');
            }
        };

        $data = $request->validate([
            'name' => ['required', 'string', 'max:220'],
            'slug' => ['nullable', 'string', 'max:240', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->ignore($product)],
            'sku' => ['nullable', 'string', 'max:120', Rule::unique('products', 'sku')->ignore($product)],
            'category_id' => ['required', 'integer', Rule::in(array_keys($lookups['categories']))],
            'subcategory_id' => ['nullable', 'integer', $subcategoryOk],
            'brand_id' => ['nullable', 'integer', Rule::in(array_keys($lookups['brands']))],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:100000'],
            'price' => [$required, 'numeric', 'min:0', 'max:9999999999'],
            'selling_price' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'variant_scenario' => [$hasVariants && ! $draft ? 'required' : 'nullable', Rule::in(['color_size', 'color_only', 'size_only'])],
            'stock_qty' => [$hasVariants || $draft ? 'nullable' : 'required', 'integer', 'min:0', 'max:4294967295'],
            'availability' => ['required', Rule::in(['in_stock', 'out_of_stock', 'preorder', 'coming_soon'])],
            'badge_type' => ['nullable', Rule::in(array_keys(self::BADGES))],
            'preorder_release_date' => ['nullable', 'date'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'size_guide_id' => ['nullable', 'integer', Rule::in(array_column($lookups['guides'], 'id'))],
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
            'colors.*.images' => ['nullable', 'array'],
            'colors.*.images.*' => ['integer', 'min:0'],
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
            'images' => ['nullable', 'array', 'max:30'],
            'images.*.id' => ['nullable', 'integer'],
            'images.*.url' => ['nullable', 'string', 'max:2048'],
            'main_key' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['has_variants'] = $hasVariants;
        $data['draft'] = $draft;
        if ($draft) {
            $data['status'] = 'draft';
        }
        $this->checkImages($data, $product);
        if (! $draft) {
            $this->checkVariants($data, $hasVariants);
        }

        return $data;
    }

    /** New images must be Cloudinary URLs produced by our own upload endpoint; existing ones must belong to this product. */
    private function checkImages(array &$data, ?Product $product): void
    {
        $prefix = 'https://res.cloudinary.com/'.config('cloudinary.cloud_name').'/';
        $submitted = collect($data['images'] ?? [])->pluck('id')->filter();
        $owned = $product && $submitted->isNotEmpty() ? $product->images()->pluck('id')->all() : [];
        $clean = [];

        foreach (array_values($data['images'] ?? []) as $e) {
            $id = (int) ($e['id'] ?? 0);
            if ($id) {
                if (! in_array($id, $owned, true)) {
                    throw ValidationException::withMessages(['images' => 'One of the images no longer belongs to this product. Reload the page.']);
                }
                $clean[] = ['id' => $id];
            } elseif (! empty($e['url'])) {
                if (! str_starts_with($e['url'], $prefix) || ! filter_var($e['url'], FILTER_VALIDATE_URL)) {
                    throw ValidationException::withMessages(['images' => 'An image URL was not produced by the uploader. Please upload it again.']);
                }
                $clean[] = ['url' => $e['url']];
            }
        }

        $data['images'] = $clean;
    }

    /** Cross-field rules the base validator can't express. */
    private function checkVariants(array $data, bool $hasVariants): void
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
            $fail('Select at least one size for this variant scenario.');
        }
        if (! $variants) {
            $fail('Enable at least one variant.');
        }

        $skus = collect($variants)->pluck('sku')->filter(fn ($v) => $v !== null && $v !== '')->unique()->values();
        $taken = $skus->isEmpty() ? collect() : DB::table('product_variants')->whereIn('sku', $skus)->pluck('id', 'sku')->mapWithKeys(fn ($id, $sku) => [strtolower($sku) => $id]);

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
            $sku = $v['sku'] ?? '';
            if ($sku !== '' && $taken->has(strtolower($sku)) && (int) $taken[strtolower($sku)] !== (int) ($v['id'] ?? 0)) {
                $fail("Variant SKU {$sku} is already in use.");
            }
        }
    }

    private function persist(Product $product, array $in): Product
    {
        $has = $in['has_variants'];
        $fields = [
            'name' => $in['name'], 'slug' => $this->slugFor('products', $in['slug'] ?? null, $in['name'], $product->id),
            'sku' => $in['sku'] ?? null ?: null, 'category_id' => $in['category_id'], 'subcategory_id' => $in['subcategory_id'] ?? null,
            'brand_id' => $in['brand_id'] ?? null, 'short_description' => $in['short_description'] ?? null, 'description' => $this->cleanHtml($in['description'] ?? null),
            'price' => $in['price'] ?? 0, 'selling_price' => $in['selling_price'] ?? null, 'has_variants' => $has,
            // The table CHECK requires a scenario for variant products and NULL stock; simple products need stock.
            'variant_scenario' => $has ? ($in['variant_scenario'] ?? 'color_size') : null, 'stock_qty' => $has ? null : ($in['stock_qty'] ?? 0),
            'availability' => $in['availability'],
            'badge' => ! empty($in['badge_type']) ? ['type' => $in['badge_type'], 'label' => self::BADGES[$in['badge_type']]] : null,
            'preorder_release_date' => $in['preorder_release_date'] ?? null, 'low_stock_threshold' => $in['low_stock_threshold'] ?? null,
            'size_guide_id' => $in['size_guide_id'] ?? null, 'visibility' => $in['visibility'], 'status' => $in['status'],
            'seo_title' => $in['seo_title'] ?? null, 'seo_description' => $in['seo_description'] ?? null,
        ];
        $product->fill($fields)->save();

        $spec = collect($in['spec'] ?? [])->only(['material', 'fabric', 'dimensions', 'fit', 'pattern', 'care_instructions'])->map(fn ($v) => $v ?: null)->all();
        $product->wasRecentlyCreated ? $product->specification()->create($spec) : $product->specification()->updateOrCreate([], $spec);

        try {
            $colorIds = $this->syncVariants($product, $in);
        } catch (QueryException $e) {
            report($e);
            throw ValidationException::withMessages(['variants' => 'A removed color, size or variant is still in a customer cart. Mark it inactive instead of removing it.']);
        }

        $this->syncImages($product, $in, $colorIds);

        return $product;
    }

    /**
     * The database is remote (~130 ms per query), so everything below is batched: rows are loaded once, unchanged rows
     * issue no UPDATE (Eloquent skips clean models), new rows go in one multi-row INSERT and stale rows in one DELETE.
     *
     * @return array<int,int> submitted colour index => colour row id
     */
    private function syncVariants(Product $product, array $in): array
    {
        $has = $in['has_variants'];
        $scenario = $in['variant_scenario'] ?? 'color_size';
        $colorsIn = $has && $scenario !== 'size_only' ? array_values($in['colors'] ?? []) : [];
        $sizesIn = $has && $scenario !== 'color_only' ? array_values($in['sizes'] ?? []) : [];
        $variantsIn = $has ? array_values($in['variants'] ?? []) : [];
        $fresh = $product->wasRecentlyCreated;

        $colorIds = $this->syncNamed(ProductColor::class, $product, $colorsIn, 'name', fn ($c, $i) => ['name' => $c['name'], 'hex_code' => $c['hex_code'] ?? null, 'sort_order' => $i + 1], $fresh);
        $sizeIds = $this->syncNamed(ProductSize::class, $product, $sizesIn, 'size_label', fn ($s, $i) => ['size_label' => $s['size_label'], 'sort_order' => $i + 1], $fresh);

        $existing = $fresh ? collect() : $product->variants()->get();
        $byId = $existing->keyBy('id');
        $byPair = $existing->keyBy(fn ($v) => ($v->color_id ?? 0).'|'.($v->size_id ?? 0));

        // Match each submitted variant to an existing row (by id, else by colour/size pair) before touching anything.
        $matched = [];
        $keep = [];
        foreach ($variantsIn as $n => $v) {
            $colorId = $scenario === 'size_only' ? null : $colorIds[(int) ($v['color'] ?? 0)];
            $sizeId = $scenario === 'color_only' ? null : $sizeIds[(int) ($v['size'] ?? 0)];
            $row = $byId->get($v['id'] ?? 0) ?? $byPair->get(($colorId ?? 0).'|'.($sizeId ?? 0));
            $matched[$n] = [$row, $colorId, $sizeId];
            if ($row) {
                $keep[] = $row->id;
            }
        }

        // Stale variants go first: they free colour/size unique keys and hold the colour/size FKs.
        $stale = $existing->pluck('id')->diff($keep)->all();
        if ($stale) {
            $product->variants()->whereIn('id', $stale)->delete();
        }

        $taken = null; // SKUs sharing our prefix, loaded lazily and only if some variant has no SKU
        $inserts = [];
        $by = auth('admin')->id();
        foreach ($variantsIn as $n => $v) {
            [$row, $colorId, $sizeId] = $matched[$n];
            $sku = ($v['sku'] ?? null) ?: $this->variantSku($product, $colorsIn[(int) ($v['color'] ?? -1)]['name'] ?? null, $sizesIn[(int) ($v['size'] ?? -1)]['size_label'] ?? null, $row?->id, $taken);
            $attrs = [
                'color_id' => $colorId, 'size_id' => $sizeId, 'sku' => $sku,
                'price_override' => $v['price_override'] ?? null, 'selling_price_override' => $v['selling_price_override'] ?? null,
                'stock_qty' => $v['stock_qty'] ?? 0, 'is_active' => (int) ! empty($v['is_active']),
            ];
            if ($row) {
                $row->fill($attrs)->save();
            } else {
                $inserts[] = $attrs + ['product_id' => $product->id, 'created_by' => $by, 'updated_by' => $by];
            }
        }
        if ($inserts) {
            ProductVariant::insert($inserts);
        }

        // Only hit the database when a colour/size row actually became unreferenced.
        if (! $fresh) {
            $this->deleteStale(ProductColor::class, $product, $colorIds);
            $this->deleteStale(ProductSize::class, $product, $sizeIds);
        }

        return $colorIds;
    }

    /** Upsert colours or sizes by submitted id (or name); returns [submittedIndex => rowId]. */
    private function syncNamed(string $model, Product $product, array $items, string $nameCol, callable $map, bool $fresh = false): array
    {
        if (! $items && $fresh) {
            return [];
        }

        $existing = $fresh ? collect() : $model::where('product_id', $product->id)->get()->keyBy('id');
        $ids = [];
        $new = [];
        $by = auth('admin')->id();
        foreach ($items as $i => $item) {
            $row = $existing->get($item['id'] ?? 0) ?? $existing->firstWhere($nameCol, $item[$nameCol]);
            if ($row) {
                $row->fill($map($item, $i))->save();
                $ids[$i] = $row->id;
            } else {
                $new[$i] = $map($item, $i) + ['product_id' => $product->id, 'created_by' => $by, 'updated_by' => $by];
            }
        }

        if ($new) {
            $model::insert(array_values($new));
            $created = $model::where('product_id', $product->id)->whereIn($nameCol, array_column($new, $nameCol))->pluck('id', $nameCol)
                ->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id]);
            foreach ($new as $i => $attrs) {
                $ids[$i] = $created[mb_strtolower($attrs[$nameCol])];
            }
            ksort($ids);
        }

        $this->staleCounts[$model] = $existing->keys()->diff($ids)->count();

        return $ids;
    }

    private function deleteStale(string $model, Product $product, array $keepIds): void
    {
        if (($this->staleCounts[$model] ?? 1) > 0) {
            $model::where('product_id', $product->id)->whereNotIn('id', $keepIds ?: [0])->delete();
        }
    }

    /** @param  array<string,int>|null  $taken  sku (lower-case) => variant id, loaded on first use and updated as SKUs are handed out */
    private function variantSku(Product $product, ?string $color, ?string $size, ?int $ignoreId, ?array &$taken): string
    {
        $base = strtoupper($product->sku ?: 'P'.$product->id);
        $parts = array_filter([$base, $color ? Str::upper(Str::limit(Str::slug($color), 6, '')) : null, $size ? Str::upper(Str::slug($size)) : null]);
        $sku = $candidate = implode('-', $parts);

        $taken ??= DB::table('product_variants')->where('sku', 'like', $base.'%')->pluck('id', 'sku')->mapWithKeys(fn ($id, $s) => [strtolower($s) => $id])->all();
        for ($i = 2; isset($taken[strtolower($candidate)]) && $taken[strtolower($candidate)] !== $ignoreId; $i++) {
            $candidate = $sku.'-'.$i;
        }
        $taken[strtolower($candidate)] = $ignoreId ?? 0;

        return $candidate;
    }

    /**
     * Images arrive in display order. Existing rows are kept (no re-upload, no duplicates), new Cloudinary URLs are inserted,
     * missing ones are removed, exactly one is main, and per-colour assignments go into product_image_colors.
     */
    private function syncImages(Product $product, array $in, array $colorIds): void
    {
        $entries = array_values($in['images'] ?? []);
        $fresh = $product->wasRecentlyCreated;
        $existing = $fresh ? collect() : $product->images()->get()->keyBy('id');
        $by = auth('admin')->id();

        $keepExisting = collect($entries)->pluck('id')->filter()->all();
        $stale = $existing->keys()->diff($keepExisting)->all();
        if ($stale) {
            $product->images()->whereIn('id', $stale)->delete();
        }

        $keyToId = [];
        $inserts = [];
        foreach ($entries as $k => $entry) {
            if (isset($entry['id'])) {
                $existing[$entry['id']]->fill(['sort_order' => $k])->save();
                $keyToId[$k] = $entry['id'];
            } else {
                $inserts[$k] = ['product_id' => $product->id, 'image_url' => $entry['url'], 'alt_text' => $product->name, 'is_main' => 0, 'sort_order' => $k, 'created_by' => $by, 'updated_by' => $by];
            }
        }
        if ($inserts) {
            ProductImage::insert(array_values($inserts));
            // A multi-row INSERT gets consecutive ids in row order, and these are the only rows of this product beyond the kept ones.
            $newIds = $product->images()->whereNotIn('id', $keepExisting ?: [0])->orderBy('id')->pluck('id')->all();
            foreach (array_keys($inserts) as $n => $k) {
                $keyToId[$k] = $newIds[$n];
            }
        }

        $keep = array_values($keyToId);
        $mainId = $keyToId[(int) ($in['main_key'] ?? -1)] ?? ($keep[0] ?? null);
        if ($mainId) {
            $product->images()->update(['is_main' => DB::raw('(id = '.(int) $mainId.')')]);
        }

        // Desired image <-> colour pairs versus what is stored: delete the removed pairs, insert the new ones.
        $want = [];
        foreach (array_values($in['colors'] ?? []) as $i => $color) {
            foreach ($color['images'] ?? [] as $k) {
                if (isset($keyToId[$k], $colorIds[$i])) {
                    $want[$keyToId[$k].'|'.$colorIds[$i]] = [(int) $keyToId[$k], (int) $colorIds[$i]];
                }
            }
        }
        $have = [];
        if ($keepExisting) {
            foreach (DB::table('product_image_colors')->whereIn('image_id', $keepExisting)->get(['image_id', 'color_id']) as $r) {
                $have[$r->image_id.'|'.$r->color_id] = [(int) $r->image_id, (int) $r->color_id];
            }
        }

        if ($remove = array_diff_key($have, $want)) {
            DB::table('product_image_colors')->where(function ($q) use ($remove) {
                foreach ($remove as [$image, $color]) {
                    $q->orWhere(fn ($w) => $w->where('image_id', $image)->where('color_id', $color));
                }
            })->delete();
        }
        if ($add = array_diff_key($want, $have)) {
            DB::table('product_image_colors')->insert(array_map(fn ($p) => ['image_id' => $p[0], 'color_id' => $p[1], 'created_by' => $by, 'updated_by' => $by], array_values($add)));
        }
    }
}
