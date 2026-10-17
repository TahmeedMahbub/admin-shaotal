<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\HeroSlide;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BannerReorderTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->actingAs(AdminUser::firstOrFail(), 'admin');
    }

    public function test_reorder_persists_the_dragged_sequence(): void
    {
        $a = HeroSlide::create(['image_url' => 'https://res.cloudinary.com/demo/a.jpg', 'sort_order' => 1, 'status' => 'active']);
        $b = HeroSlide::create(['image_url' => 'https://res.cloudinary.com/demo/b.jpg', 'sort_order' => 2, 'status' => 'active']);
        $c = HeroSlide::create(['image_url' => 'https://res.cloudinary.com/demo/c.jpg', 'sort_order' => 3, 'status' => 'active']);

        $this->postJson('/banners/reorder', ['ids' => [$c->id, $a->id, $b->id]])->assertOk();

        $this->assertSame(1, $c->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);
        $this->assertSame(3, $b->fresh()->sort_order);
    }

    public function test_reorder_rejects_ids_from_outside_the_set(): void
    {
        $a = HeroSlide::create(['image_url' => 'https://res.cloudinary.com/demo/a.jpg', 'sort_order' => 1, 'status' => 'active']);

        $this->postJson('/banners/reorder', ['ids' => [$a->id, 999999]])->assertStatus(422);
        $this->postJson('/banners/reorder', ['ids' => [$a->id, $a->id]])->assertStatus(422);
    }

    public function test_guests_cannot_reorder(): void
    {
        auth('admin')->logout();
        $this->postJson('/banners/reorder', ['ids' => [1]])->assertUnauthorized();
    }

    public function test_new_banner_is_appended_after_the_current_highest_order(): void
    {
        HeroSlide::create(['image_url' => 'https://res.cloudinary.com/demo/a.jpg', 'sort_order' => 5, 'status' => 'active']);

        $this->post('/banners', ['image_url' => 'https://res.cloudinary.com/demo/b.jpg', 'status' => 'active'])->assertRedirect();

        $this->assertSame(6, HeroSlide::where('image_url', 'https://res.cloudinary.com/demo/b.jpg')->value('sort_order'));
    }
}
