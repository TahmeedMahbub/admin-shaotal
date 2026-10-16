<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    public function test_guests_are_redirected_from_every_admin_area(): void
    {
        foreach (['/', '/products', '/orders', '/customers', '/categories/create', '/coupons', '/offers', '/pages', '/navigation', '/site-contents', '/homepage'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    public function test_guests_cannot_post_to_admin_routes(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->post('/orders/1/status', ['order_status' => 'shipped'])->assertRedirect('/login');
        $this->delete('/products/1')->assertRedirect('/login');
    }

    public function test_login_page_is_public(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }
}
