<?php

namespace App\Services\ProductImport;

use App\Models\Product;

/** Read-only lookup of an existing product that would be duplicated by a new one (same SKU, same name, or the same name bar an audience word). */
class DuplicateGuard
{
    /** Audience words shops put in front of the real product name ("Mens Premium Trouser - Escape"). */
    private const AUDIENCE = ['mens', 'men', 'womens', 'women', 'boys', 'girls', 'kids', 'teens', 'teen', 'unisex'];

    public static function find(?string $name, ?string $sku, ?int $ignoreProductId = null): ?Product
    {
        $name = trim((string) $name);
        $sku = trim((string) $sku);
        if ($name === '' && $sku === '') {
            return null;
        }

        $exact = Product::query()->select('id', 'name', 'sku', 'status')
            ->when($ignoreProductId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->where(fn ($q) => $q
                ->when($name !== '', fn ($w) => $w->orWhere('name', $name))
                ->when($sku !== '', fn ($w) => $w->orWhere('sku', $sku)))
            ->first();

        return $exact ?? ($name !== '' ? self::similar($name, $ignoreProductId) : null);
    }

    public static function message(Product $existing): string
    {
        return "A product with the same or a matching name/SKU already exists: #{$existing->id} \"{$existing->name}\". Change the name/SKU if this is a different product.";
    }

    /** Same name once case, punctuation and a leading audience word are ignored. */
    private static function similar(string $name, ?int $ignoreProductId): ?Product
    {
        $key = self::key($name);
        $last = collect(preg_split('/[^a-z0-9]+/i', $name, -1, PREG_SPLIT_NO_EMPTY))->last();
        if ($key === '' || ! $last) {
            return null;
        }

        return Product::query()->select('id', 'name', 'sku', 'status')
            ->when($ignoreProductId, fn ($q, $id) => $q->where('id', '!=', $ignoreProductId))
            ->where('name', 'like', '%'.addcslashes($last, '%_\\').'%')
            ->limit(200)->get()
            ->first(fn (Product $p) => self::key($p->name) === $key);
    }

    private static function key(string $name): string
    {
        $words = preg_split('/[^a-z0-9]+/', mb_strtolower(str_replace(["'", "\u{2019}"], '', $name)), -1, PREG_SPLIT_NO_EMPTY);
        while ($words && in_array($words[0], self::AUDIENCE, true)) {
            array_shift($words);
        }

        return implode(' ', $words);
    }
}
