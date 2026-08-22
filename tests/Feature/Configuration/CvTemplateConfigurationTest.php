<?php

namespace Tests\Feature\Configuration;

use Tests\TestCase;

class CvTemplateConfigurationTest extends TestCase
{
    public function test_cv_operations_have_bounded_non_public_defaults(): void
    {
        $this->assertSame(1024 * 1024, config('cv.editor.maximum_payload_bytes'));
        $this->assertSame('/usr/local/bin/tectonic', config('cv.pdf.tectonic_binary'));
        $this->assertSame(sys_get_temp_dir(), config('cv.pdf.temporary_root'));
        $this->assertSame(60, config('cv.pdf.temporary_max_age_minutes'));
        $this->assertSame(30, config('cv.pdf.timeout_seconds'));
        $this->assertSame(15, config('cv.pdf.idle_timeout_seconds'));
        $this->assertSame(3, config('cv.pdf.rate_limit_per_minute'));
        $this->assertSame(1024, config('cv.pdf.minimum_bytes'));
        $this->assertSame(5 * 1024 * 1024, config('cv.pdf.maximum_bytes'));
    }

    public function test_jakes_resume_has_the_complete_controlled_template_contract(): void
    {
        $this->assertSame('jakes-resume', config('cv.default_template'));
        $this->assertSame([
            'name' => "Jake's Resume",
            'view' => 'latex.jakes-resume',
            'paper' => 'a4',
            'language' => 'es',
            'sections' => [
                'professional_summary',
                'education',
                'work_experience',
                'projects',
                'skills',
                'certifications',
            ],
            'variants' => [
                'skills' => 'grouped',
            ],
        ], config('cv.templates.jakes-resume'));
    }
}
