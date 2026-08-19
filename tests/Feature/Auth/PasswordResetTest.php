<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered()
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_request_returns_a_safe_spanish_status(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'unknown@example.com'])
            ->assertSessionHas('status', 'Si la cuenta existe, recibirás un enlace para restablecer tu contraseña.');
    }

    public function test_reset_password_notification_is_in_spanish(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $message = $notification->toMail($user);

            $this->assertSame('Restablece tu contraseña', $message->subject);
            $this->assertContains(
                'Recibes este email porque solicitaste restablecer la contraseña de tu cuenta.',
                $message->introLines,
            );
            $this->assertSame('Restablecer contraseña', $message->actionText);

            $renderedMessage = $message->render();

            $this->assertStringContainsString('Si tienes problemas para usar el botón', $renderedMessage);
            $this->assertStringContainsString('Todos los derechos reservados.', $renderedMessage);
            $this->assertStringNotContainsString('If you did not request a password reset', $renderedMessage);

            return true;
        });
    }

    public function test_reset_password_screen_can_be_rendered()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            $this->assertDatabaseMissing('password_reset_tokens', [
                'email' => $user->email,
            ]);

            return true;
        });
    }

    public function test_expired_password_reset_tokens_can_be_cleared()
    {
        $expiredUser = User::factory()->create();
        $activeUser = User::factory()->create();

        DB::table('password_reset_tokens')->insert([
            [
                'email' => $expiredUser->email,
                'token' => 'expired-token',
                'created_at' => now()->subMinutes(61),
            ],
            [
                'email' => $activeUser->email,
                'token' => 'active-token',
                'created_at' => now(),
            ],
        ]);

        $this->artisan('auth:clear-resets')->assertSuccessful();

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $expiredUser->email,
        ]);
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $activeUser->email,
        ]);
    }

    public function test_expired_password_reset_tokens_are_scheduled_for_daily_cleanup()
    {
        $cleanup = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'auth:clear-resets'));

        $this->assertNotNull($cleanup);
        $this->assertSame('0 0 * * *', $cleanup->expression);
    }
}
