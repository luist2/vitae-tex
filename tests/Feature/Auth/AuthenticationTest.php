<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('cvs.index', absolute: false));
    }

    public function test_authentication_rotates_the_csrf_token_and_refreshes_its_cookie(): void
    {
        $user = User::factory()->create();

        $this->get('/login');
        $tokenBeforeLogin = session()->token();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $tokenAfterLogin = session()->token();

        $this->assertNotSame($tokenBeforeLogin, $tokenAfterLogin);
        $response->assertCookie('XSRF-TOKEN', $tokenAfterLogin);
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors([
            'email' => 'Las credenciales ingresadas no son correctas.',
        ]);

        $this->assertGuest();
    }

    public function test_login_validation_messages_are_in_spanish(): void
    {
        $this->post('/login')->assertSessionHasErrors([
            'email' => 'El email es obligatorio.',
            'password' => 'La contraseña es obligatoria.',
        ]);
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_logout_is_not_available_via_get()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/logout')->assertMethodNotAllowed();
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_rate_limited()
    {
        Event::fake([Lockout::class]);

        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        Event::assertDispatched(Lockout::class);
        $this->assertGuest();
    }

    public function test_email_verification_routes_are_not_available()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/verify-email')->assertNotFound();
        $this->actingAs($user)->post('/email/verification-notification')->assertNotFound();
    }
}
