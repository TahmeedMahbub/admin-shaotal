<?php

namespace App\Services\ProductImport;

use App\Support\HtmlCleaner;
use Closure;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * fabrilife.com product pages. Their JSON-LD is SEO boilerplate (name carries " | Fabrilife", a generic marketing
 * description, one square image), so the real data is read from the page body instead. Values are only ever copied
 * from the page; anything the page does not state stays empty for manual entry.
 */
class FabrilifeAdapter
{
    /** label (lower-case, without colon) => spec field */
    private const LABELS = [
        'material' => 'material', 'composition' => 'material', 'fabric composition' => 'material',
        'fabric' => 'fabric', 'main fabric' => 'fabric', 'fabric type' => 'fabric',
        'pattern' => 'pattern', 'print' => 'pattern',
        'fit' => 'fit',
        'dimensions' => 'dimensions',
        'care' => 'care_instructions', 'care instructions' => 'care_instructions', 'wash care' => 'care_instructions', 'washing instructions' => 'care_instructions',
    ];

    private const MAX_LEN = ['material' => 160, 'fabric' => 160, 'pattern' => 100, 'fit' => 100, 'dimensions' => 160, 'care_instructions' => 5000];

    public static function handles(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'fabrilife.com' || str_ends_with($host, '.fabrilife.com');
    }

