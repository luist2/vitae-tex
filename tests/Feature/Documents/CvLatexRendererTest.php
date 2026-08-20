<?php

namespace Tests\Feature\Documents;

use App\Models\Certification;
use App\Models\Cv;
use App\Models\CvLink;
use App\Models\EducationEntry;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillGroup;
use App\Models\User;
use App\Models\WorkExperience;
use App\Support\Latex\CvLatexRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CvLatexRendererTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_complete_cv_matches_the_stable_jakes_resume_fixture(): void
    {
        $source = $this->renderer()->render($this->completeCv());
        $fixtureHash = file_get_contents(base_path('tests/Fixtures/latex/jakes-resume.sha256'));

        $this->assertIsString($fixtureHash);
        $this->assertSame(trim($fixtureHash), hash('sha256', $source));
    }

    public function test_the_complete_rendered_template_compiles_offline_with_tectonic(): void
    {
        $directory = sys_get_temp_dir().'/vitaetex-jakes-resume-'.bin2hex(random_bytes(8));
        $sourcePath = $directory.'/resume.tex';
        $pdfPath = $directory.'/resume.pdf';
        $textPath = $directory.'/resume.txt';

        mkdir($directory, 0700, true);

        try {
            file_put_contents($sourcePath, $this->renderer()->render($this->completeCv()));

            $compile = new Process([
                '/usr/local/bin/tectonic',
                '-X',
                'compile',
                '--untrusted',
                '--only-cached',
                '--outdir',
                $directory,
                $sourcePath,
            ], env: [
                'TECTONIC_ONLY_CACHED' => '1',
                'TECTONIC_UNTRUSTED_MODE' => '1',
            ]);
            $compile->setTimeout(30);
            $compile->run();

            $this->assertTrue($compile->isSuccessful(), 'Tectonic no pudo compilar el fixture renderizado.');
            $this->assertFileExists($pdfPath);
            $this->assertSame('%PDF-', file_get_contents($pdfPath, false, null, 0, 5));
            $this->assertGreaterThan(1000, filesize($pdfPath));
            $this->assertLessThanOrEqual(5 * 1024 * 1024, filesize($pdfPath));

            $pdfInfo = new Process(['pdfinfo', $pdfPath]);
            $pdfInfo->mustRun();
            $this->assertMatchesRegularExpression('/^Page size:.*A4/m', $pdfInfo->getOutput());

            $pdfUrls = new Process(['pdfinfo', '-url', $pdfPath]);
            $pdfUrls->mustRun();

            foreach ([
                'mailto:maria.nunez@example.com',
                'https://www.linkedin.com/in/maria-nunez',
                'https://github.com/marianunez',
                'https://example.com/vitae_tex?ref=cv&lang=es',
                'https://example.com/credentials/VITAETEX_2025_001',
            ] as $expectedUrl) {
                $this->assertStringContainsString($expectedUrl, $pdfUrls->getOutput());
            }

            $extractText = new Process(['pdftotext', '-enc', 'UTF-8', '-layout', $pdfPath, $textPath]);
            $extractText->mustRun();
            $text = file_get_contents($textPath);

            $this->assertIsString($text);

            foreach ([
                'María José Núñez',
                'Perfil profesional',
                'Educación',
                'Experiencia',
                'Proyectos',
                'Habilidades técnicas',
                'Certificaciones',
            ] as $expectedText) {
                $this->assertStringContainsString($expectedText, $text);
            }
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_empty_sections_are_omitted_from_a_minimal_cv(): void
    {
        $cv = Cv::factory()->for(User::factory())->create([
            'full_name' => 'Ada Lovelace',
            'professional_headline' => null,
            'contact_email' => null,
            'phone' => '+56 9 1234 5678',
            'location' => null,
            'professional_summary' => null,
        ]);

        $source = $this->renderer()->render($cv);

        $this->assertStringContainsString('\\documentclass[a4paper,11pt]{article}', $source);
        $this->assertStringContainsString('Ada Lovelace', $source);
        $this->assertStringContainsString('+56 9 1234 5678', $source);
        $this->assertStringNotContainsString('\\section{', $source);
    }

    public function test_the_configured_section_order_controls_the_rendered_document(): void
    {
        $cv = $this->completeCv();
        config()->set('cv.templates.jakes-resume.sections', [
            'skills',
            'professional_summary',
            'education',
        ]);

        $source = $this->renderer()->render($cv);

        $skills = strpos($source, '\\section{Habilidades técnicas}');
        $summary = strpos($source, '\\section{Perfil profesional}');
        $education = strpos($source, '\\section{Educación}');

        $this->assertIsInt($skills);
        $this->assertIsInt($summary);
        $this->assertIsInt($education);
        $this->assertLessThan($summary, $skills);
        $this->assertLessThan($education, $summary);
        $this->assertStringNotContainsString('\\section{Experiencia}', $source);
    }

    public function test_rendering_uses_the_latest_persisted_state_instead_of_dirty_model_values(): void
    {
        $cv = Cv::factory()->for(User::factory())->create([
            'full_name' => 'Nombre guardado',
            'contact_email' => 'guardado@example.com',
        ]);
        $cv->full_name = 'Nombre local sin guardar';

        $source = $this->renderer()->render($cv);

        $this->assertStringContainsString('Nombre guardado', $source);
        $this->assertStringNotContainsString('Nombre local sin guardar', $source);
    }

    public function test_month_dates_are_formatted_in_spanish(): void
    {
        $cv = Cv::factory()->for(User::factory())->create([
            'full_name' => 'Ada Lovelace',
            'contact_email' => 'ada@example.com',
        ]);

        foreach (range(1, 12) as $position => $month) {
            Project::factory()->for($cv)->withoutDates()->create([
                'name' => 'Proyecto '.($position + 1),
                'start_date' => sprintf('2026-%02d-01', $month),
                'highlights' => [],
                'technologies' => [],
                'position' => $position,
            ]);
        }

        $source = $this->renderer()->render($cv);

        foreach ([
            'Enero',
            'Febrero',
            'Marzo',
            'Abril',
            'Mayo',
            'Junio',
            'Julio',
            'Agosto',
            'Septiembre',
            'Octubre',
            'Noviembre',
            'Diciembre',
        ] as $month) {
            $this->assertStringContainsString($month.' 2026', $source);
        }
    }

    public function test_hostile_content_is_escaped_without_changing_the_controlled_structure(): void
    {
        $cv = Cv::factory()->for(User::factory())->create([
            'full_name' => '\\input{/etc/passwd}',
            'contact_email' => 'safe@example.com',
            'professional_summary' => 'Cierra } % e intenta \\end{document}',
        ]);
        WorkExperience::factory()->for($cv)->create([
            'employer' => 'Empresa & Socios',
            'role' => '\\write18{touch /tmp/pwned}',
            'highlights' => ["Primera línea\n\\item{inyectado}"],
        ]);
        CvLink::factory()->for($cv)->create([
            'type' => 'other',
            'label' => '\\href{https://evil.test}{Malicioso}',
            'url' => 'https://example.com/perfil_seguro?x=1&y=2',
        ]);

        $source = $this->renderer()->render($cv);

        $this->assertStringNotContainsString('\\input{/etc/passwd}', $source);
        $this->assertStringNotContainsString('\\write18{touch /tmp/pwned}', $source);
        $this->assertStringNotContainsString('\\item{inyectado}', $source);
        $this->assertStringContainsString('\\textbackslash{}input\\{/etc/passwd\\}', $source);
        $this->assertStringContainsString('Empresa \\& Socios', $source);
        $this->assertStringContainsString('https://example.com/perfil\\_seguro?x=1\\&y=2', $source);
        $this->assertSame(1, substr_count($source, '\\begin{document}'));
        $this->assertSame(1, substr_count($source, '\\end{document}'));
    }

    public function test_an_unknown_template_is_rejected(): void
    {
        $cv = Cv::factory()->for(User::factory())->create([
            'template_key' => 'unknown-template',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('plantilla del CV no está disponible');

        $this->renderer()->render($cv);
    }

    public function test_an_invalid_persisted_url_is_rejected_at_the_latex_boundary(): void
    {
        $cv = Cv::factory()->for(User::factory())->create();
        CvLink::factory()->for($cv)->create([
            'type' => 'other',
            'label' => 'Enlace inseguro',
            'url' => 'javascript:alert(1)',
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->renderer()->render($cv);
    }

    private function renderer(): CvLatexRenderer
    {
        return app(CvLatexRenderer::class);
    }

    private function completeCv(): Cv
    {
        $cv = Cv::factory()->for(User::factory())->create([
            'title' => 'CV completo',
            'full_name' => 'María José Núñez',
            'professional_headline' => 'Ingeniera de Software',
            'contact_email' => 'maria.nunez@example.com',
            'phone' => '+56 9 5555 0101',
            'location' => 'Valparaíso, Chile',
            'professional_summary' => 'Ingeniera enfocada en productos web mantenibles, accesibles y seguros con PHP & TypeScript.',
        ]);

        EducationEntry::factory()->for($cv)->create([
            'institution' => 'Universidad Técnica Federico Santa María',
            'qualification' => 'Ingeniería Civil',
            'field_of_study' => 'Informática & Sistemas',
            'location' => 'Valparaíso, Chile',
            'start_date' => '2014-03-01',
            'end_date' => '2019-12-01',
            'is_current' => false,
            'description' => 'Formación en estructuras de datos, sistemas distribuidos y seguridad.',
            'position' => 0,
        ]);

        WorkExperience::factory()->for($cv)->create([
            'employer' => 'Laboratorio Puerto Digital',
            'role' => 'Desarrolladora Full Stack',
            'location' => 'Valparaíso, Chile',
            'start_date' => '2020-03-01',
            'end_date' => '2022-12-01',
            'is_current' => false,
            'highlights' => ['Construyó interfaces accesibles con Vue y TypeScript.'],
            'position' => 1,
        ]);
        WorkExperience::factory()->for($cv)->current()->create([
            'employer' => 'Cooperativa Horizonte',
            'role' => 'Ingeniera de Software Senior',
            'location' => 'Santiago, Chile',
            'start_date' => '2023-01-01',
            'highlights' => [
                'Diseñó servicios web con Laravel y PostgreSQL.',
                'Redujo tiempos de despliegue mediante pruebas automatizadas.',
            ],
            'position' => 0,
        ]);

        $frameworks = SkillGroup::factory()->for($cv)->create([
            'name' => 'Frameworks',
            'position' => 1,
        ]);
        Skill::factory()->for($frameworks)->create(['name' => 'Inertia', 'position' => 2]);
        Skill::factory()->for($frameworks)->create(['name' => 'Laravel', 'position' => 0]);
        Skill::factory()->for($frameworks)->create(['name' => 'Vue', 'position' => 1]);

        $languages = SkillGroup::factory()->for($cv)->create([
            'name' => 'Lenguajes',
            'position' => 0,
        ]);
        Skill::factory()->for($languages)->create(['name' => 'TypeScript', 'position' => 1]);
        Skill::factory()->for($languages)->create(['name' => 'PHP', 'position' => 0]);

        Project::factory()->for($cv)->current()->create([
            'name' => 'Vitae_Tex',
            'role' => 'Autora principal',
            'description' => 'Aplicación para mantener currículums estructurados.',
            'url' => 'https://example.com/vitae_tex?ref=cv&lang=es',
            'start_date' => '2026-04-01',
            'highlights' => [
                'Renderizado LaTeX seguro y reproducible.',
                'Datos persistidos en PostgreSQL.',
            ],
            'technologies' => ['Laravel', 'Vue', 'Tectonic'],
            'position' => 0,
        ]);

        Certification::factory()->for($cv)->create([
            'name' => 'Seguridad de aplicaciones web',
            'issuer' => 'Instituto de Tecnología Abierta',
            'issued_on' => '2025-11-01',
            'expires_on' => '2027-11-01',
            'credential_id' => 'VITAETEX_2025_001',
            'credential_url' => 'https://example.com/credentials/VITAETEX_2025_001',
            'position' => 0,
        ]);

        CvLink::factory()->for($cv)->create([
            'type' => 'github',
            'label' => 'github.com/marianunez',
            'url' => 'https://github.com/marianunez',
            'position' => 1,
        ]);
        CvLink::factory()->for($cv)->create([
            'type' => 'linkedin',
            'label' => 'linkedin.com/in/maria-nunez',
            'url' => 'https://www.linkedin.com/in/maria-nunez',
            'position' => 0,
        ]);

        return $cv;
    }
}
