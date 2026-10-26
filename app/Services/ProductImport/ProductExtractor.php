<?php

namespace App\Services\ProductImport;

use App\Support\HtmlCleaner;
use DOMDocument;
use DOMXPath;

/**
 * Pulls product facts out of a page without any AI: schema.org JSON-LD first (most shops publish it),
 * then OpenGraph/meta tags, then plain HTML. Anything it cannot find is left empty for the later AI/review steps.
 */
class ProductExtractor
{
    private const NOISE_IMAGE = '/logo|icon|sprite|avatar|favicon|placeholder|pixel|badge|payment|flag|banner|loader|spinner|blank|tracking/i';

    private const MAX_HTML_IMAGES = 8;

    private const MAX_IMAGES = 20;

    /** @return array<string, mixed> */
    public function extract(string $html, string $url): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        $xp = new DOMXPath($dom);

        $meta = $this->meta($xp);
        $ld = $this->jsonLdProduct($xp);
        $via = [];

        $r = [
            'source_url' => $url, 'final_url' => $url, 'host' => strtolower((string) parse_url($url, PHP_URL_HOST)),
            'extracted_via' => [], 'name' => null, 'brand' => null, 'sku' => null, 'description' => null,
            'price' => null, 'list_price' => null, 'currency' => null, 'availability' => null, 'material' => null,
            'category_hint' => null, 'colors' => [], 'sizes' => [], 'variants' => [], 'images' => [],
            'description_html' => null, 'spec' => [], 'attributes' => [], 'size_chart' => [], 'source_id' => null,
            'meta' => ['title' => $this->first($xp, '//title'), 'description' => $meta['description'][0] ?? null, 'site_name' => $meta['og:site_name'][0] ?? null],
            'warnings' => [],
        ];

        $site = FabrilifeAdapter::handles($url) ? (new FabrilifeAdapter)->extract($dom, $xp, $html, $url, fn (string $u) => $this->abs($u, $url)) : null;

        if ($site) {
            // Site-specific data replaces the generic guesses entirely: this site's JSON-LD and OpenGraph are SEO boilerplate.
            $r = array_merge($r, array_diff_key($site, ['images' => 1]));
            $r['images'] = $this->mergeImages($site['images'], []);
            $via = ['fabrilife'];
        } else {
            if ($ld) {
                $via[] = 'json-ld';
                $r = array_merge($r, $this->fromJsonLd($ld));
            }

            // OpenGraph / meta fill whatever JSON-LD did not provide.
            $og = fn (string $k) => $meta[$k][0] ?? null;
            if (! $r['name'] && ($n = $og('og:title') ?? $this->first($xp, '//h1'))) {
                $r['name'] = $this->cleanTitle($n, $r['meta']['site_name']);
                $via[] = $og('og:title') ? 'opengraph' : 'html';
            }
            if (! $r['description'] && ($d = $og('og:description') ?? ($meta['description'][0] ?? null))) {
                $r['description'] = $d;
                $via[] = 'opengraph';
            }
            if ($r['price'] === null && ($p = $og('product:price:amount') ?? $og('og:price:amount'))) {
                $r['price'] = $this->price($p);
                $r['currency'] ??= $og('product:price:currency') ?? $og('og:price:currency');
                $via[] = 'opengraph';
            }
            $r['brand'] ??= $og('product:brand') ?? $og('og:brand');
            $r['availability'] ??= $og('product:availability') ?? $og('og:availability');

            $images = [];
            foreach ($r['images'] as $u) {
                $images[] = ['url' => $this->abs($u, $url), 'via' => 'json-ld'];
            }
            foreach (array_merge($meta['og:image:secure_url'] ?? [], $meta['og:image'] ?? [], $meta['twitter:image'] ?? []) as $u) {
                $images[] = ['url' => $this->abs($u, $url), 'via' => 'opengraph'];
            }
            $r['images'] = $this->mergeImages($images, $this->htmlImages($xp, $url));
        }

