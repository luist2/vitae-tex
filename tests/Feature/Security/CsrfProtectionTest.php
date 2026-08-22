<?php

namespace Tests\Feature\Security;

use App\Models\Cv;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_mutable_web_route_is_covered_by_csrf_without_exclusions(): void
    {
        $mutableMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
        $mutableRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => array_intersect($mutableMethods, $route->methods()) !== []);

        $this->assertNotEmpty($mutableRoutes);

        foreach ($mutableRoutes as $route) {
            $this->assertContains(
                'web',
                $route->gatherMiddleware(),
                "The mutable route [{$route->uri()}] must use the CSRF-protected web middleware group.",
            );
        }

        $this->assertSame([], app(ValidateCsrfToken::class)->getExcludedPaths());
    }

    public function test_representative_mutations_reject_requests_without_a_csrf_token_before_side_effects(): void
    {
        $this->enableCsrfValidation();

        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->withContent()->create([
            'title' => 'CV protegido por CSRF',
        ]);
        $originalRevision = $cv->revision;
        $originalCvCount = $owner->cvs()->count();
        Process::fake()->preventStrayProcesses();

        $this->actingAs($owner);

        $requests = [
            fn () => $this->patch(route('cvs.update', $cv)),
            fn () => $this->post(route('cvs.duplicate', $cv)),
            fn () => $this->delete(route('cvs.destroy', $cv)),
            fn () => $this->postJson(route('cvs.generate.pdf', $cv)),
            fn () => $this->delete(route('profile.destroy'), ['password' => 'password']),
        ];

        foreach ($requests as $request) {
            $request()->assertStatus(419);
        }

        $this->assertNotNull($owner->fresh());
        $this->assertNotNull($cv->fresh());
        $this->assertSame($originalRevision, $cv->fresh()->revision);
        $this->assertSame($originalCvCount, $owner->cvs()->count());
        Process::assertNothingRan();
    }

    public function test_a_matching_csrf_token_allows_a_protected_mutation(): void
    {
        $this->enableCsrfValidation();

        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create();
        $token = 'csrf-token-for-feature-test';

        $this->actingAs($owner)
            ->withSession(['_token' => $token])
            ->post(route('cvs.duplicate', $cv), ['_token' => $token])
            ->assertRedirect();

        $this->assertSame(2, $owner->cvs()->count());
    }

    private function enableCsrfValidation(): void
    {
        // Laravel omits CSRF validation while the application environment is
        // "testing". Switching only this binding exercises the real middleware.
        $this->app->instance('env', 'production');
    }
}
