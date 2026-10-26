<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImportItem;
use App\Models\Subcategory;
use App\Services\CloudinaryImageService;
use App\Services\ProductImport\DuplicateGuard;
use App\Services\ProductImport\ImportProcessor;
use App\Services\ProductImport\PageFetcher;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Review, approve, draft and publish workflow. Local test DB only (rolled back); HTTP and Cloudinary are faked. */
class ProductImportReviewTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = 'https://fabrilife.com/product/74427-mens-premium-casual-shirt-chalkstone';

    private const IMG1 = 'https://fabrilife.com/products/6ad45e1284e40-square.jpg';

    private const CLOUD = 'https://res.cloudinary.com/demo/image/upload/v1/shaotal/products/test.jpg';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->actingAs(AdminUser::firstOrFail(), 'admin');
        config(['cloudinary.cloud_name' => 'demo', 'cloudinary.api_key' => 'k', 'cloudinary.api_secret' => 's', 'services.gemini.key' => null]);
        $this->app->bind(PageFetcher::class, fn () => new PageFetcher(fn () => ['93.184.216.34']));
        $this->app->instance(CloudinaryImageService::class, new CloudinaryImageService(
            fn ($path, $opts) => ['secure_url' => 'https://res.cloudinary.com/demo/image/upload/v1/'.$opts['folder'].'/'.md5_file($path).'.jpg']
        ));
    }

    // ---------------------------------------------------------------- helpers

    private function png(int $w = 300, int $h = 300, bool $plain = true): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, 0xFFFFFF);
        if (! $plain) {
            for ($i = 0; $i < 4000; $i++) {
                imagesetpixel($im, random_int(0, $w - 1), random_int(0, $h - 1), random_int(0, 0xFFFFFF));
            }
            imagefilledrectangle($im, 0, 0, $w, 12, 0x223344);
            imagefilledrectangle($im, 0, $h - 12, $w, $h, 0x992233);
            imagefilledrectangle($im, 0, 0, 12, $h, 0x118833);
            imagefilledrectangle($im, $w - 12, 0, $w, $h, 0xCC8800);
        }
        imagefilledrectangle($im, 100, 60, 200, 240, 0xCC2222);
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    private function extracted(string $fixture = 'chalkstone', string $url = self::URL): ProductImportItem
    {
        Http::fake([$url => Http::response(file_get_contents(base_path("tests/Fixtures/fabrilife-$fixture.html")), 200, ['Content-Type' => 'text/html; charset=utf-8'])]);
        $this->post(route('product-imports.store'), ['urls' => $url]);
        $item = ProductImportItem::where('source_url', $url)->firstOrFail();
        app(ImportProcessor::class)->process($item->id);

        return $item->fresh();
    }

    /** What the product form would post for this draft once an admin has filled in the manual fields. */
    private function payload(ProductImportItem $item, array $over = [], string $action = 'draft'): array
    {
        $d = $item->draft_data;
        $d['category_id'] = $d['category_id'] ?: Category::value('id');
        $d['price'] = '49.99';
        $d['images'] = [['url' => self::CLOUD]];
        $d['main_key'] = 0;
        $d['variants'] = $this->stocked($item, 5);
        $d['sku'] = 'IMPTEST-1';
        $d['import_action'] = $action;

        return array_merge($d, $over);
    }

    /** The draft's variants with a stock quantity filled in (colour/size links kept). */
    private function stocked(ProductImportItem $item, int|string $qty): array
    {
        return array_map(fn ($v) => ['stock_qty' => $qty] + $v, $item->draft_data['variants']);
    }

    private function save(ProductImportItem $item, array $payload)
    {
        return $this->post(route('product-imports.save', $item), $payload);
    }

    // ---------------------------------------------------------------- extraction -> draft

    public function test_extraction_builds_an_editable_draft_with_only_stated_values(): void
    {
        $item = $this->extracted();
        $d = $item->draft_data;

        $this->assertSame('needs_review', $item->status);
        $this->assertSame('Mens Premium Casual Shirt - Chalkstone', $d['name']);
        $this->assertSame('', $d['price'], 'BDT is never converted into an A$ price');
        $this->assertSame('', $d['selling_price']);
        $this->assertSame('', $d['sku']);
        $this->assertSame('', $d['short_description']);
        $this->assertSame('', $d['seo_title']);
        $this->assertSame('100% cotton', $d['spec']['material']);
        $this->assertSame('', $d['spec']['pattern'], 'not on the page, so left blank');
        $this->assertSame(['S', 'M', 'L', 'XL', '2XL'], array_column($d['sizes'], 'size_label'));
        $this->assertSame('Cream', $d['colors'][0]['name']);
        $this->assertSame('color_size', $d['variant_scenario']);
        $this->assertCount(5, $d['variants']);
        $this->assertSame('', $d['variants'][0]['stock_qty'], 'stock is not on the page');
        $this->assertSame([], $d['images'], 'no image is attached until one is approved');
        $this->assertStringContainsString('Fabric &amp; Composition', $d['description']);
    }

    public function test_an_unambiguous_category_name_in_the_source_is_mapped(): void
    {
        $cat = Category::firstOrFail();
        $sub = Subcategory::create(['category_id' => $cat->id, 'name' => 'Casual Shirt', 'slug' => 'zz-casual-shirt-test', 'sort_order' => 0, 'status' => 'active']);

        $d = $this->extracted()->draft_data;

        $this->assertSame($sub->id, $d['subcategory_id']);
        $this->assertSame($cat->id, $d['category_id']);
    }

    // ---------------------------------------------------------------- review page

    public function test_review_page_shows_all_fields_the_manual_checklist_and_the_four_actions(): void
    {
        $item = $this->extracted();

        $this->get(route('product-imports.review', $item))->assertOk()
            ->assertSee('Review Import: Mens Premium Casual Shirt - Chalkstone', false)
            ->assertSee('Imported from fabrilife.com')
            ->assertSee('BDT 2090.00')->assertSee('No exchange rate is applied')
            ->assertSee('A$ selling price')->assertSee('Stock per variant')->assertSee('Approved product images')
            ->assertSee('name="spec[material]"', false)->assertSee('name="spec[care_instructions]"', false)
            ->assertSee('name="seo_title"', false)->assertSee('name="selling_price"', false)->assertSee('name="brand_id"', false)
            ->assertSee('value="100% cotton"', false)
            ->assertSee('value="progress"', false)->assertSee('value="approve"', false)->assertSee('value="draft"', false)->assertSee('value="publish"', false)
            ->assertSee('Source images (2)')->assertSee(self::IMG1, false)
            ->assertSee('Source size chart')
            ->assertDontSee('Update Product');
    }

    public function test_review_is_unavailable_until_extraction_has_finished(): void
    {
        $this->post(route('product-imports.store'), ['urls' => self::URL]);
        $item = ProductImportItem::where('source_url', self::URL)->firstOrFail();

        $this->get(route('product-imports.review', $item))->assertRedirect(route('product-imports.item', $item));
        $this->save($item, ['import_action' => 'draft', 'name' => 'x'])->assertStatus(409);
    }

    public function test_save_progress_keeps_edits_on_the_import_only(): void
    {
        $item = $this->extracted();
        $products = Product::count();

        $this->save($item, $this->payload($item, ['name' => 'Chalkstone Casual Shirt', 'price' => '55'], 'progress'))->assertSessionHas('success');

        $item->refresh();
        $this->assertSame($products, Product::count(), 'Save Progress never creates a product');
        $this->assertSame('needs_review', $item->status);
        $this->assertSame('Chalkstone Casual Shirt', $item->draft_data['name']);
        $this->get(route('product-imports.review', $item))->assertSee('value="Chalkstone Casual Shirt"', false)->assertSee('value="55"', false);
    }

    // ---------------------------------------------------------------- approve / draft / publish

    public function test_approve_runs_every_publish_check_and_marks_ready(): void
    {
        $item = $this->extracted();

        $this->save($item, $this->payload($item, ['images' => [], 'main_key' => null], 'approve'))->assertSessionHasErrors('images');
        $this->save($item, $this->payload($item, ['price' => '0'], 'approve'))->assertSessionHasErrors('price');
        $this->save($item, $this->payload($item, ['variants' => $this->stocked($item, 0)], 'approve'))->assertSessionHasErrors('stock_qty');
        $this->assertSame('needs_review', $item->fresh()->status);

        $this->save($item, $this->payload($item, [], 'approve'))->assertSessionHasNoErrors();
        $this->assertSame('ready', $item->fresh()->status);
        $this->assertNull($item->fresh()->product_id);

        $this->save($item, $this->payload($item, ['name' => 'Edited after approval'], 'progress'));
        $this->assertSame('needs_review', $item->fresh()->status, 'editing sends it back for approval');
    }

    public function test_save_draft_creates_a_hidden_product_using_the_existing_product_logic(): void
    {
        $item = $this->extracted();
        $before = Product::count();

        $this->save($item, $this->payload($item, ['selling_price' => '39.99']))->assertRedirect(route('product-imports.review', $item))->assertSessionHasNoErrors();

        $item->refresh();
        $p = Product::findOrFail($item->product_id);
        $this->assertSame($before + 1, Product::count());
        $this->assertSame('draft_saved', $item->status);
        $this->assertSame('draft', $p->status);
        $this->assertSame('Mens Premium Casual Shirt - Chalkstone', $p->name);
        $this->assertSame('mens-premium-casual-shirt-chalkstone', $p->slug);
        $this->assertSame('49.99', (string) $p->price);
        $this->assertSame('39.99', (string) $p->selling_price);
        $this->assertSame(1, $p->has_variants);
        $this->assertSame('color_size', $p->variant_scenario);
        $this->assertNull($p->stock_qty);
        $this->assertSame('100% cotton', $p->specification->material);
        $this->assertSame(5, $p->variants()->count());
        $this->assertSame(['S', 'M', 'L', 'XL', '2XL'], $p->sizes()->orderBy('sort_order')->pluck('size_label')->all());
        $this->assertSame(['Cream'], $p->colors()->pluck('name')->all());
        $this->assertSame([self::CLOUD], $p->images()->pluck('image_url')->all());
        $this->assertSame(1, $p->images()->where('is_main', 1)->count());
        $this->assertSame(auth('admin')->id(), $p->created_by);
    }

    public function test_save_draft_accepts_an_incomplete_product_but_publish_does_not(): void
    {
        $item = $this->extracted();
        $incomplete = ['images' => [], 'main_key' => null, 'price' => '', 'variants' => $this->stocked($item, '')];

        $this->save($item, $this->payload($item, $incomplete, 'publish'))->assertSessionHasErrors();
        $this->assertNull($item->fresh()->product_id);

        $this->save($item, $this->payload($item, $incomplete, 'draft'))->assertSessionHasNoErrors();
        $this->assertSame('draft', Product::findOrFail($item->fresh()->product_id)->status);
    }

    public function test_publish_is_a_separate_explicit_action_and_goes_live_only_then(): void
    {
        $item = $this->extracted();
        $this->save($item, $this->payload($item));
        $productId = $item->fresh()->product_id;
        $this->assertSame('draft', Product::find($productId)->status);

        $this->save($item, $this->payload($item, [], 'publish'))->assertSessionHas('success');

        $p = Product::find($productId);
        $this->assertSame('published', $item->fresh()->status);
        $this->assertSame('active', $p->status);
        $this->assertSame('visible', $p->visibility);
        $this->assertSame($productId, $item->fresh()->product_id, 'publishing updates the same product');
    }

    public function test_publish_requires_category_price_stock_and_images(): void
    {
        $item = $this->extracted();
        $products = Product::count();

        $this->save($item, $this->payload($item, ['category_id' => ''], 'publish'))->assertSessionHasErrors('category_id');
        $this->save($item, $this->payload($item, ['price' => '0'], 'publish'))->assertSessionHasErrors('price');
        $this->save($item, $this->payload($item, ['images' => [], 'main_key' => null], 'publish'))->assertSessionHasErrors('images');
        $this->save($item, $this->payload($item, ['variants' => $this->stocked($item, 0)], 'publish'))->assertSessionHasErrors('stock_qty');
        $this->save($item, $this->payload($item, ['images' => [['url' => 'https://evil.example.com/x.jpg']]], 'publish'))->assertSessionHasErrors('images');

        $this->assertSame($products, Product::count());
        $this->assertSame('needs_review', $item->fresh()->status);
    }

    public function test_after_a_draft_exists_the_review_page_edits_that_product_not_a_new_one(): void
    {
        $item = $this->extracted();
        $this->save($item, $this->payload($item));
        $item->refresh();
        $count = Product::count();

        $this->get(route('product-imports.review', $item))->assertOk()->assertSee('Product #'.$item->product_id)->assertDontSee('value="progress"', false);

        $this->save($item, $this->payload($item, ['name' => 'Renamed Chalkstone'], 'draft'))->assertSessionHasNoErrors();
        $this->assertSame($count, Product::count());
        $this->assertSame('Renamed Chalkstone', Product::find($item->product_id)->name);

        $this->save($item, $this->payload($item, [], 'progress'))->assertStatus(409);
    }

    public function test_a_published_item_offers_update_only(): void
    {
        $item = $this->extracted();
        $this->save($item, $this->payload($item, [], 'publish'));
        $item->refresh();
        $this->assertSame('published', $item->status);

        $this->get(route('product-imports.review', $item))->assertSee('value="update"', false)->assertDontSee('value="publish"', false);
        $this->save($item, $this->payload($item, ['short_description' => 'Updated blurb', 'status' => 'active'], 'update'))->assertSessionHas('success');
        $this->assertSame('Updated blurb', Product::find($item->product_id)->short_description);
        $this->assertSame('active', Product::find($item->product_id)->status);
    }

    // ---------------------------------------------------------------- duplicates and product 41

    public function test_a_catalogue_product_with_the_same_name_blocks_everything(): void
    {
        $existing = Product::create(['name' => 'Mens Premium Casual Shirt - Chalkstone', 'slug' => 'zz-existing-chalkstone', 'sku' => 'ZZ-EXISTING', 'category_id' => Category::value('id'), 'price' => 10, 'has_variants' => 0, 'stock_qty' => 1, 'availability' => 'in_stock', 'status' => 'active', 'visibility' => 'visible']);
        $before = $existing->fresh()->toArray();
        $item = $this->extracted();
        $count = Product::count();

        $this->assertSame('duplicate', $item->status);
        $this->get(route('product-imports.review', $item))->assertOk()->assertSee('matching name/SKU already exists')->assertSee('disabled', false);
        foreach (['approve', 'draft', 'publish'] as $action) {
            $this->save($item, $this->payload($item, [], $action))->assertSessionHasErrors('name');
        }
        $this->assertSame($count, Product::count());
        $this->assertSame($before, $existing->fresh()->toArray(), 'the existing product is untouched');

        $this->save($item, $this->payload($item, ['name' => 'Chalkstone Shirt (new)'], 'progress'));
        $this->assertSame('needs_review', $item->fresh()->status, 'a new name clears the duplicate flag');
    }

    public function test_the_product_41_source_page_is_flagged_even_though_the_shop_adds_mens_to_the_name(): void
    {
        $existing = Product::create(['name' => 'Premium Trouser - Escape', 'slug' => 'zz-fl-trouser-escape', 'sku' => 'zz-flt-escape', 'category_id' => Category::value('id'), 'price' => 19.99, 'has_variants' => 0, 'stock_qty' => 3, 'availability' => 'preorder', 'status' => 'active', 'visibility' => 'visible']);
        $before = $existing->fresh()->toArray();
        $item = $this->extracted('escape', 'https://fabrilife.com/product/72075-mens-premium-trouser-escape');

        $this->assertSame('Mens Premium Trouser - Escape', $item->draft_data['name']);
        $this->assertSame('duplicate', $item->status);
        $this->assertStringContainsString('#'.$existing->id, implode(' ', $item->raw_data['warnings']));
        $count = Product::count();
        foreach (['draft', 'publish'] as $action) {
            $this->save($item, $this->payload($item, [], $action))->assertSessionHasErrors('name');
        }
        $this->assertSame($count, Product::count());
        $this->assertSame($before, $existing->fresh()->toArray());
    }

    public function test_similar_but_different_names_are_not_treated_as_duplicates(): void
    {
        Product::create(['name' => 'Premium Trouser - Escapade', 'slug' => 'zz-escapade', 'category_id' => Category::value('id'), 'price' => 10, 'has_variants' => 0, 'stock_qty' => 1, 'availability' => 'in_stock', 'status' => 'draft', 'visibility' => 'visible']);

        $this->assertNull(DuplicateGuard::find("Men's Premium Trouser - Escape", null));
        $this->assertNotNull(DuplicateGuard::find('Premium Trouser - Escapade', null));
        $this->assertNotNull(DuplicateGuard::find('Womens premium trouser escapade', null));
    }

    public function test_an_existing_sku_blocks_saving(): void
    {
        $existing = Product::create(['name' => 'Some Other Product', 'slug' => 'zz-other', 'sku' => 'TAKEN-SKU', 'category_id' => Category::value('id'), 'price' => 10, 'has_variants' => 0, 'stock_qty' => 1, 'availability' => 'in_stock', 'status' => 'draft', 'visibility' => 'visible']);
        $item = $this->extracted();

        $this->save($item, $this->payload($item, ['sku' => 'TAKEN-SKU']))->assertSessionHasErrors('name');
        $this->assertSame('Some Other Product', $existing->fresh()->name);
    }

    public function test_product_41_is_never_overwritten_even_if_an_item_is_pointed_at_it(): void
    {
        // Stand-in for the live baseline product, created before the import item exists.
        DB::table('products')->where('id', 41)->delete();
        DB::table('products')->insert(['id' => 41, 'name' => 'Premium Trouser - Escape', 'slug' => 'fl-trouser-escape', 'sku' => 'flt-escape', 'category_id' => Category::value('id'),
            'price' => 19.99, 'selling_price' => 14.99, 'has_variants' => 0, 'stock_qty' => 3, 'availability' => 'preorder', 'status' => 'active', 'visibility' => 'visible', 'created_at' => now()->subYear(), 'updated_at' => now()->subYear()]);
        $before = (array) DB::table('products')->where('id', 41)->first();

        $item = $this->extracted();
        $item->update(['product_id' => 41]); // tampered link

        $this->get(route('product-imports.review', $item))->assertStatus(409);
        foreach (['draft', 'publish', 'update'] as $action) {
            $this->save($item, $this->payload($item, ['name' => 'Hijacked'], $action))->assertStatus(409);
        }
        $this->assertSame($before, (array) DB::table('products')->where('id', 41)->first());
    }

    public function test_the_same_source_url_cannot_create_a_second_product(): void
    {
        $item = $this->extracted();
        $this->save($item, $this->payload($item));
        $count = Product::count();

        $this->post(route('product-imports.store'), ['urls' => self::URL.'?utm_source=again'])->assertSessionHasErrors('urls');
        $this->assertSame($count, Product::count());
        $this->assertSame(1, ProductImportItem::where('url_hash', $item->url_hash)->count());
    }

    // ---------------------------------------------------------------- batch page

    public function test_batch_page_shows_status_summary_and_review_buttons(): void
    {
        $item = $this->extracted();

        $this->get(route('product-imports.show', $item->batch_id))->assertOk()
            ->assertSee('Needs review: 1')->assertSee('Review')->assertSee(route('product-imports.review', $item), false);

        $this->save($item, $this->payload($item));
        $this->get(route('product-imports.show', $item->batch_id))->assertSee('Draft saved: 1')->assertSee('#'.$item->fresh()->product_id);
    }

    // ---------------------------------------------------------------- images

    public function test_original_image_is_downloaded_and_uploaded_to_cloudinary(): void
    {
        $item = $this->extracted();
        Http::fake([self::IMG1 => Http::response($this->png(), 200, ['Content-Type' => 'image/png'])]);

        $res = $this->postJson(route('product-imports.images.original', $item), ['url' => self::IMG1])->assertOk();

        $this->assertStringStartsWith('https://res.cloudinary.com/demo/image/upload/v1/shaotal/products/', $res->json('url'));
        $this->assertSame(self::IMG1, $item->fresh()->generated_images[0]['source']);
        $this->assertSame('original', $item->fresh()->generated_images[0]['mode']);
    }

    public function test_only_extracted_source_images_can_be_fetched(): void
    {
        $item = $this->extracted();
        Http::fake();

        $this->postJson(route('product-imports.images.original', $item), ['url' => 'https://evil.example.com/a.png'])->assertStatus(422);
        $this->postJson(route('product-imports.images.generate', $item), ['url' => 'http://169.254.169.254/x', 'mode' => 'local'])->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_non_image_source_files_are_refused(): void
    {
        $item = $this->extracted();
        Http::fake([self::IMG1 => Http::response('<html>not an image</html>', 200, ['Content-Type' => 'image/jpeg'])]);

        $this->postJson(route('product-imports.images.original', $item), ['url' => self::IMG1])->assertStatus(422)->assertJsonPath('message', 'The source file is not a JPG, PNG, WEBP or GIF image.');
    }

    public function test_quick_clean_previews_then_uploads_only_after_approval(): void
    {
        $item = $this->extracted();
        Http::fake([self::IMG1 => Http::response($this->png(), 200, ['Content-Type' => 'image/png'])]);

        $gen = $this->postJson(route('product-imports.images.generate', $item), ['url' => self::IMG1, 'mode' => 'local'])->assertOk();
        $this->assertNull($item->fresh()->generated_images, 'nothing is uploaded before approval');

        $preview = $this->get($gen->json('preview_url'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $im = imagecreatefromstring($preview->getContent());
        $this->assertSame([1600, 1600], [imagesx($im), imagesy($im)]);
        $corner = imagecolorat($im, 4, 4);
        foreach ([($corner >> 16) & 255 => 0xF5, ($corner >> 8) & 255 => 0xED, $corner & 255 => 0xE3] as $got => $want) {
            $this->assertEqualsWithDelta($want, $got, 3, 'corner is Shaotal beige #F5EDE3');
        }
        $mid = imagecolorat($im, 800, 800);
        $this->assertGreaterThan(150, ($mid >> 16) & 255, 'the red product is kept');
        $this->assertLessThan(80, $mid & 255);

        $this->postJson(route('product-imports.images.approve', $item), ['preview_id' => $gen->json('preview_id')])->assertOk()->assertJsonStructure(['url']);
        $this->assertSame('local', $item->fresh()->generated_images[0]['mode']);
        $this->postJson(route('product-imports.images.approve', $item), ['preview_id' => $gen->json('preview_id')])->assertStatus(404);
    }

    public function test_quick_clean_refuses_busy_backgrounds_instead_of_guessing(): void
    {
        $item = $this->extracted();
        Http::fake([self::IMG1 => Http::response($this->png(400, 400, false), 200, ['Content-Type' => 'image/png'])]);

        $this->postJson(route('product-imports.images.generate', $item), ['url' => self::IMG1, 'mode' => 'local'])
            ->assertStatus(422)->assertJsonPath('message', 'This photo has no plain background to replace. Use AI generation or the original.');
    }

    public function test_ai_generation_needs_a_gemini_key_and_never_leaks_it(): void
    {
        $item = $this->extracted();
        Http::fake([self::IMG1 => Http::response($this->png(), 200, ['Content-Type' => 'image/png'])]);

        $this->postJson(route('product-imports.images.generate', $item), ['url' => self::IMG1, 'mode' => 'gemini'])
            ->assertStatus(422)->assertJsonPath('message', 'Gemini is not configured. Add GEMINI_API_KEY to .env.');

        config(['services.gemini.key' => 'secret-test-key']);
        Http::fake([
            self::IMG1 => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
            'generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'done'], ['inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode($this->png(512, 512))]]]]]]]),
        ]);
        $res = $this->postJson(route('product-imports.images.generate', $item), ['url' => self::IMG1, 'mode' => 'gemini'])->assertOk();

        $this->assertStringNotContainsString('secret-test-key', $res->getContent());
        Http::assertSent(fn ($r) => str_contains($r->url(), 'gemini-2.5-flash-image:generateContent')
            && $r->header('x-goog-api-key') === ['secret-test-key']
            && str_contains($r['contents'][0]['parts'][0]['text'], '#F5EDE3')
            && $r['contents'][0]['parts'][1]['inline_data']['mime_type'] === 'image/png'
            && ! str_contains($r->url(), 'secret-test-key'));
        $this->get($res->json('preview_url'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_gemini_failures_are_reported_without_a_preview(): void
    {
        config(['services.gemini.key' => 'secret-test-key']);
        $item = $this->extracted();
        Http::fake([
            self::IMG1 => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
            'generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['finishReason' => 'IMAGE_SAFETY', 'content' => ['parts' => [['text' => 'sorry']]]]]]),
        ]);

        $this->postJson(route('product-imports.images.generate', $item), ['url' => self::IMG1, 'mode' => 'gemini'])
            ->assertStatus(422)->assertJsonPath('message', 'Gemini did not return an image (IMAGE_SAFETY). Try again or use the original.');
    }

    public function test_unapproved_previews_are_pruned_after_a_day_and_only_the_same_admin_can_approve(): void
    {
        $item = $this->extracted();
        Http::fake([self::IMG1 => Http::response($this->png(), 200, ['Content-Type' => 'image/png'])]);

        $first = $this->postJson(route('product-imports.images.generate', $item), ['url' => self::IMG1, 'mode' => 'local'])->json('preview_id');
        $file = storage_path("app/private/import-previews/{$item->id}/$first.jpg");
        $this->assertFileExists($file);
        touch($file, time() - 90000);

        $second = $this->postJson(route('product-imports.images.generate', $item), ['url' => self::IMG1, 'mode' => 'local'])->json('preview_id');
        $this->assertFileDoesNotExist($file, 'the day-old preview was pruned');
        $this->assertFileExists(storage_path("app/private/import-previews/{$item->id}/$second.jpg"));

        $sidecar = storage_path("app/private/import-previews/{$item->id}/$second.json");
        file_put_contents($sidecar, json_encode(['source' => self::IMG1, 'mode' => 'local', 'admin' => -1]));
        $this->postJson(route('product-imports.images.approve', $item), ['preview_id' => $second])->assertStatus(404);

        @unlink(storage_path("app/private/import-previews/{$item->id}/$second.jpg"));
        @unlink($sidecar);
    }

    public function test_preview_files_are_scoped_to_their_item_and_id_format(): void
    {
        $item = $this->extracted();
        $this->get(route('product-imports.images.preview', [$item, '../../.env']))->assertNotFound();
        $this->get(route('product-imports.images.preview', [$item, '11111111-1111-1111-1111-111111111111']))->assertNotFound();
    }
}
