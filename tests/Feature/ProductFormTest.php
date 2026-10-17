<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Product;
use App\Services\CloudinaryImageService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Runs against the local MySQL test database (shaotal_admin_test); every test is rolled back. */
class ProductFormTest extends TestCase
{
    use DatabaseTransactions;

    private function base(array $over = []): array
    {
        return array_merge([
            'name' => 'QA Linen Shirt', 'category_id' => 1, 'price' => 50, 'stock_qty' => 5,
            'availability' => 'in_stock', 'visibility' => 'visible', 'status' => 'active',
        ], $over);
    }

    private function url(string $n): string
    {
        config(['cloudinary.cloud_name' => 'demo']);

        return "https://res.cloudinary.com/demo/image/upload/v1/shaotal/products/$n.jpg";
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->actingAs(AdminUser::firstOrFail(), 'admin');
    }

    public function test_create_page_and_edit_page_render(): void
    {
        $this->get('/products/create')->assertOk()->assertSee('Save Draft')->assertSee('Product Media');
        $this->get('/products/1/edit')->assertOk()->assertSee('Variants Matrix', false);
    }

    public function test_simple_product_with_main_and_gallery_images(): void
    {
        $this->post('/products', $this->base([
            'images' => [['url' => $this->url('a')], ['url' => $this->url('b')]], 'main_key' => 1,
        ]))->assertRedirect();

        $p = Product::where('name', 'QA Linen Shirt')->firstOrFail();
        $this->assertSame('qa-linen-shirt', $p->slug);
        $this->assertSame(2, $p->images()->count());
        $this->assertSame($this->url('b'), $p->images()->where('is_main', 1)->value('image_url'));
        $this->assertSame(1, $p->images()->where('is_main', 1)->count());
    }

    public function test_save_draft_forces_draft_status_and_tolerates_missing_price(): void
    {
        $this->post('/products', ['name' => 'Half Done', 'category_id' => 1, 'availability' => 'in_stock', 'visibility' => 'visible', 'status' => 'active', 'save_draft' => 1])->assertRedirect();

        $p = Product::where('name', 'Half Done')->firstOrFail();
        $this->assertSame('draft', $p->status);
        $this->assertSame(0, DB::table('products')->where('id', $p->id)->where('status', 'active')->where('visibility', 'visible')->count(), 'draft must not match the storefront filter');
    }

    public function test_validation_failure_keeps_input_and_shows_errors(): void
    {
        $this->from('/products/create')->post('/products', $this->base(['price' => '', 'short_description' => 'keep me', 'images' => [['url' => $this->url('keep')]]]))
            ->assertRedirect('/products/create')->assertSessionHasErrors('price')
            ->assertSessionHasInput('short_description', 'keep me')->assertSessionHasInput('images.0.url', $this->url('keep'));

        $this->followingRedirects()->from('/products/create')->post('/products', $this->base(['price' => '', 'images' => [['url' => $this->url('keep')]]]))
            ->assertSee(str_replace('/', '\/', $this->url('keep')), false); // @json escapes slashes
    }

    public function test_form_redisplays_after_validation_failure_with_new_colors_and_sizes(): void
    {
        // Blank ids for new rows arrive as null (ConvertEmptyStringsToNull); the form must still render and keep the selections.
        $r = $this->followingRedirects()->from('/products/create')->post('/products', $this->base([
            'price' => '', 'has_variants' => 1, 'variant_scenario' => 'color_size', 'size_guide_id' => 1,
            'images' => [['url' => $this->url('x')]], 'main_key' => 0,
            'colors' => [['id' => '', 'name' => 'Sage', 'hex_code' => '#8a9a5b', 'images' => [0]]],
            'sizes' => [['id' => '', 'size_label' => 'M']],
            'variants' => [['id' => '', 'color' => 0, 'size' => 0, 'stock_qty' => 2, 'is_active' => 1]],
        ]));

        $r->assertOk()->assertSee('Sage')->assertSee('The price field is required');
    }

    public function test_rejects_image_urls_not_produced_by_the_uploader(): void
    {
        config(['cloudinary.cloud_name' => 'demo']);
        $this->post('/products', $this->base(['images' => [['url' => 'https://evil.example/x.jpg']]]))->assertSessionHasErrors('images');
        $this->assertSame(0, Product::where('name', 'QA Linen Shirt')->count());
    }

