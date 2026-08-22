<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\EnforceCvEditorPayloadLimit;
use App\Models\Cv;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CvRouteSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array{method: string, cv_scoped: bool}> */
    private const ROUTE_MATRIX = [
        'cvs.index' => ['method' => 'GET', 'cv_scoped' => false],
        'cvs.store' => ['method' => 'POST', 'cv_scoped' => false],
        'cvs.edit' => ['method' => 'GET', 'cv_scoped' => true],
        'cvs.download.tex' => ['method' => 'GET', 'cv_scoped' => true],
        'cvs.generate.pdf' => ['method' => 'POST', 'cv_scoped' => true],
        'cvs.update' => ['method' => 'PATCH', 'cv_scoped' => true],
        'cvs.duplicate' => ['method' => 'POST', 'cv_scoped' => true],
        'cvs.destroy' => ['method' => 'DELETE', 'cv_scoped' => true],
    ];

    public function test_the_private_local_disk_does_not_register_file_serving_routes(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('storage.local'));
        $this->assertNull(Route::getRoutes()->getByName('storage.local.upload'));
    }

    public function test_every_cv_route_is_in_the_authenticated_route_matrix(): void
    {
        $actualRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'cvs.'))
            ->keyBy(fn ($route): string => (string) $route->getName());

        $this->assertEqualsCanonicalizing(array_keys(self::ROUTE_MATRIX), $actualRoutes->keys()->all());

        foreach (self::ROUTE_MATRIX as $name => $definition) {
            $route = $actualRoutes->get($name);

            $this->assertNotNull($route);
            $this->assertSame([$definition['method']], array_values(array_diff($route->methods(), ['HEAD'])));
            $this->assertContains('web', $route->gatherMiddleware());
            $this->assertContains('auth', $route->gatherMiddleware());
        }

        $this->assertContains(
            'throttle:cv-pdf-generation',
            $actualRoutes->get('cvs.generate.pdf')->gatherMiddleware(),
        );
        $this->assertContains(
            EnforceCvEditorPayloadLimit::class,
            $actualRoutes->get('cvs.update')->gatherMiddleware(),
        );
    }

    #[DataProvider('cvRouteProvider')]
    public function test_guests_cannot_use_any_cv_route(string $method, string $routeName): void
    {
        $cv = Cv::factory()->create();
        Process::fake()->preventStrayProcesses();
        $url = in_array($routeName, ['cvs.index', 'cvs.store'], true)
            ? route($routeName)
            : route($routeName, $cv);

        $this->call($method, $url)
            ->assertRedirect('/login');

        Process::assertNothingRan();
    }

    #[DataProvider('ownedCvRouteProvider')]
    public function test_a_user_cannot_use_any_route_for_another_users_cv(string $method, string $routeName): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $cv = Cv::factory()->for($owner)->withContent()->create([
            'title' => 'CV privado que no debe filtrarse',
            'full_name' => 'Nombre privado que no debe filtrarse',
        ]);
        $originalRevision = $cv->revision;
        Process::fake()->preventStrayProcesses();

        $this->actingAs($otherUser)
            ->call($method, route($routeName, $cv))
            ->assertNotFound()
            ->assertDontSee($cv->title)
            ->assertDontSee($cv->full_name);

        $this->assertSame($originalRevision, $cv->fresh()->revision);
        $this->assertSame(1, $owner->cvs()->count());
        $this->assertSame(0, $otherUser->cvs()->count());
        Process::assertNothingRan();
    }

    /** @return array<string, array{string, string}> */
    public static function cvRouteProvider(): array
    {
        return self::routeProvider(cvScoped: null);
    }

    /** @return array<string, array{string, string}> */
    public static function ownedCvRouteProvider(): array
    {
        return self::routeProvider(cvScoped: true);
    }

    /** @return array<string, array{string, string}> */
    private static function routeProvider(?bool $cvScoped): array
    {
        $routes = [];

        foreach (self::ROUTE_MATRIX as $name => $definition) {
            if ($cvScoped !== null && $definition['cv_scoped'] !== $cvScoped) {
                continue;
            }

            $routes[$name] = [$definition['method'], $name];
        }

        return $routes;
    }
}
