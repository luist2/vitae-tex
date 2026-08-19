<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_redirects_guests_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_the_home_page_redirects_authenticated_users_to_their_cvs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('cvs.index'));
    }

    public function test_the_public_account_pages_use_the_expected_inertia_components(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/Login'));

        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/Register'));

        $this->get('/forgot-password')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/ForgotPassword'));
    }

    public function test_the_application_uses_the_spanish_vitaetex_identity(): void
    {
        $this->assertSame('VitaeTex', config('app.name'));
        $this->assertSame('es', config('app.locale'));
        $this->assertSame('es', config('app.fallback_locale'));

        $this->get('/login')
            ->assertOk()
            ->assertSee('VitaeTex')
            ->assertDontSee('Laravel Starter Kit');
    }
}
