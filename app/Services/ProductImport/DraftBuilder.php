<?php

namespace App\Services\ProductImport;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Subcategory;

/**
 * Turns an extraction into the field names the product form posts, so the review page can reuse the product form.
 * Only values the source actually stated are filled; everything else stays '' and is listed by manualFields().
 */
class DraftBuilder
{
    /** @return array<string, mixed> */
    public static function build(array $raw): array
    {
        [$categoryId, $subcategoryId] = self::category($raw['category_hint'] ?? null);

        $sizes = array_values(array_filter(array_map('trim', $raw['sizes'] ?? []), 'strlen'));
        $colors = array_values(array_filter(array_map('trim', $raw['colors'] ?? []), 'strlen'));
        $scenario = $colors && $sizes ? 'color_size' : ($colors ? 'color_only' : ($sizes ? 'size_only' : null));

        $variants = [];
        foreach ($scenario === 'size_only' ? [null] : array_keys($colors) as $ci) {
            foreach ($scenario === 'color_only' ? [null] : array_keys($sizes) as $si) {
                $variants[] = ['id' => '', 'color' => $ci ?? '', 'size' => $si ?? '', 'sku' => '', 'price_override' => '', 'selling_price_override' => '', 'stock_qty' => '', 'is_active' => 1];
            }
        }

        $spec = $raw['spec'] ?? [];

        return [
            'name' => $raw['name'] ?? '', 'slug' => '', 'sku' => '',
            'category_id' => $categoryId ?? '', 'subcategory_id' => $subcategoryId ?? '', 'brand_id' => self::brand($raw['brand'] ?? null) ?? '',
            'short_description' => '', 'description' => self::description($raw),
            'price' => '', 'selling_price' => '', 'stock_qty' => '', 'low_stock_threshold' => '',
            'has_variants' => $scenario ? 1 : 0, 'variant_scenario' => $scenario ?? 'color_size',
            'availability' => 'in_stock', 'visibility' => 'visible', 'status' => 'draft', 'size_guide_id' => '',
            'spec' => [
                'material' => $spec['material'] ?? '', 'fabric' => $spec['fabric'] ?? '', 'dimensions' => $spec['dimensions'] ?? '',
                'fit' => $spec['fit'] ?? '', 'pattern' => $spec['pattern'] ?? '', 'care_instructions' => $spec['care_instructions'] ?? '',
            ],
            'colors' => array_map(fn ($name) => ['id' => '', 'name' => $name, 'hex_code' => '#cccccc', 'images' => []], $colors),
            'sizes' => array_map(fn ($label) => ['id' => '', 'size_label' => $label], $sizes),
            'variants' => $variants,
            'seo_title' => '', 'seo_description' => '',
            'images' => [],
        ];
    }

    /**
     * Fields an admin still has to fill, as [input name => label]. Evaluated against the current form values.
     *
     * @return array<string, string>
     */
    public static function manualFields(array $d): array
    {
        $blank = fn ($v) => $v === null || $v === '' || $v === [];
        $need = [];
        foreach (['price' => 'A$ selling price', 'sku' => 'SKU', 'short_description' => 'Short description', 'category_id' => 'Category', 'brand_id' => 'Brand'] as $k => $label) {
            if ($blank($d[$k] ?? null)) {
                $need[$k] = $label;
            }
        }
        if (! $blank($d['category_id'] ?? null) && $blank($d['subcategory_id'] ?? null)) {
            $need['subcategory_id'] = 'Subcategory';
        }
        foreach (['material' => 'Material', 'fabric' => 'Fabric', 'pattern' => 'Pattern', 'fit' => 'Fit', 'dimensions' => 'Dimensions', 'care_instructions' => 'Care instructions'] as $k => $label) {
            if ($blank($d['spec'][$k] ?? null)) {
                $need['spec['.$k.']'] = $label;
            }
        }
        foreach (['seo_title' => 'SEO title', 'seo_description' => 'SEO description'] as $k => $label) {
            if ($blank($d[$k] ?? null)) {
                $need[$k] = $label;
            }
        }
        if (empty($d['has_variants']) && $blank($d['stock_qty'] ?? null)) {
            $need['stock_qty'] = 'Stock quantity';
        }
        if (! empty($d['has_variants']) && collect($d['variants'] ?? [])->every(fn ($v) => $blank($v['stock_qty'] ?? null) || (int) $v['stock_qty'] === 0)) {
            $need['variants'] = 'Stock per variant';
        }
        if (collect($d['colors'] ?? [])->contains(fn ($c) => strtolower($c['hex_code'] ?? '') === '#cccccc')) {
            $need['colors'] = 'Colour swatch (hex)';
        }
        if ($blank($d['images'] ?? null)) {
            $need['images'] = 'Approved product images';
        }

        return $need;
    }

    private static function description(array $raw): string
    {
        if (! empty($raw['description_html'])) {
            return $raw['description_html'];
        }
        $paragraphs = preg_split('/\n+/', trim((string) ($raw['description'] ?? '')));

        return collect($paragraphs)->filter()->map(fn ($p) => '<p>'.e($p).'</p>')->implode('');
    }

    /** @return array{0: int|null, 1: int|null} only an unambiguous exact-name match is used; otherwise left for manual choice */
    private static function category(?string $hint): array
    {
        if (! $hint) {
            return [null, null];
        }
        $parts = array_values(array_filter(array_map(fn ($p) => mb_strtolower(trim($p)), preg_split('/[>\/|]/', $hint))));
        $subs = Subcategory::query()->get(['id', 'category_id', 'name'])->filter(fn ($s) => in_array(mb_strtolower(trim($s->name)), $parts, true));
        if ($subs->count() === 1) {
            return [$subs->first()->category_id, $subs->first()->id];
        }
        $cats = Category::query()->get(['id', 'name'])->filter(fn ($c) => in_array(mb_strtolower(trim($c->name)), $parts, true));

        return [$cats->count() === 1 && $subs->isEmpty() ? $cats->first()->id : null, null];
    }

    private static function brand(?string $name): ?int
    {
        return $name ? Brand::query()->where('name', trim($name))->value('id') : null;
    }
}
