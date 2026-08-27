<?php

namespace Tests\Feature\Cvs;

use App\Http\Resources\CvEditorResource;
use App\Models\Cv;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CvValidationRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_invalid_inertia_update_returns_to_the_editor_without_a_referrer_or_fresh_previous_url(): void
    {
        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->withContent()->create([
            'title' => 'Estado persistido',
        ]);
        $originalRevision = $cv->revision;
        $originalEducationIds = $cv->educationEntries()->pluck('id')->all();
        $payload = $this->payloadWithMissingEducationStartDate($cv);

        $response = $this->actingAs($owner)
            ->withSession([
                '_previous' => ['url' => route('register')],
            ])
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->patch(route('cvs.update', $cv), $payload);

        $response->assertSessionHasErrors('education_entries.0.start_date');
        $this->assertSame('Estado persistido', $cv->fresh()->title);
        $this->assertSame($originalRevision, $cv->fresh()->revision);
        $this->assertSame($originalEducationIds, $cv->educationEntries()->pluck('id')->all());
        $response->assertRedirect(route('cvs.edit', $cv));
    }

    public function test_an_invalid_inertia_update_returns_to_the_editor_after_a_direct_reload(): void
    {
        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->withContent()->create([
            'title' => 'Estado persistido',
        ]);
        $originalRevision = $cv->revision;
        $originalEducationIds = $cv->educationEntries()->pluck('id')->all();
        $payload = $this->payloadWithMissingEducationStartDate($cv);

        $this->actingAs($owner)
            ->withSession([
                '_previous' => ['url' => route('register')],
            ])
            ->get(route('cvs.edit', $cv))
            ->assertOk()
            ->assertSessionHas('_previous.url', route('cvs.edit', $cv));

        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->patch(route('cvs.update', $cv), $payload);

        $response
            ->assertSessionHasErrors('education_entries.0.start_date')
            ->assertRedirect(route('cvs.edit', $cv));
        $this->assertSame('Estado persistido', $cv->fresh()->title);
        $this->assertSame($originalRevision, $cv->fresh()->revision);
        $this->assertSame($originalEducationIds, $cv->educationEntries()->pluck('id')->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadWithMissingEducationStartDate(Cv $cv): array
    {
        $cv->load([
            'workExperiences',
            'educationEntries',
            'skillGroups.skills',
            'projects',
            'certifications',
            'links',
        ]);

        $payload = CvEditorResource::make($cv)->resolve();
        unset($payload['id'], $payload['revision'], $payload['updated_at']);

        $payload['education_entries'][0]['start_date'] = '';
        $payload['education_entries'][0]['end_date'] = '2020-12';
        $payload['education_entries'][0]['is_current'] = false;

        return $payload;
    }
}
