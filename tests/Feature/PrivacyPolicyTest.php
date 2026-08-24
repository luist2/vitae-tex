<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PrivacyPolicyTest extends TestCase
{
    public function test_the_privacy_policy_is_public_and_describes_the_retention_baseline(): void
    {
        config(['privacy.contact_email' => null]);

        $this->get(route('privacy'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Legal/Privacy')
                ->where('contactEmail', null));

        $component = file_get_contents(resource_path('js/pages/Legal/Privacy.vue'));

        $this->assertIsString($component);
        $this->assertStringContainsString('máximo operativo de siete días', $component);
    }

    public function test_the_privacy_policy_receives_the_configured_public_contact(): void
    {
        config(['privacy.contact_email' => 'privacidad@example.com']);

        $this->get(route('privacy'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Legal/Privacy')
                ->where('contactEmail', 'privacidad@example.com'));
    }
}
