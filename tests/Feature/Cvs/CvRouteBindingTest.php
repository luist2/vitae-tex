<?php

namespace Tests\Feature\Cvs;

use App\Models\Cv;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CvRouteBindingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth'])->get('/_testing/cvs/{cv}', function (Cv $cv) {
            Gate::authorize('view', $cv);

            return response()->json([
                'id' => $cv->id,
                'title' => $cv->title,
            ]);
        });
    }

    public function test_guests_cannot_resolve_a_cv(): void
    {
        $cv = Cv::factory()->create();

        $this->get("/_testing/cvs/{$cv->id}")
            ->assertRedirect('/login');
    }

    public function test_an_owner_can_resolve_their_cv(): void
    {
        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->get("/_testing/cvs/{$cv->id}")
            ->assertOk()
            ->assertExactJson([
                'id' => $cv->id,
                'title' => $cv->title,
            ]);
    }

    public function test_a_user_cannot_resolve_another_users_cv(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create();

        $this->actingAs($otherUser)
            ->get("/_testing/cvs/{$cv->id}")
            ->assertNotFound()
            ->assertDontSee($cv->title);
    }

    public function test_a_missing_cv_and_a_foreign_cv_have_the_same_response(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignCv = Cv::factory()->for($owner)->create();

        $foreignResponse = $this->actingAs($otherUser)
            ->get("/_testing/cvs/{$foreignCv->id}");
        $missingResponse = $this->actingAs($otherUser)
            ->get('/_testing/cvs/'.PHP_INT_MAX);

        $foreignResponse->assertNotFound();
        $missingResponse->assertNotFound();
        $this->assertSame($missingResponse->getContent(), $foreignResponse->getContent());
    }

    public function test_an_invalid_cv_id_returns_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/_testing/cvs/not-an-id')
            ->assertNotFound();
    }
}
