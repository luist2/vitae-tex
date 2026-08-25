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
            ->assertInertiaFlash('toast', [
                'type' => 'success',
                'message' => 'Email actualizado correctamente.',
            ])
            ->assertRedirect('/settings/profile');

        $user->refresh();

        $this->assertSame('test@example.com', $user->email);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $cvs = Cv::factory()->count(2)->for($user)->withContent()->create();
        $otherCv = Cv::factory()->for($otherUser)->withContent()->create();
        $deletedContentRows = $this->contentRowIds($cvs);
        $retainedContentRows = $this->contentRowIds([$otherCv]);

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
        $this->assertDatabaseMissing('users', ['id' => $user->id]);

        foreach ($cvs as $cv) {
            $this->assertDatabaseMissing('cvs', ['id' => $cv->id]);
        }

        foreach ($deletedContentRows as $table => $ids) {
            foreach ($ids as $id) {
                $this->assertDatabaseMissing($table, ['id' => $id]);
            }
        }

        $this->assertDatabaseHas('users', ['id' => $otherUser->id]);
        $this->assertDatabaseHas('cvs', ['id' => $otherCv->id]);

        foreach ($retainedContentRows as $table => $ids) {
            foreach ($ids as $id) {
                $this->assertDatabaseHas($table, ['id' => $id]);
            }
        }

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

    /**
     * @param  iterable<int, Cv>  $cvs
     * @return array<string, array<int, int>>
     */
    private function contentRowIds(iterable $cvs): array
    {
        $rows = [
            'work_experiences' => [],
            'education_entries' => [],
            'skill_groups' => [],
            'skills' => [],
            'projects' => [],
            'certifications' => [],
            'cv_links' => [],
        ];

        foreach ($cvs as $cv) {
            $cv->load([
                'workExperiences',
                'educationEntries',
                'skillGroups.skills',
                'projects',
                'certifications',
                'links',
            ]);

            $rows['work_experiences'] = [...$rows['work_experiences'], ...$cv->workExperiences->pluck('id')->all()];
            $rows['education_entries'] = [...$rows['education_entries'], ...$cv->educationEntries->pluck('id')->all()];
            $rows['skill_groups'] = [...$rows['skill_groups'], ...$cv->skillGroups->pluck('id')->all()];
            $rows['skills'] = [
                ...$rows['skills'],
                ...$cv->skillGroups->flatMap(fn ($group) => $group->skills->pluck('id'))->all(),
            ];
            $rows['projects'] = [...$rows['projects'], ...$cv->projects->pluck('id')->all()];
            $rows['certifications'] = [...$rows['certifications'], ...$cv->certifications->pluck('id')->all()];
            $rows['cv_links'] = [...$rows['cv_links'], ...$cv->links->pluck('id')->all()];
        }

        return $rows;
    }
}
