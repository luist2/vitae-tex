<?php

namespace Tests\Feature\Cvs;

use App\Actions\Cvs\SaveCv;
use App\Http\Resources\CvEditorResource;
use App\Models\Cv;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CvEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_editor_loads_the_complete_ordered_cv_contract(): void
    {
        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->withContent()->create([
            'title' => 'CV completo',
        ]);

        $this->actingAs($owner)
            ->get(route('cvs.edit', $cv))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cvs/Edit')
                ->where('cv.id', $cv->id)
                ->where('cv.title', 'CV completo')
                ->where('cv.template_key', 'jakes-resume')
                ->where('template.key', 'jakes-resume')
                ->where('template.name', "Jake's Resume")
                ->where('template.sections', [
                    'professional_summary',
                    'education',
                    'work_experience',
                    'projects',
                    'skills',
                    'certifications',
                ])
                ->has('cv.work_experiences', 1)
                ->has('cv.education_entries', 1)
                ->has('cv.skill_groups', 1)
                ->has('cv.skill_groups.0.skills', 2)
                ->has('cv.projects', 1)
                ->has('cv.certifications', 1)
                ->has('cv.links', 1)
                ->where('cv.work_experiences.0.start_date', fn (string $date): bool => preg_match('/^\d{4}-\d{2}$/', $date) === 1)
                ->missing('cv.work_experiences.0.id')
                ->missing('cv.work_experiences.0.position')
                ->missing('cv.skill_groups.0.skills.0.id'));
    }

    public function test_the_editor_contract_can_save_basic_fields_without_losing_hidden_collections(): void
    {
        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->withContent()->create();
        $cv->load([
            'workExperiences',
            'educationEntries',
            'skillGroups.skills',
            'projects',
            'certifications',
            'links',
        ]);
        $payload = CvEditorResource::make($cv)->resolve();
        unset($payload['id'], $payload['updated_at']);

        $payload['title'] = 'CV para backend';
        $payload['full_name'] = 'Grace Hopper';
        $payload['professional_headline'] = 'Pionera de la computación';
        $payload['contact_email'] = 'grace@example.com';
        $payload['professional_summary'] = 'Desarrolladora de compiladores y oficial naval.';

        $this->actingAs($owner)
            ->patch(route('cvs.update', $cv), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('cvs.edit', $cv));

        $cv->refresh()->load([
            'workExperiences',
            'educationEntries',
            'skillGroups.skills',
            'projects',
            'certifications',
            'links',
        ]);

        $this->assertSame('CV para backend', $cv->title);
        $this->assertSame('Grace Hopper', $cv->full_name);
        $this->assertSame('grace@example.com', $cv->contact_email);
        $this->assertCount(1, $cv->workExperiences);
        $this->assertCount(1, $cv->educationEntries);
        $this->assertCount(1, $cv->skillGroups);
        $this->assertCount(2, $cv->skillGroups->sole()->skills);
        $this->assertCount(1, $cv->projects);
        $this->assertCount(1, $cv->certifications);
        $this->assertCount(1, $cv->links);
    }

    public function test_an_owner_can_save_the_complete_cv_and_server_normalizes_every_position(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $cv = Cv::factory()->for($owner)->withContent()->create();
        $foreignCv = Cv::factory()->for($otherUser)->withContent()->create();
        $oldExperienceIds = $cv->workExperiences()->pluck('id')->all();
        $foreignExperience = $foreignCv->workExperiences()->sole();

        $payload = $this->validPayload();
        $payload['work_experiences'][0]['id'] = $foreignExperience->id;
        $payload['work_experiences'][0]['position'] = 99;
        $payload['skill_groups'][0]['skills'][0]['id'] = $foreignCv->skillGroups()->sole()->skills()->first()->id;
        $payload['skill_groups'][0]['skills'][0]['position'] = 88;

        $this->actingAs($owner)
            ->patch(route('cvs.update', $cv), $payload)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'CV guardado correctamente.')
            ->assertRedirect(route('cvs.edit', $cv));

        $cv->refresh()->load([
            'workExperiences',
            'educationEntries',
            'skillGroups.skills',
            'projects',
            'certifications',
            'links',
        ]);

        $this->assertSame('CV de Ada', $cv->title);
        $this->assertSame('Ada Lovelace', $cv->full_name);
        $this->assertSame('ada@example.com', $cv->contact_email);
        $this->assertNull($cv->phone);
        $this->assertSame(['Analytical Engines', 'Royal Society'], $cv->workExperiences->pluck('employer')->all());
        $this->assertSame([0, 1], $cv->workExperiences->pluck('position')->all());
        $this->assertSame('1842-01-01', $cv->workExperiences[0]->start_date->toDateString());
        $this->assertSame([0], $cv->educationEntries->pluck('position')->all());
        $this->assertSame([0, 1], $cv->skillGroups->pluck('position')->all());
        $this->assertSame([0, 1], $cv->skillGroups[0]->skills->pluck('position')->all());
        $this->assertSame([0], $cv->projects->pluck('position')->all());
        $this->assertSame([0], $cv->certifications->pluck('position')->all());
        $this->assertSame([0], $cv->links->pluck('position')->all());
        $this->assertSame(['PHP', 'Vue'], $cv->projects->sole()->technologies);

        foreach ($oldExperienceIds as $oldExperienceId) {
            $this->assertDatabaseMissing('work_experiences', ['id' => $oldExperienceId]);
        }

        $this->assertDatabaseHas('work_experiences', [
            'id' => $foreignExperience->id,
            'cv_id' => $foreignCv->id,
            'employer' => $foreignExperience->employer,
        ]);
        $this->assertNotContains($foreignExperience->id, $cv->workExperiences->pluck('id')->all());
    }

    public function test_invalid_nested_content_leaves_the_existing_cv_unchanged(): void
    {
        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->withContent()->create([
            'title' => 'Estado persistido',
        ]);
        $originalExperienceIds = $cv->workExperiences()->pluck('id')->all();
        $payload = $this->validPayload();
        $payload['contact_email'] = null;
        $payload['phone'] = '   ';
        $payload['links'] = [];
        $payload['work_experiences'][0]['is_current'] = false;
        $payload['work_experiences'][0]['end_date'] = null;
        $payload['education_entries'][0]['is_current'] = true;
        $payload['projects'][0]['url'] = 'ftp://example.com/private';
        $payload['projects'][0]['is_current'] = true;
        $payload['projects'][0]['technologies'] = array_fill(0, 21, 'PHP');
        $payload['certifications'][0]['issued_on'] = '2026-06';
        $payload['certifications'][0]['expires_on'] = '2026-05';

        $this->actingAs($owner)
            ->from(route('cvs.edit', $cv))
            ->patch(route('cvs.update', $cv), $payload)
            ->assertSessionHasErrors([
                'contact_email',
                'work_experiences.0.end_date',
                'education_entries.0.end_date',
                'projects.0.url',
                'projects.0.end_date',
                'projects.0.technologies',
                'certifications.0.expires_on',
            ])
            ->assertRedirect(route('cvs.edit', $cv));

        $this->assertSame('Estado persistido', $cv->fresh()->title);
        $this->assertSame($originalExperienceIds, $cv->workExperiences()->pluck('id')->all());
    }

    public function test_work_experience_limits_and_nested_fields_use_the_editor_error_paths(): void
    {
        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create();
        $payload = $this->validPayload();
        $payload['work_experiences'][0] = [
            'employer' => '',
            'role' => '',
            'location' => null,
            'start_date' => '1842-1',
            'end_date' => '1841-12',
            'is_current' => false,
            'highlights' => array_fill(0, 9, 'Punto destacado'),
        ];
        $payload['work_experiences'][1]['end_date'] = '1839-12';

        $this->actingAs($owner)
            ->from(route('cvs.edit', $cv))
            ->patch(route('cvs.update', $cv), $payload)
            ->assertSessionHasErrors([
                'work_experiences.0.employer',
                'work_experiences.0.role',
                'work_experiences.0.start_date',
                'work_experiences.0.highlights',
                'work_experiences.1.end_date',
            ])
            ->assertRedirect(route('cvs.edit', $cv));

        $this->assertDatabaseCount('work_experiences', 0);
    }

    public function test_education_limits_and_nested_fields_use_the_editor_error_paths(): void
    {
        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create();
        $payload = $this->validPayload();
        $payload['education_entries'][0] = [
            'institution' => '',
            'qualification' => '',
            'field_of_study' => null,
            'location' => null,
            'start_date' => '1830-1',
            'end_date' => '1835-12',
            'is_current' => false,
            'description' => str_repeat('a', 601),
        ];
        $payload['education_entries'][] = [
            'institution' => 'Universidad de Londres',
            'qualification' => 'Matemáticas',
            'field_of_study' => null,
            'location' => 'Londres',
            'start_date' => '1840-01',
            'end_date' => '1839-12',
            'is_current' => false,
            'description' => null,
        ];

        $this->actingAs($owner)
            ->from(route('cvs.edit', $cv))
            ->patch(route('cvs.update', $cv), $payload)
            ->assertSessionHasErrors([
                'education_entries.0.institution',
                'education_entries.0.qualification',
                'education_entries.0.start_date',
                'education_entries.0.description',
                'education_entries.1.end_date',
            ])
            ->assertRedirect(route('cvs.edit', $cv));

        $payload = $this->validPayload();
        $payload['education_entries'] = array_fill(0, 11, $payload['education_entries'][0]);

        $this->actingAs($owner)
            ->patch(route('cvs.update', $cv), $payload)
            ->assertSessionHasErrors('education_entries');

        $this->assertDatabaseCount('education_entries', 0);
    }

    public function test_total_skill_limit_is_validated_across_groups(): void
    {
        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create();
        $payload = $this->validPayload();
        $payload['skill_groups'] = array_fill(0, 10, [
            'name' => 'Grupo',
            'skills' => array_fill(0, 11, ['name' => 'Habilidad']),
        ]);

        $this->actingAs($owner)
            ->patch(route('cvs.update', $cv), $payload)
            ->assertSessionHasErrors('skill_groups');

        $this->assertDatabaseCount('skill_groups', 0);
    }

    public function test_template_and_custom_link_values_are_limited_to_the_controlled_contract(): void
    {
        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create();
        $payload = $this->validPayload();
        $payload['template_key'] = 'uploaded-template';
        $payload['links'][0]['type'] = 'other';
        $payload['links'][0]['label'] = '   ';

        $this->actingAs($owner)
            ->patch(route('cvs.update', $cv), $payload)
            ->assertSessionHasErrors([
                'template_key',
                'links.0.label',
            ]);

        $this->assertSame('jakes-resume', $cv->fresh()->template_key);
    }

    public function test_save_cv_rolls_back_all_writes_if_a_late_database_write_fails(): void
    {
        $cv = Cv::factory()->withContent()->create([
            'title' => 'Antes de la transacción',
        ]);
        $originalExperienceIds = $cv->workExperiences()->pluck('id')->all();
        $payload = $this->validPayload();
        $payload['certifications'][0]['issued_on'] = '2026-06';
        $payload['certifications'][0]['expires_on'] = '2026-05';
        $failed = false;

        try {
            app(SaveCv::class)->handle($cv, $payload);
        } catch (QueryException) {
            $failed = true;
        }

        $this->assertTrue($failed, 'The invalid certification must reach the database constraint.');
        $this->assertSame('Antes de la transacción', $cv->fresh()->title);
        $this->assertSame($originalExperienceIds, $cv->workExperiences()->pluck('id')->all());
    }

    public function test_guests_and_other_users_cannot_update_a_cv(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $cv = Cv::factory()->for($owner)->create([
            'title' => 'CV privado',
        ]);
        $payload = $this->validPayload();

        $this->patch(route('cvs.update', $cv), $payload)
            ->assertRedirect('/login');

        $this->actingAs($otherUser)
            ->patch(route('cvs.update', $cv), $payload)
            ->assertNotFound()
            ->assertDontSee('CV privado');

        $this->assertSame('CV privado', $cv->fresh()->title);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'title' => '  CV de Ada  ',
            'template_key' => 'jakes-resume',
            'full_name' => '  Ada Lovelace  ',
            'professional_headline' => ' Matemática y programadora ',
            'contact_email' => ' ada@example.com ',
            'phone' => '   ',
            'location' => ' Londres ',
            'professional_summary' => ' Pionera de la programación. ',
            'work_experiences' => [
                [
                    'employer' => ' Analytical Engines ',
                    'role' => ' Investigadora ',
                    'location' => null,
                    'start_date' => '1842-01',
                    'end_date' => null,
                    'is_current' => true,
                    'highlights' => [' Diseñó el primer algoritmo. '],
                ],
                [
                    'employer' => ' Royal Society ',
                    'role' => ' Colaboradora ',
                    'location' => ' Londres ',
                    'start_date' => '1840-02',
                    'end_date' => '1841-03',
                    'is_current' => false,
                    'highlights' => [],
                ],
            ],
            'education_entries' => [
                [
                    'institution' => ' Educación privada ',
                    'qualification' => ' Matemáticas ',
                    'field_of_study' => null,
                    'location' => ' Londres ',
                    'start_date' => '1830-01',
                    'end_date' => '1835-12',
                    'is_current' => false,
                    'description' => null,
                ],
            ],
            'skill_groups' => [
                [
                    'name' => ' Lenguajes ',
                    'skills' => [
                        ['name' => ' PHP '],
                        ['name' => ' TypeScript '],
                    ],
                ],
                [
                    'name' => ' Frameworks ',
                    'skills' => [
                        ['name' => ' Laravel '],
                    ],
                ],
            ],
            'projects' => [
                [
                    'name' => ' Motor analítico ',
                    'role' => null,
                    'description' => ' Notas sobre computación. ',
                    'url' => 'https://example.com/project',
                    'start_date' => '1842-01',
                    'end_date' => '1843-12',
                    'is_current' => false,
                    'highlights' => [' Traducción y notas. '],
                    'technologies' => [' PHP ', ' Vue '],
                ],
            ],
            'certifications' => [
                [
                    'name' => ' Reconocimiento ',
                    'issuer' => ' Sociedad científica ',
                    'issued_on' => '1843-01',
                    'expires_on' => null,
                    'credential_id' => null,
                    'credential_url' => 'https://example.com/credential',
                ],
            ],
            'links' => [
                [
                    'type' => 'github',
                    'label' => null,
                    'url' => 'https://github.com/ada',
                ],
            ],
        ];
    }
}
