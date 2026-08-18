<?php

namespace Tests\Feature\Settings;

use App\Models\Cv;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/settings/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/settings/profile', [
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $user->refresh();

        $this->assertSame('test@example.com', $user->email);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $cv = Cv::factory()->for($user)->withContent()->create();
        $otherCv = Cv::factory()->for($otherUser)->create();

        DB::table('sessions')->insert([
            $this->sessionRecord('first-user-session', $user->id),
            $this->sessionRecord('second-user-session', $user->id),
            $this->sessionRecord('other-user-session', $otherUser->id),
        ]);

        Password::createToken($user);
        Password::createToken($otherUser);

        $response = $this
            ->actingAs($user)
            ->delete('/settings/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
        $this->assertDatabaseMissing('cvs', ['id' => $cv->id]);
        $this->assertDatabaseHas('cvs', ['id' => $otherCv->id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseHas('sessions', ['id' => 'other-user-session']);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $otherUser->email]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/settings/profile')
            ->delete('/settings/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/settings/profile');

        $this->assertNotNull($user->fresh());
    }

    /**
     * @return array<string, int|string|null>
     */
    private function sessionRecord(string $id, int $userId): array
    {
        return [
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ];
    }
}
