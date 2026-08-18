<?php

namespace Tests\Feature\Models;

use App\Models\Cv;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CvTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_own_multiple_cvs(): void
    {
        $user = User::factory()->create();
        $cvs = Cv::factory()->count(2)->for($user)->create();

        $this->assertCount(2, $user->cvs);
        $this->assertTrue($cvs->every(
            fn (Cv $cv): bool => $cv->user->is($user),
        ));
    }

    public function test_an_empty_cv_can_be_created_through_its_owner(): void
    {
        $user = User::factory()->create();

        $cv = $user->cvs()->create([
            'title' => 'CV principal',
            'template_key' => 'jakes-resume',
        ]);

        $this->assertTrue($cv->user->is($user));
        $this->assertNull($cv->full_name);
        $this->assertNull($cv->professional_headline);
        $this->assertNull($cv->contact_email);
        $this->assertNull($cv->phone);
        $this->assertNull($cv->location);
        $this->assertNull($cv->professional_summary);
    }

    public function test_user_id_cannot_be_mass_assigned_on_an_existing_cv(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create();

        $cv->fill(['user_id' => $otherUser->id])->save();

        $this->assertTrue($cv->fresh()->user->is($owner));
    }

    public function test_user_scoped_queries_do_not_return_another_users_cv(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownedCv = Cv::factory()->for($owner)->create();
        $otherCv = Cv::factory()->for($otherUser)->create();

        $this->assertTrue($owner->cvs()->findOrFail($ownedCv->id)->is($ownedCv));
        $this->assertNull($owner->cvs()->find($otherCv->id));
    }

    public function test_deleting_a_user_cascades_to_their_cvs_only(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $userCvs = Cv::factory()->count(2)->for($user)->create();
        $otherCv = Cv::factory()->for($otherUser)->create();

        $user->delete();

        foreach ($userCvs as $cv) {
            $this->assertDatabaseMissing('cvs', ['id' => $cv->id]);
        }

        $this->assertDatabaseHas('cvs', ['id' => $otherCv->id]);
    }
}
