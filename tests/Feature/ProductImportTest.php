<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Product;
use App\Models\ProductImportItem;
use App\Services\ProductImport\ImportProcessor;
use App\Services\ProductImport\PageFetcher;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Runs against the local MySQL test database (shaotal_admin_test); every test is rolled back. HTTP is always faked. */
class ProductImportTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = 'https://shop.example.com/products/premium-trouser-escape';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->actingAs(AdminUser::firstOrFail(), 'admin');
        $this->app->bind(PageFetcher::class, fn () => new PageFetcher(fn ($host) => match ($host) {
            'internal.example.com' => ['10.0.0.5'],
            'loopback.example.com' => ['127.0.0.1'],
            'nowhere.example.com' => [],
            default => ['93.184.216.34'],
        }));
    }

    private function page(string $name = 'Premium Trouser - Escape'): string
    {
        $ld = json_encode(['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $name, 'sku' => 'IMPORT-TEST-SKU-1',
            'image' => ['https://cdn.example.com/a.jpg'], 'offers' => ['@type' => 'Offer', 'price' => '14.99', 'priceCurrency' => 'AUD']]);

        return "<html><head><script type=\"application/ld+json\">$ld</script></head><body></body></html>";
    }

    private function submit(string $urls, array $extra = [])
    {
        return $this->post(route('product-imports.store'), ['urls' => $urls] + $extra);
    }

    private function item(string $url = self::URL): ProductImportItem
    {
        return ProductImportItem::where('source_url', $url)->firstOrFail();
    }

    // ---- submission & duplicates -------------------------------------------------------------

    public function test_submitting_urls_queues_one_item_per_unique_page(): void
    {
        $before = ProductImportItem::count();
        $this->submit(implode("\n", [
            self::URL,
            'https://www.shop.example.com/products/premium-trouser-escape/?utm_source=x#top', // same page
            'https://shop.example.com/products/other-item',
            'not a url',
            '',
        ]))->assertRedirect();

        $this->assertSame($before + 2, ProductImportItem::count());
        $this->assertSame('queued', $this->item()->status);
        $this->assertSame('https://shop.example.com/products/premium-trouser-escape', $this->item()->normalized_url);
        $this->assertSame(64, strlen($this->item()->url_hash));
        $this->assertSame(auth('admin')->id(), $this->item()->created_by);
    }

    public function test_resubmitting_an_imported_url_is_skipped_and_reported(): void
    {
        $this->submit(self::URL);
        $count = ProductImportItem::count();

        $this->from(route('product-imports.create'))
            ->submit('http://WWW.shop.example.com/products/premium-trouser-escape?fbclid=1')
            ->assertRedirect(route('product-imports.create'))
            ->assertSessionHasErrors('urls')
            ->assertSessionHas('import_result', fn ($r) => count($r['skipped']) === 1 && $r['created'] === 0);

        $this->assertSame($count, ProductImportItem::count());
    }

    public function test_mixed_submit_creates_only_the_new_urls_and_lists_the_skipped_ones(): void
    {
        $this->submit(self::URL);
        $this->submit(self::URL."\nhttps://shop.example.com/products/new-one")
            ->assertSessionHas('import_result', fn ($r) => $r['created'] === 1 && count($r['skipped']) === 1);

        $this->assertSame(1, ProductImportItem::where('source_url', 'https://shop.example.com/products/new-one')->count());
    }

    public function test_url_hash_is_unique_in_the_database(): void
    {
        $this->submit(self::URL);
        $item = $this->item();

        $this->expectException(QueryException::class);
        DB::table('product_import_items')->insert(['batch_id' => $item->batch_id, 'source_url' => 'x', 'normalized_url' => 'x', 'url_hash' => $item->url_hash]);
    }

    public function test_validation_and_limits(): void
    {
        $this->submit('')->assertSessionHasErrors('urls');
        $this->submit(implode("\n", array_map(fn ($i) => "https://shop.example.com/p/$i", range(1, 201))))->assertSessionHasErrors('urls');
        $this->assertSame(0, ProductImportItem::where('source_url', 'like', 'https://shop.example.com/p/%')->count());
    }

    // ---- fetching & extraction ---------------------------------------------------------------

    public function test_processing_extracts_and_stores_data_without_touching_products(): void
    {
        Http::fake([self::URL => Http::response($this->page(), 200, ['Content-Type' => 'text/html; charset=utf-8'])]);
        $this->submit(self::URL);
        $products = Product::count();

        $this->assertTrue(app(ImportProcessor::class)->process($this->item()->id));

        $item = $this->item()->fresh();
        $this->assertSame('needs_review', $item->status);
        $this->assertSame(1, $item->attempts);
        $this->assertNull($item->last_error);
        $this->assertSame('Premium Trouser - Escape', $item->raw_data['name']);
        $this->assertSame('14.99', $item->raw_data['price']);
        $this->assertSame(['https://cdn.example.com/a.jpg'], $item->source_images);
        $this->assertNull($item->product_id);
        $this->assertSame($products, Product::count(), 'Phase 1 must not create products');
    }

    public function test_an_existing_product_name_marks_the_item_duplicate(): void
    {
        $name = Product::value('name');
        Http::fake([self::URL => Http::response($this->page($name), 200, ['Content-Type' => 'text/html'])]);
        $this->submit(self::URL);
        app(ImportProcessor::class)->process($this->item()->id);

        $item = $this->item()->fresh();
        $this->assertSame('duplicate', $item->status, 'a name already in the catalogue blocks saving');
        $this->assertStringContainsString('already exists', implode(' ', $item->raw_data['warnings']));
    }

    public function test_page_without_product_data_fails_permanently(): void
    {
        Http::fake([self::URL => Http::response('<html><body>Hello</body></html>', 200, ['Content-Type' => 'text/html'])]);
        $this->submit(self::URL);
        app(ImportProcessor::class)->process($this->item()->id);

        $item = $this->item()->fresh();
        $this->assertSame('failed', $item->status);
        $this->assertStringContainsString('No product data', $item->last_error);
        $this->assertSame(1, $item->attempts);
    }

    public function test_404_fails_without_retrying(): void
    {
        Http::fake([self::URL => Http::response('nope', 404)]);
        $this->submit(self::URL);
        app(ImportProcessor::class)->process($this->item()->id);

        $this->assertSame('failed', $this->item()->fresh()->status);
        $this->assertStringContainsString('404', $this->item()->fresh()->last_error);
    }

    // ---- retries -------------------------------------------------------------------------------

    public function test_server_errors_back_off_then_fail_after_three_attempts(): void
    {
        Http::fake([self::URL => Http::response('busy', 503)]);
        $this->submit(self::URL);
        $processor = app(ImportProcessor::class);

        $processor->process($this->item()->id);
        $item = $this->item()->fresh();
        $this->assertSame('queued', $item->status);
        $this->assertSame(1, $item->attempts);
        $this->assertTrue($item->next_attempt_at->isFuture());
        $this->assertStringContainsString('503', $item->last_error);
        $this->assertNotContains($item->id, $processor->due(50)->all(), 'not due during back-off');

        $item->update(['next_attempt_at' => now()->subMinute()]);
        $this->assertContains($item->id, $processor->due(50)->all());
        $processor->process($item->id);
        $item->update(['next_attempt_at' => now()->subMinute()]);
        $processor->process($item->id);

        $item = $item->fresh();
        $this->assertSame('failed', $item->status);
        $this->assertSame(3, $item->attempts);
        $this->assertNull($item->next_attempt_at);
    }

    public function test_manual_retry_resets_attempts_and_can_succeed(): void
    {
        Http::fake([self::URL => Http::sequence()->push('nope', 404)->push($this->page(), 200, ['Content-Type' => 'text/html'])]);
        $this->submit(self::URL);
        app(ImportProcessor::class)->process($this->item()->id);
        $this->assertSame('failed', $this->item()->fresh()->status);

        $this->post(route('product-imports.run', $this->item()))->assertRedirect()->assertSessionHas('success');

        $item = $this->item()->fresh();
        $this->assertSame('needs_review', $item->status);
        $this->assertSame(1, $item->attempts, 'attempts restart on a manual retry');
        $this->assertNull($item->last_error);
    }

    public function test_an_item_already_claimed_is_not_processed_twice(): void
    {
        Http::fake([self::URL => Http::response($this->page(), 200, ['Content-Type' => 'text/html'])]);
        $this->submit(self::URL);
        $id = $this->item()->id;
        $processor = app(ImportProcessor::class);

        $this->assertTrue($processor->process($id));
        $this->assertFalse($processor->process($id), 'already extracted: nothing to claim');
        Http::assertSentCount(1);

        $item = $this->item()->fresh();
        $item->update(['status' => 'processing', 'locked_at' => now()]);
        $this->assertFalse($processor->process($id), 'fresh lock held by another worker');
        $item->update(['locked_at' => now()->subMinutes(30)]);
        $this->assertTrue($processor->process($id), 'a crashed run is picked up after the lock goes stale');
    }

    // ---- SSRF protection -----------------------------------------------------------------------

    public function test_internal_and_unresolvable_hosts_are_never_requested(): void
    {
        Http::fake();
        $this->submit("https://internal.example.com/p/1\nhttps://loopback.example.com/p/2\nhttps://nowhere.example.com/p/3\nhttp://169.254.169.254/latest/meta-data");
        $processor = app(ImportProcessor::class);
        foreach (ProductImportItem::whereIn('source_url', [
            'https://internal.example.com/p/1', 'https://loopback.example.com/p/2', 'https://nowhere.example.com/p/3',
        ])->get() as $item) {
            $processor->process($item->id);
        }

        Http::assertNothingSent();
        $this->assertSame('failed', ProductImportItem::where('source_url', 'https://internal.example.com/p/1')->value('status'));
        $this->assertSame('failed', ProductImportItem::where('source_url', 'https://loopback.example.com/p/2')->value('status'));
        $this->assertStringContainsString('not allowed', ProductImportItem::where('source_url', 'https://internal.example.com/p/1')->value('last_error'));
        $this->assertSame('queued', ProductImportItem::where('source_url', 'https://nowhere.example.com/p/3')->value('status'), 'DNS failure is retryable');
    }

    public function test_redirect_to_an_internal_address_is_blocked(): void
    {
        Http::fake([
            self::URL => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data']),
            '169.254.169.254/*' => Http::response('secret', 200),
        ]);
        $this->submit(self::URL);
        app(ImportProcessor::class)->process($this->item()->id);

        $this->assertSame('failed', $this->item()->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_redirect_to_a_public_page_is_followed(): void
    {
        Http::fake([
            self::URL => Http::response('', 301, ['Location' => '/products/moved']),
            'shop.example.com/products/moved' => Http::response($this->page(), 200, ['Content-Type' => 'text/html']),
        ]);
        $this->submit(self::URL);
        app(ImportProcessor::class)->process($this->item()->id);

        $item = $this->item()->fresh();
        $this->assertSame('needs_review', $item->status);
        $this->assertSame('https://shop.example.com/products/moved', $item->raw_data['final_url']);
    }

    public function test_non_html_responses_are_rejected(): void
    {
        Http::fake([self::URL => Http::response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf'])]);
        $this->submit(self::URL);
        app(ImportProcessor::class)->process($this->item()->id);

        $this->assertSame('failed', $this->item()->fresh()->status);
    }

    // ---- UI -------------------------------------------------------------------------------------

    public function test_pages_render(): void
    {
        Http::fake([self::URL => Http::response($this->page(), 200, ['Content-Type' => 'text/html'])]);
        $this->get(route('product-imports.index'))->assertOk();
        $this->get(route('product-imports.create'))->assertOk()->assertSee('Product URLs');

        $this->submit(self::URL)->assertRedirect();
        $item = $this->item();
        $this->get(route('product-imports.show', $item->batch_id))->assertOk()->assertSee('Fetch now');
        app(ImportProcessor::class)->process($item->id);
        $this->get(route('product-imports.show', $item->batch_id))->assertOk()->assertSee('Premium Trouser - Escape');
        $this->get(route('product-imports.item', $item))->assertOk()->assertSee('IMPORT-TEST-SKU-1')->assertSee('14.99');
        $this->get(route('product-imports.index'))->assertOk()->assertSee('Needs review 1');
    }

    public function test_guests_cannot_reach_the_importer(): void
    {
        auth('admin')->logout();
        $this->get(route('product-imports.index'))->assertRedirect(route('login'));
        $this->post(route('product-imports.store'), ['urls' => self::URL])->assertRedirect(route('login'));
    }
}
