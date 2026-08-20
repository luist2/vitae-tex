<?php

namespace Tests\Feature\Documents;

use App\Models\Cv;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CvTexDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_download_a_cv_source(): void
    {
        $cv = Cv::factory()->create();

        $this->get(route('cvs.download.tex', $cv))
            ->assertRedirect('/login');
    }

    public function test_an_owner_can_download_the_persisted_cv_source_with_private_headers(): void
    {
        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create([
            'title' => 'CV Backend Chile',
            'full_name' => 'Nombre guardado',
            'professional_summary' => 'Resumen persistido',
        ]);

        $response = $this->actingAs($owner)->get(route('cvs.download.tex', [
            'cv' => $cv,
            'full_name' => 'Nombre local sin guardar',
        ]));

        $response
            ->assertOk()
            ->assertDownload('cv-backend-chile.tex')
            ->assertHeader('Content-Type', 'application/x-tex; charset=UTF-8')
            ->assertHeaderContains('Cache-Control', 'private')
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $source = $response->streamedContent();

        $this->assertStringContainsString('Nombre guardado', $source);
        $this->assertStringContainsString('Resumen persistido', $source);
        $this->assertStringNotContainsString('Nombre local sin guardar', $source);
    }

    public function test_a_user_cannot_download_another_users_cv_source(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create([
            'title' => 'CV privado del propietario',
            'full_name' => 'Nombre que no debe filtrarse',
        ]);

        $this->actingAs($otherUser)
            ->get(route('cvs.download.tex', $cv))
            ->assertNotFound()
            ->assertDontSee('CV privado del propietario')
            ->assertDontSee('Nombre que no debe filtrarse');
    }

    public function test_the_download_filename_is_sanitized_by_the_application(): void
    {
        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create([
            'title' => "CV Ñuñoa / Backend #1\r\nUnsafe",
        ]);

        $this->actingAs($owner)
            ->get(route('cvs.download.tex', $cv))
            ->assertOk()
            ->assertDownload('cv-nunoa-backend-1-unsafe.tex');
    }
}
