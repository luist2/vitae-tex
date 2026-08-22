<?php

return [
    'default_template' => 'jakes-resume',

    'editor' => [
        'maximum_payload_bytes' => (int) env('CV_EDITOR_MAXIMUM_PAYLOAD_BYTES', 1024 * 1024),
    ],

    'pdf' => [
        'tectonic_binary' => env('TECTONIC_BINARY', '/usr/local/bin/tectonic'),
        'temporary_root' => env('CV_PDF_TEMPORARY_ROOT', sys_get_temp_dir()),
        'temporary_max_age_minutes' => (int) env('CV_PDF_TEMPORARY_MAX_AGE_MINUTES', 60),
        'timeout_seconds' => (int) env('CV_PDF_TIMEOUT_SECONDS', 30),
        'idle_timeout_seconds' => (int) env('CV_PDF_IDLE_TIMEOUT_SECONDS', 15),
        'rate_limit_per_minute' => (int) env('CV_PDF_RATE_LIMIT_PER_MINUTE', 3),
        'minimum_bytes' => 1024,
        'maximum_bytes' => (int) env('CV_PDF_MAXIMUM_BYTES', 5 * 1024 * 1024),
    ],

    'templates' => [
        'jakes-resume' => [
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
        ],
    ],
];
