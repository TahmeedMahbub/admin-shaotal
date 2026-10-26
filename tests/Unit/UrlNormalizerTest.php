<?php

namespace Tests\Unit;

use App\Services\ProductImport\UrlNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UrlNormalizerTest extends TestCase
{
    #[DataProvider('sameAs')]
    public function test_variants_of_one_page_normalize_identically(string $a, string $b): void
    {
        $this->assertSame(UrlNormalizer::normalize($a), UrlNormalizer::normalize($b));
    }

    public static function sameAs(): array
    {
        $base = 'https://shop.example.com/products/linen-shirt';

        return [
            'www and case' => ['https://WWW.Shop.Example.com/products/linen-shirt', $base],
            'http vs https' => ['http://shop.example.com/products/linen-shirt', $base],
            'trailing slash' => [$base.'/', $base],
            'fragment' => [$base.'#reviews', $base],
            'tracking params' => [$base.'?utm_source=fb&utm_campaign=x&fbclid=abc&gclid=1', $base],
            'shopify variant param' => [$base.'?variant=123456', $base],
            'default port' => ['https://shop.example.com:443/products/linen-shirt', $base],
            'double slash' => ['https://shop.example.com//products//linen-shirt', $base],
            'query order' => ['https://shop.example.com/p?b=2&a=1', 'https://shop.example.com/p?a=1&b=2'],
            'surrounding space' => ["  $base  ", $base],
        ];
    }

    public function test_real_differences_are_kept(): void
    {
        $this->assertNotSame(
            UrlNormalizer::normalize('https://shop.example.com/products/a'),
            UrlNormalizer::normalize('https://shop.example.com/products/b'),
        );
        $this->assertNotSame(
            UrlNormalizer::normalize('https://shop.example.com/p?color=red'),
            UrlNormalizer::normalize('https://shop.example.com/p?color=blue'),
        );
        $this->assertNotSame(
            UrlNormalizer::normalize('https://shop.example.com:8443/p'),
            UrlNormalizer::normalize('https://shop.example.com/p'),
        );
    }

    #[DataProvider('invalid')]
    public function test_rejects_unusable_urls(string $url): void
    {
        $this->assertNull(UrlNormalizer::normalize($url));
    }

    public static function invalid(): array
    {
        return [['not a url'], ['ftp://example.com/x'], ['javascript:alert(1)'], ['//example.com/x'], ['https://'], ['https://localhost/x'], ['example.com/x'], ['']];
    }

    public function test_hash_is_stable_sha256(): void
    {
        $n = UrlNormalizer::normalize('https://shop.example.com/p');
        $this->assertSame(64, strlen(UrlNormalizer::hash($n)));
        $this->assertSame(UrlNormalizer::hash($n), UrlNormalizer::hash($n));
    }
}
