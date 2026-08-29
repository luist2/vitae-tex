<?php

namespace Tests\Feature\Cvs;

use App\Http\Requests\Cvs\UpdateCvRequest;
use App\Models\Cv;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use ReflectionMethod;
use Tests\TestCase;

class CvEditorPayloadLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_largest_valid_editor_contract_fits_below_the_configured_limit(): void
    {
        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create();
        $payload = $this->maximumContractPayload();
        $body = $this->jsonBody($payload);

        $this->assertGreaterThan(600 * 1024, strlen($body));
        $this->assertLessThan((int) config('cv.editor.maximum_payload_bytes'), strlen($body));

        $this->actingAs($owner);
        $this->patchJsonBody($cv, $body, strlen($body))
            ->assertRedirect(route('cvs.edit', $cv))
            ->assertSessionHasNoErrors();

        $cv->refresh();

        $this->assertSame(2, $cv->revision);
        $this->assertCount(15, $cv->workExperiences);
        $this->assertCount(10, $cv->educationEntries);
        $this->assertCount(10, $cv->skillGroups);
        $this->assertSame(100, $cv->skillGroups->sum(fn ($group): int => $group->skills->count()));
        $this->assertCount(15, $cv->projects);
        $this->assertCount(20, $cv->certifications);
        $this->assertCount(8, $cv->links);
    }

    public function test_a_payload_at_the_exact_limit_is_accepted_and_unknown_fields_are_discarded(): void
    {
        config(['cv.editor.maximum_payload_bytes' => 16 * 1024]);

        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create();
        $payload = $this->validPayload();
        $payload['unexpected'] = '';
        $body = $this->jsonBodyWithExactBytes($payload, 16 * 1024, 'unexpected');

        $this->actingAs($owner);
        $this->patchJsonBody($cv, $body, strlen($body))
            ->assertRedirect(route('cvs.edit', $cv))
            ->assertSessionHasNoErrors();

        $this->assertSame('CV de Ada', $cv->fresh()->title);
        $this->assertSame(2, $cv->fresh()->revision);
    }

    public function test_an_oversized_body_is_rejected_even_if_content_length_is_forged_smaller(): void
    {
        config(['cv.editor.maximum_payload_bytes' => 4096]);

        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->withContent()->create([
            'title' => 'Estado persistido',
        ]);
        $originalRevision = $cv->revision;
        $originalExperienceIds = $cv->workExperiences()->pluck('id')->all();
        $privateMarker = 'CONTENIDO-PRIVADO-NO-DEBE-APARECER';
        $body = $this->jsonBody([
            ...$this->validPayload(),
            'unexpected' => $privateMarker.str_repeat('x', 4096),
        ]);

        $this->actingAs($owner);
        $this->patchJsonBody($cv, $body, 1)
            ->assertStatus(413)
            ->assertSeeText('La solicitud es demasiado grande. Reduce el contenido e inténtalo de nuevo.')
            ->assertDontSee($privateMarker)
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertHeaderMissing('X-Inertia')
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertSame('Estado persistido', $cv->fresh()->title);
        $this->assertSame($originalRevision, $cv->fresh()->revision);
        $this->assertSame($originalExperienceIds, $cv->workExperiences()->pluck('id')->all());
    }

    public function test_an_oversized_declared_content_length_is_rejected_before_validation(): void
    {
        config(['cv.editor.maximum_payload_bytes' => 4096]);

        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create([
            'title' => 'Sin cambios',
        ]);
        $body = $this->jsonBody($this->validPayload());

        $this->actingAs($owner);
        $this->patchJsonBody($cv, $body, 4097)
            ->assertStatus(413)
            ->assertSeeText('La solicitud es demasiado grande.');

        $this->assertSame('Sin cambios', $cv->fresh()->title);
        $this->assertSame(1, $cv->fresh()->revision);
    }

    public function test_prepare_for_validation_keeps_only_the_controlled_editor_contract(): void
    {
        $payload = $this->validPayload();
        $payload['revision'] = 999;
        $payload['unexpected'] = 'discard me';
        $payload['projects'][0]['unexpected'] = 'discard me';
        $payload['skill_groups'][0]['unexpected'] = 'discard me';
        $payload['skill_groups'][0]['skills'][0]['unexpected'] = 'discard me';
        $request = UpdateCvRequest::create('/cvs/1', 'PATCH', $payload);

        (new ReflectionMethod($request, 'prepareForValidation'))->invoke($request);

        $normalized = $request->all();

        $this->assertArrayNotHasKey('revision', $normalized);
        $this->assertArrayNotHasKey('unexpected', $normalized);
        $this->assertArrayNotHasKey('unexpected', $normalized['projects'][0]);
        $this->assertArrayNotHasKey('unexpected', $normalized['skill_groups'][0]);
        $this->assertArrayNotHasKey('unexpected', $normalized['skill_groups'][0]['skills'][0]);
    }

    private function patchJsonBody(Cv $cv, string $body, int $declaredBytes): TestResponse
    {
        return $this->call(
            'PATCH',
            route('cvs.update', $cv),
            server: [
                'CONTENT_LENGTH' => $declaredBytes,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'text/html, application/xhtml+xml',
                'HTTP_X_INERTIA' => 'true',
            ],
            content: $body,
        );
    }

    /** @param array<string, mixed> $payload */
    private function jsonBody(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /** @param array<string, mixed> $payload */
    private function jsonBodyWithExactBytes(array $payload, int $bytes, string $paddingKey): string
    {
        $emptyBody = $this->jsonBody($payload);
        $paddingBytes = $bytes - strlen($emptyBody);

        $this->assertGreaterThan(0, $paddingBytes);

        $payload[$paddingKey] = str_repeat('x', $paddingBytes);
        $body = $this->jsonBody($payload);

        $this->assertSame($bytes, strlen($body));

        return $body;
    }

    /** @return array<string, mixed> */
    private function maximumContractPayload(): array
    {
        $text = static fn (int $characters): string => str_repeat('😀', $characters);
        $url = static fn (): string => 'https://example.com/'.str_repeat('a', 2028);
        $experience = [
            'employer' => $text(120),
            'role' => $text(120),
            'location' => $text(120),
            'start_date' => '2000-01',
            'end_date' => '2001-01',
            'is_current' => false,
            'highlights' => array_fill(0, 8, $text(300)),
        ];
        $education = [
            'institution' => $text(120),
            'qualification' => $text(160),
            'field_of_study' => $text(120),
            'location' => $text(120),
            'start_date' => '2000-01',
            'end_date' => '2001-01',
            'is_current' => false,
            'description' => $text(600),
        ];
        $skillGroup = static fn (int $skills): array => [
            'name' => $text(60),
            'skills' => array_fill(0, $skills, ['name' => $text(80)]),
        ];
        $project = [
            'name' => $text(120),
            'role' => $text(120),
            'description' => $text(600),
            'url' => $url(),
            'start_date' => '2000-01',
            'end_date' => '2001-01',
            'is_current' => false,
            'highlights' => array_fill(0, 8, $text(300)),
            'technologies' => array_fill(0, 20, $text(60)),
        ];
        $certification = [
            'name' => $text(160),
            'issuer' => $text(120),
            'issued_on' => '2000-01',
            'expires_on' => '2001-01',
            'credential_id' => $text(120),
            'credential_url' => $url(),
        ];

        return [
            'title' => $text(100),
            'template_key' => 'jakes-resume',
            'full_name' => $text(120),
            'professional_headline' => $text(160),
            'contact_email' => 'ada@example.com',
            'phone' => $text(40),
            'location' => $text(120),
            'professional_summary' => $text(1200),
            'work_experiences' => array_fill(0, 15, $experience),
            'education_entries' => array_fill(0, 10, $education),
            'skill_groups' => [
                $skillGroup(20),
                ...array_fill(0, 8, $skillGroup(9)),
                $skillGroup(8),
            ],
            'projects' => array_fill(0, 15, $project),
            'certifications' => array_fill(0, 20, $certification),
            'links' => array_fill(0, 8, [
                'type' => 'other',
                'label' => $text(60),
                'url' => $url(),
            ]),
        ];
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'title' => '  CV de Ada  ',
            'template_key' => 'jakes-resume',
            'full_name' => '  Ada Lovelace  ',
            'professional_headline' => ' Matemática y programadora ',
            'contact_email' => ' ada@example.com ',
            'phone' => '',
            'location' => ' Londres ',
            'professional_summary' => ' Pionera de la programación. ',
            'work_experiences' => [],
            'education_entries' => [],
            'skill_groups' => [],
            'projects' => [
                [
                    'name' => ' Motor analítico ',
                    'role' => null,
                    'description' => null,
                    'url' => null,
                    'start_date' => null,
                    'end_date' => null,
                    'is_current' => false,
                    'highlights' => [],
                    'technologies' => [],
                ],
            ],
            'certifications' => [],
            'links' => [],
        ];
    }
}