    /**
     * @param  Closure  $abs  fn (string $url): string — resolves a page-relative URL
     * @return array<string, mixed>|null null when the page is not a recognisable product page
     */
    public function extract(DOMDocument $dom, DOMXPath $xp, string $html, string $url, Closure $abs): ?array
    {
        $card = $xp->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' product-details-card ')]")->item(0);
        if (! $card) {
            return null;
        }

        $mark = $this->markProduct($html);
        $id = preg_match('#/product/(\d+)#', (string) parse_url($url, PHP_URL_PATH), $m) ? $m[1] : (isset($mark['id']) ? (string) $mark['id'] : null);

        $name = $this->clean($xp->query('.//h4', $card)->item(0)?->textContent) ?: ($mark['name'] ?? null);
        if (! $name) {
            return null;
        }

        $now = $this->number($xp->query(".//span[@class='price_field']", $card)->item(0)?->textContent);
        $old = $this->number($xp->query(".//span[@class='regular_price_field']", $card)->item(0)?->textContent);

        $sizes = [];
        foreach ($xp->query(".//div[contains(concat(' ', normalize-space(@class), ' '), ' size-selector ')]", $card) as $el) {
            if ($id !== null && $el->getAttribute('data-productid') !== '' && $el->getAttribute('data-productid') !== $id) {
                continue;
            }
            $label = $this->clean($el->getAttribute('data-size') ?: $el->textContent);
            if ($label !== null) {
                $sizes[$label] = $label;
            }
        }

        $chart = $this->sizeChart($xp);
        $notes = [];
        if (! $sizes && $chart) {
            // The size picker is empty when the product is out of stock; the size chart still lists the sizes the page offers.
            foreach (reset($chart)['rows'] as $row) {
                if (($label = $this->clean($row[0] ?? null)) !== null) {
                    $sizes[$label] = $label;
                }
            }
            $notes[] = 'No size picker on the source page; sizes were taken from its size chart.';
        }
        $outOfStock = $xp->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' btnOutOfStock ')]", $card)->length > 0;
        if ($outOfStock) {
            $notes[] = 'This product is out of stock on the source site. Enter your own stock quantities.';
        }

        $descNode = $xp->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' type-description ')][contains(@class,'self-product-description')]")->item(0);
        $descHtml = $descNode ? HtmlCleaner::clean($this->innerHtml($dom, $descNode)) : null;
        $attributes = $descNode ? $this->attributes($descNode) : [];
        $spec = $this->spec($attributes, $descNode);

        $colours = [];
        foreach ($attributes as $label => $value) {
            if (in_array($label, ['colour', 'color'], true)) {
                foreach (preg_split('/\s*,\s*/', $value) as $c) {
                    if ($c !== '') {
                        $colours[$c] = $c;
                    }
                }
            }
        }

        return [
            'source_id' => $id, 'name' => mb_substr($name, 0, 220), 'brand' => 'Fabrilife', 'sku' => null,
            'description_html' => $descHtml, 'description' => $descHtml ? $this->plain($descHtml) : null,
            'price' => $now, 'list_price' => $old && $now && $old > $now ? $old : null, 'currency' => 'BDT',
            'availability' => $outOfStock ? 'OutOfStock' : ($xp->query(".//button[contains(@class,'btnAddToCart')]", $card)->length ? 'InStock' : null), 'notes' => $notes, 'category_hint' => isset($mark['category']) ? $this->clean($mark['category']) : null,
            'attributes' => $attributes, 'spec' => $spec, 'material' => $spec['material'] ?? null,
            'colors' => array_values($colours), 'sizes' => array_values($sizes), 'variants' => [],
            'size_chart' => $chart,
            'images' => $this->images($xp, $abs),
        ];
    }

    /** @return array<string, mixed> the page's `var mark_product = {...};` analytics object */
    private function markProduct(string $html): array
    {
        if (preg_match('/var\s+mark_product\s*=\s*(\{.*?\})\s*;/s', $html, $m)) {
            $j = json_decode($m[1], true);

            return is_array($j) ? $j : [];
        }

        return [];
    }

    /** @return list<array{url: string, via: string}> */
    private function images(DOMXPath $xp, Closure $abs): array
    {
        $files = [];
        $q = "//div[contains(concat(' ', normalize-space(@class), ' '), ' product-image-container ')][1]//img[contains(concat(' ', normalize-space(@class), ' '), ' product-image ')]";
        foreach ($xp->query($q) as $img) {
            $files[] = $img->getAttribute('data-img_url') ?: $img->getAttribute('src');
        }
        foreach ($xp->query("//div[contains(@class,'thumbswitch')][1]//*[@data-img_url]") as $thumb) {
            $files[] = $thumb->getAttribute('data-img_url');
        }

        $out = [];
        foreach ($files as $f) {
            $f = trim($f);
            if ($f === '') {
                continue;
            }
            $url = $abs(str_contains($f, '/') ? $f : '/products/'.$f);
            $out[strtok($url, '?')] ??= ['url' => strtok($url, '?'), 'via' => 'fabrilife'];
        }

        return array_values($out);
    }

    /** `<strong>Label:</strong> value` pairs inside the description. @return array<string, string> */
    private function attributes(DOMElement $root): array
    {
        $out = [];
        foreach ((new DOMXPath($root->ownerDocument))->query('.//strong|.//b', $root) as $strong) {
            $label = $this->clean($strong->textContent);
            if ($label === null || ! str_ends_with($label, ':')) {
                continue;
            }
            $label = mb_strtolower(rtrim($label, ': '));
            $value = '';
            for ($n = $strong->nextSibling; $n; $n = $n->nextSibling) {
                $value .= $n->textContent;
            }
            $value = $this->clean($value);
            if ($value !== null && $label !== '') {
                $out[$label] ??= $value;
            }
        }

        // Plain "Label: value" lines without a bold label, e.g. "Fabric type: Plain fabric".
        $xp = new DOMXPath($root->ownerDocument);
        foreach ($xp->query('.//li|.//p', $root) as $el) {
            if ($xp->query('.//li|.//p', $el)->length > 0) {
                continue;
            }
            if (preg_match('/^([A-Za-z][A-Za-z .\/&\'-]{1,28}):\s*(.{1,400})$/u', $this->clean($el->textContent) ?? '', $m)) {
                $out[mb_strtolower(trim($m[1]))] ??= trim($m[2]);
            }
        }

        return $out;
    }

    /** @return array<string, string> only fields the page actually states */
    private function spec(array $attributes, ?DOMElement $descNode): array
    {
        $spec = [];
        foreach ($attributes as $label => $value) {
            $field = self::LABELS[$label] ?? null;
            if ($field && ! isset($spec[$field])) {
                $spec[$field] = $this->fit($value, self::MAX_LEN[$field]);
            }
        }
        // "Made of 100% Cotton" is a statement of material even without a label.
        if (! isset($spec['material']) && $descNode) {
            $xp = new DOMXPath($descNode->ownerDocument);
            foreach ($xp->query('.//li|.//p', $descNode) as $el) {
                if ($xp->query('.//li|.//p', $el)->length === 0 && preg_match('/^made (?:of|from)\s+(.{3,160}?)\.?$/iu', $this->clean($el->textContent) ?? '', $m)) {
                    $spec['material'] = trim($m[1]);
                    break;
                }
            }
        }
        // "Regular Fit designed for ..." is written as a bold phrase rather than a labelled item.
        if (! isset($spec['fit']) && $descNode) {
            foreach ((new DOMXPath($descNode->ownerDocument))->query('.//strong|.//b', $descNode) as $strong) {
                if (preg_match('/^(regular|slim|relaxed|loose|oversized|tailored|athletic|skinny|straight|comfort)\s+fit$/i', $this->clean($strong->textContent) ?? '')) {
                    $spec['fit'] = $this->clean($strong->textContent);
                    break;
                }
            }
        }

        return $spec;
    }

    /** Keeps the value whole; only when it would not fit the column is it cut at the site's own " — " clause break. */
    private function fit(string $value, int $max): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }
        $head = preg_split('/\s+[\x{2013}\x{2014}-]\s+/u', $value)[0];

        return mb_substr($head, 0, $max);
    }

    /** @return array<string, array{headers: list<string>, rows: list<list<string>>}> keyed inch / cm */
    private function sizeChart(DOMXPath $xp): array
    {
        $out = [];
        foreach ($xp->query("//div[starts-with(@id,'sizechart-')]") as $pane) {
            $unit = preg_match('/sizechart-([a-z]+)/i', $pane->getAttribute('id'), $m) ? strtolower($m[1]) : 'chart';
            $table = $xp->query('.//table', $pane)->item(0);
            if (! $table) {
                continue;
            }
            $headers = [];
            foreach ($xp->query('.//th', $table) as $th) {
                $headers[] = $this->clean($th->textContent) ?? '';
            }
            $rows = [];
            foreach ($xp->query('.//tbody/tr', $table) as $tr) {
                $cells = [];
                foreach ($xp->query('./td', $tr) as $td) {
                    $cells[] = $this->clean($td->textContent) ?? '';
                }
                if ($cells) {
                    $rows[] = $cells;
                }
            }
            if ($headers && $rows) {
                $out[$unit] ??= ['headers' => $headers, 'rows' => $rows];
            }
        }

        return $out;
    }

    private function innerHtml(DOMDocument $dom, DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        return $html;
    }

    private function plain(string $html): string
    {
        $t = preg_replace('#<\s*(br|/p|/li|/h[1-6])\b[^>]*>#i', "\n", $html);
        $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace(["/[ \t\x{00A0}]+/u", "/\s*\n\s*/"], [' ', "\n"], $t));
    }

    private function number(?string $v): ?string
    {
        $v = str_replace(',', '', (string) preg_replace('/[^\d.,]/', '', (string) $v));

        return is_numeric($v) ? number_format((float) $v, 2, '.', '') : null;
    }

    private function clean(?string $v): ?string
    {
        $v = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', html_entity_decode((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return $v === '' ? null : $v;
    }
}
