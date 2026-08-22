<?php

namespace Tests\Feature\Cvs;

use App\Http\Resources\CvEditorResource;
use App\Models\Cv;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CvCollectionLimitsTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('collectionLimitProvider')]
    public function test_exceeding_a_collection_limit_leaves_the_complete_cv_unchanged(
        string $scenario,
        string $errorPath,
    ): void {
        $owner = User::factory()->create();
        $cv = Cv::factory()->for($owner)->withContent()->create([
            'title' => 'Estado completo persistido',
        ]);
        $originalState = $this->aggregateState($cv);
        $payload = $originalState['content'];
        unset($payload['id'], $payload['revision'], $payload['updated_at']);
        $payload['title'] = 'Este título no debe persistirse';
        $this->exceedLimit($payload, $scenario);

        $this->actingAs($owner)
            ->from(route('cvs.edit', $cv))
            ->patch(route('cvs.update', $cv), $payload)
            ->assertSessionHasErrors($errorPath)
            ->assertRedirect(route('cvs.edit', $cv));

        $this->assertSame($originalState, $this->aggregateState($cv));
    }

    /** @return array<string, array{string, string}> */
    public static function collectionLimitProvider(): array
    {
        return [
            'work experiences per CV' => ['work_experiences', 'work_experiences'],
            'highlights per work experience' => ['work_highlights', 'work_experiences.0.highlights'],
            'education entries per CV' => ['education_entries', 'education_entries'],
            'skill groups per CV' => ['skill_groups', 'skill_groups'],
            'skills per group' => ['skills_per_group', 'skill_groups.0.skills'],
            'skills in total per CV' => ['total_skills', 'skill_groups'],
            'projects per CV' => ['projects', 'projects'],
            'highlights per project' => ['project_highlights', 'projects.0.highlights'],
            'technologies per project' => ['project_technologies', 'projects.0.technologies'],
            'certifications per CV' => ['certifications', 'certifications'],
            'links per CV' => ['links', 'links'],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function exceedLimit(array &$payload, string $scenario): void
    {
        switch ($scenario) {
            case 'work_experiences':
                $payload['work_experiences'] = array_fill(0, 16, $payload['work_experiences'][0]);
                break;
            case 'work_highlights':
                $payload['work_experiences'][0]['highlights'] = array_fill(0, 9, 'Resultado');
                break;
            case 'education_entries':
                $payload['education_entries'] = array_fill(0, 11, $payload['education_entries'][0]);
                break;
            case 'skill_groups':
                $payload['skill_groups'] = array_fill(0, 11, $payload['skill_groups'][0]);
                break;
            case 'skills_per_group':
                $payload['skill_groups'][0]['skills'] = array_fill(0, 21, ['name' => 'Habilidad']);
                break;
            case 'total_skills':
                $payload['skill_groups'] = [
                    ...array_fill(0, 5, [
                        'name' => 'Grupo completo',
                        'skills' => array_fill(0, 20, ['name' => 'Habilidad']),
                    ]),
                    [
                        'name' => 'Grupo adicional',
                        'skills' => [['name' => 'Habilidad 101']],
                    ],
                ];
                break;
            case 'projects':
                $payload['projects'] = array_fill(0, 16, $payload['projects'][0]);
                break;
            case 'project_highlights':
                $payload['projects'][0]['highlights'] = array_fill(0, 9, 'Resultado');
                break;
            case 'project_technologies':
                $payload['projects'][0]['technologies'] = array_fill(0, 21, 'Tecnología');
                break;
            case 'certifications':
                $payload['certifications'] = array_fill(0, 21, $payload['certifications'][0]);
                break;
            case 'links':
                $payload['links'] = array_fill(0, 9, $payload['links'][0]);
                break;
            default:
                $this->fail("Unknown collection limit scenario: {$scenario}");
        }
    }

    /** @return array{content: array<string, mixed>, ids: array<string, array<int, int>>} */
    private function aggregateState(Cv $cv): array
    {
        $cv->refresh()->load([
            'workExperiences',
            'educationEntries',
            'skillGroups.skills',
            'projects',
            'certifications',
            'links',
        ]);

        return [
            'content' => CvEditorResource::make($cv)->resolve(),
            'ids' => [
                'work_experiences' => $cv->workExperiences->pluck('id')->all(),
                'education_entries' => $cv->educationEntries->pluck('id')->all(),
                'skill_groups' => $cv->skillGroups->pluck('id')->all(),
                'skills' => $cv->skillGroups
                    ->flatMap(fn ($group) => $group->skills->pluck('id'))
                    ->values()
                    ->all(),
                'projects' => $cv->projects->pluck('id')->all(),
                'certifications' => $cv->certifications->pluck('id')->all(),
                'cv_links' => $cv->links->pluck('id')->all(),
            ],
        ];
    }
}