    public function test_color_size_variants_with_per_color_image_assignment_survive_editing(): void
    {
        $payload = $this->base([
            'sku' => 'QA', 'has_variants' => 1, 'variant_scenario' => 'color_size', 'stock_qty' => null,
            'images' => [['url' => $this->url('r')], ['url' => $this->url('b')]], 'main_key' => 0,
            'colors' => [['name' => 'Red', 'hex_code' => '#ff0000', 'images' => [0]], ['name' => 'Blue', 'hex_code' => '#0000ff', 'images' => [0, 1]]],
            'sizes' => [['size_label' => 'S'], ['size_label' => 'M']],
            'variants' => [
                ['color' => 0, 'size' => 0, 'stock_qty' => 3, 'is_active' => 1], ['color' => 0, 'size' => 1, 'stock_qty' => 4, 'is_active' => 1],
                ['color' => 1, 'size' => 0, 'stock_qty' => 5, 'is_active' => 0], ['color' => 1, 'size' => 1, 'stock_qty' => 6, 'is_active' => 1],
            ],
        ]);
        $this->post('/products', $payload)->assertRedirect();
        $p = Product::where('sku', 'QA')->firstOrFail();

        $this->assertSame(4, $p->variants()->count());
        $this->assertSame(['Blue' => 2, 'Red' => 1], DB::table('product_image_colors as ic')->join('product_colors as c', 'c.id', '=', 'ic.color_id')->where('c.product_id', $p->id)->groupBy('c.name')->pluck(DB::raw('COUNT(*)'), 'c.name')->all());

        // Edit: send back the existing ids, drop the second image and one size; nothing is duplicated.
        $p->load(['images', 'colors', 'sizes', 'variants']);
        $img = $p->images->sortBy('sort_order')->values();
        $red = $p->colors->firstWhere('name', 'Red');
        $blue = $p->colors->firstWhere('name', 'Blue');
        $s = $p->sizes->firstWhere('size_label', 'S');
        $edit = $payload + [];
        $edit['images'] = [['id' => $img[0]->id]];
        $edit['colors'] = [['id' => $red->id, 'name' => 'Red', 'hex_code' => '#ff0000', 'images' => [0]], ['id' => $blue->id, 'name' => 'Blue', 'hex_code' => '#0000ff', 'images' => []]];
        $edit['sizes'] = [['id' => $s->id, 'size_label' => 'S']];
        $edit['variants'] = [
            ['id' => $p->variants->firstWhere(fn ($v) => $v->color_id == $red->id && $v->size_id == $s->id)->id, 'color' => 0, 'size' => 0, 'stock_qty' => 9, 'is_active' => 1],
            ['color' => 1, 'size' => 0, 'stock_qty' => 5, 'is_active' => 1],
        ];
        $this->put("/products/{$p->id}", $edit)->assertRedirect();

        $p->refresh();
        $this->assertSame(1, $p->images()->count());
        $this->assertSame($img[0]->id, $p->images()->first()->id, 'existing image row is reused, not re-created');
        $this->assertSame(2, $p->variants()->count());
        $this->assertSame(9, (int) $p->variants()->where('color_id', $red->id)->value('stock_qty'));
        $this->assertSame(1, DB::table('product_image_colors')->where('image_id', $img[0]->id)->count());
        $this->assertSame(['S'], $p->sizes()->pluck('size_label')->all());
    }

    public function test_upload_endpoint_returns_only_the_url(): void
    {
        $this->app->bind(CloudinaryImageService::class, fn () => new CloudinaryImageService(fn () => ['secure_url' => 'https://res.cloudinary.com/demo/image/upload/x.jpg', 'api_key' => 'leak']));
        config(['cloudinary.cloud_name' => 'demo', 'cloudinary.api_key' => 'k', 'cloudinary.api_secret' => 's']);

        $this->postJson('/products/upload-image', ['image' => UploadedFile::fake()->image('a.jpg')])
            ->assertOk()->assertExactJson(['url' => 'https://res.cloudinary.com/demo/image/upload/x.jpg']);
        $this->postJson('/products/upload-image', ['image' => UploadedFile::fake()->create('a.txt', 3, 'text/plain')])->assertStatus(422)->assertJsonStructure(['message']);
        $this->postJson('/products/upload-image', [])->assertStatus(422);
    }

    public function test_guests_cannot_use_the_upload_endpoint(): void
    {
        auth('admin')->logout();
        $this->postJson('/products/upload-image', [])->assertUnauthorized();
    }
}
