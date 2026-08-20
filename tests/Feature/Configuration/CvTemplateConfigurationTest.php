<?php

namespace Tests\Feature\Configuration;

use Tests\TestCase;

class CvTemplateConfigurationTest extends TestCase
{
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
