<?php

namespace Tests\Unit;

use App\Services\ProductImport\ProductExtractor;
use PHPUnit\Framework\TestCase;

/** Fixtures in tests/Fixtures are trimmed copies of two live fabrilife.com product pages. */
class FabrilifeAdapterTest extends TestCase
{
    private const CHALKSTONE = 'https://fabrilife.com/product/74427-mens-premium-casual-shirt-chalkstone';

    private const SHOCKWAVE = 'https://fabrilife.com/product/73842-teens-boys-premium-trouser-shockwave';

    private function extract(string $fixture, string $url): array
    {
        return (new ProductExtractor)->extract(file_get_contents(__DIR__.'/../Fixtures/fabrilife-'.$fixture.'.html'), $url);
    }

    public function test_chalkstone_shirt_is_extracted_completely(): void
    {
        $r = $this->extract('chalkstone', self::CHALKSTONE);

        $this->assertSame(['fabrilife'], $r['extracted_via']);
        $this->assertSame('Mens Premium Casual Shirt - Chalkstone', $r['name'], 'no " | Fabrilife" suffix from the SEO title');
        $this->assertSame('74427', $r['source_id']);
        $this->assertSame('Mens > Shirt > Casual Shirt', $r['category_hint']);
        $this->assertSame('Fabrilife', $r['brand']);
        $this->assertNull($r['sku'], 'the shop id is a reference, not a Shaotal SKU');
        $this->assertSame(['S', 'M', 'L', 'XL', '2XL'], $r['sizes'], 'only this product\'s sizes, not another product\'s XXXL');
        $this->assertSame(['Cream'], $r['colors']);
    }

    public function test_prices_stay_in_bdt_and_are_not_converted(): void
    {
        $r = $this->extract('chalkstone', self::CHALKSTONE);

        $this->assertSame('BDT', $r['currency']);
        $this->assertSame('2090.00', $r['price']);
        $this->assertSame('2800.00', $r['list_price']);
        $this->assertContains('Source price is in BDT. Enter the A$ price manually; no automatic conversion is applied.', $r['warnings']);
    }

    public function test_full_description_is_kept_not_the_seo_boilerplate(): void
    {
        $r = $this->extract('chalkstone', self::CHALKSTONE);

        $this->assertStringStartsWith('<p>Chalkstone is the shirt that works without trying', $r['description_html']);
        $this->assertStringContainsString('<li><strong>Weight:</strong> 148 GSM', $r['description_html']);
        $this->assertStringContainsString('Fabric &amp; Composition', $r['description_html']);
        $this->assertStringContainsString('The kind of shirt that never needs an occasion', $r['description']);
        $this->assertStringNotContainsString('Shop Best Quality', $r['description']);
        $this->assertStringNotContainsString('Size chart', $r['description'], 'size chart is separate from the description');
        $this->assertStringNotContainsString('class=', $r['description_html']);
    }

    public function test_spec_fields_come_only_from_labelled_values(): void
    {
        $r = $this->extract('chalkstone', self::CHALKSTONE);

        $this->assertSame('100% cotton', $r['spec']['material']);
        $this->assertSame('Satin stripe — woven with a fine raised stripe that adds quiet texture and a soft lustre', $r['spec']['fabric']);
        $this->assertArrayNotHasKey('pattern', $r['spec'], 'not stated on the page, so left for manual entry');
        $this->assertArrayNotHasKey('care_instructions', $r['spec']);
        $this->assertArrayNotHasKey('dimensions', $r['spec']);
        $this->assertSame('Cream', $r['attributes']['colour']);
        $this->assertStringContainsString('148 GSM', $r['attributes']['weight']);
    }

    public function test_all_gallery_images_are_found_and_menu_images_are_ignored(): void
    {
        $r = $this->extract('chalkstone', self::CHALKSTONE);

        $this->assertSame([
            'https://fabrilife.com/products/6ad45e1284e40-square.jpg',
            'https://fabrilife.com/products/6ad45e12d850b-square.jpg',
        ], array_column($r['images'], 'url'));
    }

