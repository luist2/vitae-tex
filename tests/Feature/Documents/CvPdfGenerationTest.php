<?php

namespace Tests\Feature\Documents;

use App\Models\Cv;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class CvPdfGenerationTest extends TestCase
{
    use RefreshDatabase;

    private string $temporaryRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryRoot = sys_get_temp_dir().'/vitaetex-pdf-endpoint-tests-'.bin2hex(random_bytes(8));
        mkdir($this->temporaryRoot, 0700, true);

        config([
            'cv.pdf.temporary_root' => $this->temporaryRoot,
            'cv.pdf.minimum_bytes' => 1024,
            'cv.pdf.maximum_bytes' => 4096,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryRoot);

        parent::tearDown();
    }

    public function test_a_guest_cannot_generate_a_cv_pdf(): void
    {
        $cv = Cv::factory()->create();
        Process::fake()->preventStrayProcesses();

        $this->post(route('cvs.generate.pdf', $cv))
            ->assertRedirect('/login');

        Process::assertNothingRan();
    }

    public function test_an_owner_can_generate_a_private_pdf_from_the_persisted_cv(): void
    {
        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create([
            'title' => 'CV Backend Chile',
            'full_name' => 'Nombre guardado',
            'contact_email' => 'guardado@example.com',
            'professional_summary' => 'Resumen persistido',
        ]);
        $expectedPdf = $this->validPdf();

        Process::fake(function (PendingProcess $process) use ($expectedPdf) {
            $source = File::get($process->path.'/document.tex');

            $this->assertStringContainsString('Nombre guardado', $source);
            $this->assertStringContainsString('Resumen persistido', $source);
            $this->assertStringNotContainsString('Nombre local sin guardar', $source);

            File::put($process->path.'/document.pdf', $expectedPdf);

            return Process::result();
        });

        $response = $this->actingAs($owner)->post(route('cvs.generate.pdf', $cv), [
            'full_name' => 'Nombre local sin guardar',
        ]);

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="cv-backend-chile.pdf"')
            ->assertHeaderContains('Cache-Control', 'private')
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-CV-Revision', $cv->updated_at->toISOString())
            ->assertContent($expectedPdf);

        $this->assertTemporaryRootIsEmpty();
    }

    public function test_a_user_cannot_generate_another_users_cv_pdf(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create([
            'title' => 'CV privado del propietario',
            'full_name' => 'Nombre que no debe filtrarse',
        ]);
        Process::fake()->preventStrayProcesses();

        $this->actingAs($otherUser)
            ->postJson(route('cvs.generate.pdf', $cv))
            ->assertNotFound()
            ->assertDontSee('CV privado del propietario')
            ->assertDontSee('Nombre que no debe filtrarse');

        Process::assertNothingRan();
    }

    public function test_pdf_generation_is_only_available_via_post(): void
    {
        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create();
        Process::fake()->preventStrayProcesses();

        $this->actingAs($owner)
            ->get(route('cvs.generate.pdf', $cv))
            ->assertMethodNotAllowed();

        Process::assertNothingRan();
    }

    public function test_a_compilation_failure_returns_a_safe_private_error(): void
    {
        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create([
            'full_name' => 'Contenido privado del CV',
            'contact_email' => 'private@example.com',
        ]);
        $workingDirectory = null;

        Process::fake(function (PendingProcess $process) use (&$workingDirectory) {
            $workingDirectory = $process->path;

            return Process::result('', 'Contenido privado del CV '.$process->path, 1);
        });

        $response = $this->actingAs($owner)
            ->postJson(route('cvs.generate.pdf', $cv));

        $response
            ->assertServiceUnavailable()
            ->assertExactJson([
                'message' => 'No fue posible generar el PDF. Inténtalo nuevamente.',
            ])
            ->assertHeaderContains('Cache-Control', 'private')
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertDontSee('Contenido privado del CV')
            ->assertDontSee((string) $workingDirectory);

        $this->assertIsString($workingDirectory);
        $this->assertDirectoryDoesNotExist($workingDirectory);
        $this->assertTemporaryRootIsEmpty();
    }

    public function test_pdf_generation_is_rate_limited_per_user(): void
    {
        config(['cv.pdf.rate_limit_per_minute' => 2]);

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create();
        $otherCv = Cv::factory()->for($otherUser)->create();
        $expectedPdf = $this->validPdf();

        Process::fake(function (PendingProcess $process) use ($expectedPdf) {
            File::put($process->path.'/document.pdf', $expectedPdf);

            return Process::result();
        });

        $this->actingAs($owner)
            ->postJson(route('cvs.generate.pdf', $cv))
            ->assertOk();
        $this->postJson(route('cvs.generate.pdf', $cv))
            ->assertOk();
        $this->postJson(route('cvs.generate.pdf', $cv))
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
        $this->actingAs($otherUser)
            ->postJson(route('cvs.generate.pdf', $otherCv))
            ->assertOk();

        Process::assertRanTimes(fn (PendingProcess $process): bool => is_array($process->command), 3);
        $this->assertTemporaryRootIsEmpty();
    }

    private function validPdf(): string
    {
        return '%PDF-1.7'.str_repeat('x', 2048);
    }

    private function assertTemporaryRootIsEmpty(): void
    {
        $this->assertSame([], File::directories($this->temporaryRoot));
        $this->assertSame([], File::files($this->temporaryRoot));
    }
}
