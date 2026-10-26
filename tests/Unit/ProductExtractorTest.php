<?php

namespace Tests\Unit;

use App\Services\ProductImport\ProductExtractor;
use PHPUnit\Framework\TestCase;

class ProductExtractorTest extends TestCase
{
    private const URL = 'https://shop.example.com/products/premium-trouser-escape';

    /** Shaped like live product 41 (Premium Trouser - Escape): size-only variants 2XL/L/M/XL, 19.99 list / 14.99 sale. */
    private function jsonLdPage(): string
    {
        $ld = json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                ['@type' => 'BreadcrumbList'],
                [
                    '@type' => 'ProductGroup', 'name' => 'Premium Trouser - Escape', 'sku' => 'flt-escape',
                    'brand' => ['@type' => 'Brand', 'name' => 'Fresh Linen'],
                    'description' => '<p>Ideal for cozy evenings &amp; year-round lounging.</p><p>Two pockets and a drawstring.</p>',
                    'material' => '100% Cotton', 'category' => 'Fashion > Men > Lounge',
                    'image' => ['/img/escape-1_800x800.jpg', ['@type' => 'ImageObject', 'url' => 'https://cdn.example.com/escape-2.jpg']],
                    'hasVariant' => array_map(fn ($s) => [
                        '@type' => 'Product', 'name' => "Escape $s", 'sku' => "flt-escape-$s", 'size' => $s,
                        'offers' => ['@type' => 'Offer', 'price' => '14.99', 'priceCurrency' => 'AUD', 'availability' => 'https://schema.org/PreOrder'],
                    ], ['2XL', 'L', 'M', 'XL']),
                ],
            ],
        ]);

        return <<<HTML
        <html><head><title>Premium Trouser - Escape | Fresh Linen</title>
        <meta property="og:site_name" content="Fresh Linen">
        <meta property="og:image" content="https://cdn.example.com/escape-2.jpg">
        <script type="application/ld+json">$ld</script></head>
        <body><h1>Premium Trouser - Escape</h1>
        <img src="/img/logo.png"><img src="/img/escape-1_400x400.jpg" width="400">
        <img src="/img/tiny.jpg" width="40" height="40"></body></html>
        HTML;
    }

    public function test_extracts_from_json_ld_product_group(): void
    {
        $r = (new ProductExtractor)->extract($this->jsonLdPage(), self::URL);

        $this->assertSame('Premium Trouser - Escape', $r['name']);
        $this->assertSame('flt-escape', $r['sku']);
        $this->assertSame('Fresh Linen', $r['brand']);
        $this->assertSame('14.99', $r['price']);
        $this->assertSame('AUD', $r['currency']);
        $this->assertSame('PreOrder', $r['availability']);
        $this->assertSame('100% Cotton', $r['material']);
        $this->assertSame('Fashion > Men > Lounge', $r['category_hint']);
        $this->assertSame(['2XL', 'L', 'M', 'XL'], $r['sizes']);
        $this->assertSame([], $r['colors']);
        $this->assertCount(4, $r['variants']);
        $this->assertSame('flt-escape-L', $r['variants'][1]['sku']);
        $this->assertContains('json-ld', $r['extracted_via']);
        $this->assertStringContainsString("Ideal for cozy evenings & year-round lounging.\nTwo pockets", $r['description']);
        $this->assertStringNotContainsString('<p>', $r['description']);
    }

    public function test_images_are_resolved_deduplicated_and_noise_is_dropped(): void
    {
        $r = (new ProductExtractor)->extract($this->jsonLdPage(), self::URL);
        $urls = array_column($r['images'], 'url');

        $this->assertSame([
            'https://shop.example.com/img/escape-1_800x800.jpg',
            'https://cdn.example.com/escape-2.jpg',
        ], $urls, 'relative URL resolved; og duplicate, size-suffix duplicate, logo and tiny image removed');
    }

    public function test_falls_back_to_opengraph_and_html_when_no_json_ld(): void
    {
        $html = '<html><head><title>Linen Tee | Some Store</title>
            <meta property="og:title" content="Linen Tee | Some Store"><meta property="og:site_name" content="Some Store">
            <meta property="og:description" content="Soft linen tee."><meta property="og:image" content="https://cdn.example.com/tee.jpg">
            <meta property="product:price:amount" content="1,299.50"><meta property="product:price:currency" content="USD"></head><body></body></html>';
        $r = (new ProductExtractor)->extract($html, self::URL);

        $this->assertSame('Linen Tee', $r['name'], 'store suffix trimmed from the title');
        $this->assertSame('1299.50', $r['price']);
        $this->assertSame('USD', $r['currency']);
        $this->assertSame('Soft linen tee.', $r['description']);
        $this->assertSame(['opengraph'], $r['extracted_via']);
        $this->assertContains('Source price is in USD. Enter the A$ price manually; no automatic conversion is applied.', $r['warnings']);
        $this->assertStringContainsString('No structured product data', implode(' ', $r['warnings']));
    }

    public function test_page_without_product_data_yields_empty_result_and_warnings(): void
    {
        $r = (new ProductExtractor)->extract('<html><body><p>Hello</p></body></html>', self::URL);

        $this->assertNull($r['name']);
        $this->assertNull($r['price']);
        $this->assertSame([], $r['images']);
        $this->assertContains('No product name found.', $r['warnings']);
    }

    public function test_malformed_json_ld_is_ignored_not_fatal(): void
    {
        $html = '<html><head><meta property="og:title" content="Fallback"><script type="application/ld+json">{ broken json</script></head></html>';
        $this->assertSame('Fallback', (new ProductExtractor)->extract($html, self::URL)['name']);
    }
}
