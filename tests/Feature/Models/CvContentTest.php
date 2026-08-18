<?php

namespace Tests\Feature\Models;

use App\Models\Certification;
use App\Models\Cv;
use App\Models\CvLink;
use App\Models\EducationEntry;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillGroup;
use App\Models\WorkExperience;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CvContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_complete_cv_factory_creates_every_content_relation(): void
    {
        $cv = Cv::factory()->withContent()->create();

        $this->assertTrue($cv->workExperiences->sole()->cv->is($cv));
        $this->assertTrue($cv->educationEntries->sole()->cv->is($cv));
        $this->assertTrue($cv->skillGroups->sole()->cv->is($cv));
        $this->assertCount(2, $cv->skillGroups->sole()->skills);
        $this->assertTrue($cv->projects->sole()->cv->is($cv));
        $this->assertTrue($cv->certifications->sole()->cv->is($cv));
        $this->assertTrue($cv->links->sole()->cv->is($cv));
    }

    public function test_direct_cv_collections_are_ordered_by_position_and_id(): void
    {
        $cv = Cv::factory()->create();

        foreach ($this->directRelations() as $relation => $factory) {
            $models = $factory
                ->for($cv)
                ->sequence(
                    ['position' => 1],
                    ['position' => 0],
                    ['position' => 1],
                )
                ->count(3)
                ->create();

            $this->assertSame(
                [$models[1]->id, $models[0]->id, $models[2]->id],
                $cv->{$relation}()->pluck('id')->all(),
                "The [{$relation}] relation is not ordered deterministically.",
            );
        }
    }

    public function test_skills_are_ordered_within_their_group(): void
    {
        $skillGroup = SkillGroup::factory()->create();
        $skills = Skill::factory()
            ->for($skillGroup)
            ->sequence(
                ['position' => 2],
                ['position' => 0],
                ['position' => 1],
            )
            ->count(3)
            ->create();

        $this->assertSame(
            [$skills[1]->id, $skills[2]->id, $skills[0]->id],
            $skillGroup->skills()->pluck('id')->all(),
        );
    }

    public function test_dates_booleans_json_arrays_and_positions_are_cast(): void
    {
        $workExperience = WorkExperience::factory()->create();
        $project = Project::factory()->create();
        $certification = Certification::factory()->create();

        $this->assertInstanceOf(Carbon::class, $workExperience->start_date);
        $this->assertInstanceOf(Carbon::class, $workExperience->end_date);
        $this->assertIsBool($workExperience->is_current);
        $this->assertIsArray($workExperience->highlights);
        $this->assertIsInt($workExperience->position);

        $this->assertInstanceOf(Carbon::class, $project->start_date);
        $this->assertInstanceOf(Carbon::class, $project->end_date);
        $this->assertIsBool($project->is_current);
        $this->assertIsArray($project->highlights);
        $this->assertIsArray($project->technologies);
        $this->assertIsInt($project->position);

        $this->assertInstanceOf(Carbon::class, $certification->issued_on);
        $this->assertInstanceOf(Carbon::class, $certification->expires_on);
        $this->assertIsInt($certification->position);
    }

    public function test_current_and_optional_date_states_can_be_persisted(): void
    {
        $cv = Cv::factory()->create();

        $currentWork = WorkExperience::factory()->for($cv)->current()->create();
        $currentEducation = EducationEntry::factory()->for($cv)->current()->create();
        $currentProject = Project::factory()->for($cv)->current()->create();
        $undatedProject = Project::factory()->for($cv)->withoutDates()->create();
        $undatedCertification = Certification::factory()->for($cv)->withoutDates()->create();
        $unlabelledGithub = CvLink::factory()->for($cv)->create([
            'type' => 'github',
            'label' => null,
        ]);

        $this->assertTrue($currentWork->is_current);
        $this->assertNull($currentWork->end_date);
        $this->assertTrue($currentEducation->is_current);
        $this->assertNull($currentEducation->end_date);
        $this->assertTrue($currentProject->is_current);
        $this->assertNull($currentProject->end_date);
        $this->assertNull($undatedProject->start_date);
        $this->assertNull($undatedProject->end_date);
        $this->assertNull($undatedCertification->issued_on);
        $this->assertNull($undatedCertification->expires_on);
        $this->assertNull($unlabelledGithub->label);
    }

    public function test_parent_ids_cannot_be_changed_through_mass_assignment(): void
    {
        $cv = Cv::factory()->withContent()->create();
        $otherCv = Cv::factory()->create();

        foreach ($this->contentModels($cv) as $model) {
            $model->fill(['cv_id' => $otherCv->id])->save();

            $this->assertSame($cv->id, $model->fresh()->cv_id);
        }

        $skillGroup = $cv->skillGroups->sole();
        $otherSkillGroup = SkillGroup::factory()->for($otherCv)->create();
        $skill = $skillGroup->skills->firstOrFail();
        $skill->fill(['skill_group_id' => $otherSkillGroup->id])->save();

        $this->assertSame($skillGroup->id, $skill->fresh()->skill_group_id);
    }

    public function test_deleting_a_cv_cascades_to_all_content(): void
    {
        $cv = Cv::factory()->withContent()->create();

        $cv->delete();

        foreach ([
            'work_experiences',
            'education_entries',
            'skill_groups',
            'skills',
            'projects',
            'certifications',
            'cv_links',
        ] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_deleting_a_skill_group_cascades_to_its_skills_only(): void
    {
        $cv = Cv::factory()->create();
        $skillGroup = SkillGroup::factory()->for($cv)->create();
        $otherSkillGroup = SkillGroup::factory()->for($cv)->create();
        Skill::factory()->count(2)->for($skillGroup)->create();
        $otherSkill = Skill::factory()->for($otherSkillGroup)->create();

        $skillGroup->delete();

        $this->assertDatabaseMissing('skills', ['skill_group_id' => $skillGroup->id]);
        $this->assertDatabaseHas('skills', ['id' => $otherSkill->id]);
    }

    /**
     * @return array<string, Factory>
     */
    private function directRelations(): array
    {
        return [
            'workExperiences' => WorkExperience::factory(),
            'educationEntries' => EducationEntry::factory(),
            'skillGroups' => SkillGroup::factory(),
            'projects' => Project::factory(),
            'certifications' => Certification::factory(),
            'links' => CvLink::factory(),
        ];
    }

    /**
     * @return list<WorkExperience|EducationEntry|SkillGroup|Project|Certification|CvLink>
     */
    private function contentModels(Cv $cv): array
    {
        return [
            $cv->workExperiences->sole(),
            $cv->educationEntries->sole(),
            $cv->skillGroups->sole(),
            $cv->projects->sole(),
            $cv->certifications->sole(),
            $cv->links->sole(),
        ];
    }
}