    public function test_size_chart_tables_are_captured_in_both_units(): void
    {
        $r = $this->extract('chalkstone', self::CHALKSTONE);

        $this->assertSame(['Size', 'Collar', 'Chest', 'Length', 'Sleeve'], $r['size_chart']['inch']['headers']);
        $this->assertSame(['S', '5.9', '15', '11', '9.3'], $r['size_chart']['inch']['rows'][0]);
        $this->assertCount(5, $r['size_chart']['cm']['rows']);
    }

    public function test_second_layout_with_nested_paragraphs_and_a_bold_fit_phrase(): void
    {
        $r = $this->extract('shockwave', self::SHOCKWAVE);

        $this->assertSame('Teens Boys Premium Trouser - Shockwave', $r['name']);
        $this->assertSame('1390.00', $r['price']);
        $this->assertSame('1500.00', $r['list_price']);
        $this->assertSame(['S', 'M', 'L'], $r['sizes']);
        $this->assertSame([], $r['colors'], 'this page states no colour; nothing is invented');
        $this->assertSame('Premium Terry', $r['spec']['fabric']);
        $this->assertSame('Regular Fit', $r['spec']['fit']);
        $this->assertArrayNotHasKey('material', $r['spec']);
        $this->assertSame('Teens > Boys > Sports Trouser', $r['category_hint']);
        $this->assertCount(2, $r['images']);
        $this->assertSame(['Size', 'Waist', 'Thigh (round)', 'Length'], $r['size_chart']['inch']['headers']);
    }

    /**
     * Comparison with live product 41 ("Premium Trouser - Escape"), whose source page this fixture was trimmed from.
     * Baseline values were read once from the shared DB (SELECT only): sizes 2XL/L/M/XL, specification material
     * "100% Cotton", and a description that begins with the sentences asserted below. The test never touches a database.
     */
    public function test_out_of_stock_escape_page_matches_the_product_41_baseline(): void
    {
        $r = $this->extract('escape', 'https://fabrilife.com/product/72075-mens-premium-trouser-escape');

        $this->assertSame('Mens Premium Trouser - Escape', $r['name']);
        $this->assertEqualsCanonicalizing(['2XL', 'L', 'M', 'XL'], $r['sizes'], 'same sizes as product 41');
        $this->assertSame('100% Cotton', $r['spec']['material'], 'same material as product 41');
        $this->assertSame('Plain fabric', $r['spec']['fabric']);
        $this->assertArrayNotHasKey('fit', $r['spec'], 'product 41 has "Regular Fit", but the page does not state a fit');
        $this->assertSame('OutOfStock', $r['availability']);
        $this->assertSame('990.00', $r['price']);
        $this->assertSame('1290.00', $r['list_price']);
        $this->assertStringStartsWith('Ideal for cozy evenings and year-round lounging and sleeping. Two pockets are featured for convenience, as is a drawstring for a great fit. The waistband is made', $r['description']);
        $this->assertStringContainsString('Detailed Description', $r['description']);
        $this->assertSame('110-125', $r['attributes']['g.s.m']);
        $this->assertSame('40/40', $r['attributes']['yarn count']);
        $this->assertContains('No size picker on the source page; sizes were taken from its size chart.', $r['warnings']);
        $this->assertContains('This product is out of stock on the source site. Enter your own stock quantities.', $r['warnings']);
        $this->assertCount(1, $r['images']);
    }

    public function test_a_non_product_fabrilife_page_falls_back_to_generic_extraction(): void
    {
        $r = (new ProductExtractor)->extract('<html><head><meta property="og:title" content="Offers"></head><body></body></html>', 'https://fabrilife.com/offers');

        $this->assertSame('Offers', $r['name']);
        $this->assertNotContains('fabrilife', $r['extracted_via']);
    }
}