        $r['name'] = $this->text($r['name']) ? mb_substr($this->text($r['name']), 0, 220) : null;
        if (! $r['description_html'] && is_string($r['description']) && str_contains($r['description'], '<')) {
            $r['description_html'] = HtmlCleaner::clean($r['description']);
        }
        $r['description'] = $this->text($r['description']);
        $r['brand'] = $this->text($r['brand']);
        $r['extracted_via'] = array_values(array_unique($via ?: ($r['name'] ? ['html'] : [])));

        $notes = $r['notes'] ?? [];
        unset($r['notes']);
        $r = $this->withWarnings($r);
        $r['warnings'] = array_merge($r['warnings'], $notes);

        return $r;
    }

    /** @return array<string, list<string>> */
    private function meta(DOMXPath $xp): array
    {
        $out = [];
        foreach ($xp->query('//meta[@content]') as $m) {
            $k = strtolower($m->getAttribute('property') ?: $m->getAttribute('name') ?: $m->getAttribute('itemprop'));
            if ($k !== '' && trim($m->getAttribute('content')) !== '') {
                $out[$k][] = trim(html_entity_decode($m->getAttribute('content')));
            }
        }

        return $out;
    }

    private function first(DOMXPath $xp, string $query): ?string
    {
        $n = $xp->query($query)->item(0);

        return $n ? (trim(preg_replace('/\s+/', ' ', $n->textContent)) ?: null) : null;
    }

    /** First schema.org Product / ProductGroup found in any JSON-LD block (also inside @graph). */
    private function jsonLdProduct(DOMXPath $xp): ?array
    {
        $found = null;
        foreach ($xp->query('//script[@type="application/ld+json"]') as $s) {
            $json = json_decode(trim(preg_replace('/^\s*<!\[CDATA\[|\]\]>\s*$/', '', $s->textContent)), true);
            if (! is_array($json)) {
                continue;
            }
            $this->walk($json, function (array $node) use (&$found) {
                $types = (array) ($node['@type'] ?? []);
                if (! $found && (in_array('Product', $types, true) || in_array('ProductGroup', $types, true))) {
                    $found = $node;
                }
            });
            if ($found) {
                break;
            }
        }

        return $found;
    }

    private function walk(array $node, callable $visit): void
    {
        if (isset($node['@type'])) {
            $visit($node);
        }
        foreach (['@graph', 'mainEntity', 'itemListElement'] as $k) {
            foreach ((array) ($node[$k] ?? []) as $child) {
                if (is_array($child)) {
                    $this->walk($child, $visit);
                }
            }
        }
        if (array_is_list($node)) {
            foreach ($node as $child) {
                if (is_array($child)) {
                    $this->walk($child, $visit);
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private function fromJsonLd(array $n): array
    {
        $variantsIn = $this->list($n['hasVariant'] ?? []);
        $offers = $this->offers($n['offers'] ?? []);
        foreach ($variantsIn as $v) {
            $offers = $offers ?: $this->offers($v['offers'] ?? []);
        }

        $colors = $this->strings($n['color'] ?? []);
        $sizes = $this->strings($n['size'] ?? []);
        $variants = [];
        foreach ($variantsIn as $v) {
            $o = $this->offers($v['offers'] ?? []);
            $c = $this->strings($v['color'] ?? [])[0] ?? null;
            $z = $this->strings($v['size'] ?? [])[0] ?? null;
            $colors = array_merge($colors, $c ? [$c] : []);
            $sizes = array_merge($sizes, $z ? [$z] : []);
            $variants[] = ['name' => $this->text($v['name'] ?? null), 'sku' => $this->text($v['sku'] ?? null), 'color' => $c, 'size' => $z,
                'price' => $o['price'] ?? null, 'availability' => $o['availability'] ?? null];
        }

        $images = $this->images($n['image'] ?? []);
        foreach ($variantsIn as $v) {
            $images = array_merge($images, $this->images($v['image'] ?? []));
        }

        $cat = $n['category'] ?? null;

        return [
            'name' => $this->text($n['name'] ?? null), 'description' => $n['description'] ?? null,
            'sku' => $this->text($n['sku'] ?? ($n['mpn'] ?? null)),
            'brand' => is_array($n['brand'] ?? null) ? ($n['brand']['name'] ?? null) : ($n['brand'] ?? null),
            'price' => $offers['price'] ?? null, 'list_price' => $offers['list_price'] ?? null, 'currency' => $offers['currency'] ?? null,
            'availability' => $offers['availability'] ?? null,
            'material' => $this->text(is_array($n['material'] ?? null) ? ($n['material']['name'] ?? null) : ($n['material'] ?? null)),
            'category_hint' => $this->text(is_array($cat) ? implode(' > ', $this->strings($cat)) : $cat),
            'colors' => array_values(array_unique(array_filter($colors))), 'sizes' => array_values(array_unique(array_filter($sizes))),
            'variants' => $variants, 'images' => array_values(array_unique($images)),
        ];
    }

    /** @return array{price?: string, list_price?: string, currency?: string, availability?: string} */
    private function offers(mixed $raw): array
    {
        $list = $this->list($raw);
        if (! $list) {
            return [];
        }
        $prices = [];
        foreach ($list as $o) {
            $p = $this->price($o['price'] ?? ($o['lowPrice'] ?? null));
            if ($p !== null) {
                $prices[] = (float) $p;
            }
        }
        $o = $list[0];
        $listPrice = null;
        foreach ($this->list($o['priceSpecification'] ?? []) as $spec) {
            if (stripos((string) ($spec['priceType'] ?? ''), 'list') !== false || stripos((string) ($spec['priceType'] ?? ''), 'strikethrough') !== false) {
                $listPrice = $this->price($spec['price'] ?? null);
            }
        }

        return array_filter([
            'price' => $prices ? number_format(min($prices), 2, '.', '') : null,
            'list_price' => $listPrice,
            'currency' => $o['priceCurrency'] ?? ($o['priceSpecification']['priceCurrency'] ?? null),
            'availability' => isset($o['availability']) ? basename((string) $o['availability']) : null,
        ], fn ($v) => $v !== null);
    }

    private function price(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = str_replace(',', '', preg_replace('/[^\d.,]/', '', (string) $v));

        return is_numeric($s) ? number_format((float) $s, 2, '.', '') : null;
    }

    /** @return list<array> a single object or a list of objects, normalised to a list */
    private function list(mixed $v): array
    {
        if (! is_array($v) || $v === []) {
            return [];
        }

        return array_is_list($v) ? array_values(array_filter($v, 'is_array')) : [$v];
    }

    /** @return list<string> */
    private function strings(mixed $v): array
    {
        $out = [];
        foreach ((array) $v as $item) {
            $s = is_array($item) ? ($item['name'] ?? ($item['value'] ?? null)) : $item;
            if (is_scalar($s) && trim((string) $s) !== '') {
                $out[] = trim((string) $s);
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function images(mixed $v): array
    {
        $out = [];
        foreach (is_array($v) && ! array_is_list($v) ? [$v] : (array) $v as $item) {
            $u = is_array($item) ? ($item['url'] ?? ($item['contentUrl'] ?? null)) : $item;
            if (is_string($u) && trim($u) !== '') {
                $out[] = trim($u);
            }
        }

        return $out;
    }

    /** @return list<array{url: string, via: string}> */
    private function htmlImages(DOMXPath $xp, string $base): array
    {
        $out = [];
        foreach ($xp->query('//img') as $img) {
            $w = (int) $img->getAttribute('width');
            $h = (int) $img->getAttribute('height');
            if (($w && $w < 200) || ($h && $h < 200)) {
                continue;
            }
            $src = $img->getAttribute('data-zoom-image') ?: $img->getAttribute('data-large') ?: $img->getAttribute('data-src')
                ?: $img->getAttribute('data-original') ?: $this->largestSrcset($img->getAttribute('srcset') ?: $img->getAttribute('data-srcset')) ?: $img->getAttribute('src');
            if (! $src || str_starts_with($src, 'data:') || preg_match('/\.(svg|gif)(\?|$)/i', $src) || preg_match(self::NOISE_IMAGE, $src.' '.$img->getAttribute('class').' '.$img->getAttribute('alt'))) {
                continue;
            }
            $out[] = ['url' => $this->abs($src, $base), 'via' => 'html'];
        }

        return $out;
    }

    private function largestSrcset(string $srcset): ?string
    {
        $best = null;
        $bestW = -1;
        foreach (explode(',', $srcset) as $part) {
            $bits = preg_split('/\s+/', trim($part));
            $w = isset($bits[1]) ? (int) $bits[1] : 0;
            if ($bits[0] !== '' && $w > $bestW) {
                $best = $bits[0];
                $bestW = $w;
            }
        }

        return $best;
    }

    /** De-duplicates by path without size suffixes/query, keeps source order, caps the total. */
    private function mergeImages(array $primary, array $html): array
    {
        $seen = [];
        $out = [];
        $htmlAdded = 0;
        foreach (array_merge($primary, $html) as $img) {
            if (! preg_match('#^https?://#i', $img['url'])) {
                continue;
            }
            $key = preg_replace('/_(\d*x\d*|\d+w)(?=\.\w+$)/', '', strtok($img['url'], '?'));
            if (isset($seen[$key])) {
                continue;
            }
            if ($img['via'] === 'html' && ($primary ? $htmlAdded >= 4 : $htmlAdded >= self::MAX_HTML_IMAGES)) {
                continue;
            }
            $img['via'] === 'html' && $htmlAdded++;
            $seen[$key] = true;
            $out[] = $img;
            if (count($out) >= self::MAX_IMAGES) {
                break;
            }
        }

        return $out;
    }

    private function abs(string $u, string $base): string
    {
        $u = trim(html_entity_decode($u));
        if (preg_match('#^https?://#i', $u)) {
            return $u;
        }
        $b = parse_url($base);
        $root = ($b['scheme'] ?? 'https').'://'.($b['host'] ?? '').(isset($b['port']) ? ':'.$b['port'] : '');
        if (str_starts_with($u, '//')) {
            return ($b['scheme'] ?? 'https').':'.$u;
        }
        if (str_starts_with($u, '/')) {
            return $root.$u;
        }

        return $root.rtrim(dirname($b['path'] ?? '/'), '/').'/'.$u;
    }

    private function text(mixed $v): ?string
    {
        if (! is_string($v) || trim($v) === '') {
            return null;
        }
        $v = preg_replace('#<\s*(br|/p|/li|/h[1-6]|/div)\b[^>]*>#i', "\n", $v);
        $v = html_entity_decode(strip_tags($v), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $v = preg_replace(["/[ \t\x{00A0}]+/u", "/\s*\n\s*/"], [' ', "\n"], $v);

        return trim(mb_substr($v, 0, 20000)) ?: null;
    }

    /** Page titles carry a store suffix ("Item | Store"); structured product names do not, so only titles are trimmed. */
    private function cleanTitle(?string $name, ?string $site): ?string
    {
        $name = $this->text($name);
        if (! $name) {
            return null;
        }
        $name = preg_split('/\s+[|\x{2013}\x{2014}]\s+/u', $name)[0];
        if ($site) {
            $name = trim(preg_replace('/\s*[|\-\x{2013}]\s*'.preg_quote($site, '/').'\s*$/iu', '', $name));
        }

        return mb_substr($name, 0, 220);
    }

    private function withWarnings(array $r): array
    {
        $w = [];
        if (! $r['name']) {
            $w[] = 'No product name found.';
        }
        if ($r['price'] === null) {
            $w[] = 'No price found.';
        }
        if (! $r['images']) {
            $w[] = 'No images found.';
        }
        if (! array_intersect(['json-ld', 'fabrilife'], $r['extracted_via'])) {
            $w[] = 'No structured product data (JSON-LD) on the page; fields are best-effort.';
        }
        if ($r['currency'] && strtoupper($r['currency']) !== 'AUD') {
            $w[] = "Source price is in {$r['currency']}. Enter the A\$ price manually; no automatic conversion is applied.";
        }
        $r['warnings'] = $w;

        return $r;
    }
}
