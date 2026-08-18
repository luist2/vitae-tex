<?php

namespace Tests\Feature\Policies;

use App\Models\Cv;
use App\Models\User;
use App\Policies\CvPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CvPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_cv_policy_is_discovered_by_convention(): void
    {
        $this->assertInstanceOf(CvPolicy::class, Gate::getPolicyFor(Cv::class));
    }

    public function test_authenticated_users_can_list_and_create_cvs(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(Gate::forUser($user)->allows('viewAny', Cv::class));
        $this->assertTrue(Gate::forUser($user)->allows('create', Cv::class));
    }

    public function test_an_owner_can_use_every_cv_ability(): void
    {
        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create();

        foreach ($this->ownedAbilities() as $ability) {
            $this->assertTrue(
                Gate::forUser($owner)->allows($ability, $cv),
                "Expected the owner to be authorized for [{$ability}].",
            );
        }
    }

    public function test_another_user_cannot_use_any_cv_ability(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create();

        foreach ($this->ownedAbilities() as $ability) {
            $this->assertFalse(
                Gate::forUser($otherUser)->allows($ability, $cv),
                "Expected a non-owner to be denied for [{$ability}].",
            );
        }
    }

    /**
     * @return list<string>
     */
    private function ownedAbilities(): array
    {
        return [
            'view',
            'update',
            'delete',
            'duplicate',
            'download',
            'generate',
        ];
    }
}
