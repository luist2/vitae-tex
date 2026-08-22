<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OutputSafetyAuditTest extends TestCase
{
    public function test_vue_sources_do_not_render_untrusted_html(): void
    {
        foreach (File::allFiles(resource_path('js')) as $file) {
            if ($file->getExtension() !== 'vue') {
                continue;
            }

            $this->assertStringNotContainsString(
                'v-html',
                $file->getContents(),
                "The Vue source [{$file->getRelativePathname()}] must not render raw HTML.",
            );
        }
    }

    public function test_raw_blade_output_is_confined_to_the_controlled_latex_template(): void
    {
        $allowedRawView = 'latex'.DIRECTORY_SEPARATOR.'jakes-resume.blade.php';

        foreach (File::allFiles(resource_path('views')) as $file) {
            if ($file->getRelativePathname() === $allowedRawView) {
                continue;
            }

            $this->assertStringNotContainsString(
                '{!!',
                $file->getContents(),
                "The Blade view [{$file->getRelativePathname()}] must not contain raw output.",
            );
        }

        $latexTemplate = File::get(resource_path('views/latex/jakes-resume.blade.php'));

        $this->assertStringContainsString('{!!', $latexTemplate);
    }
}
