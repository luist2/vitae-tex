<?php

namespace Tests\Feature\Database;

use App\Models\Certification;
use App\Models\Cv;
use App\Models\CvLink;
use App\Models\EducationEntry;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillGroup;
use App\Models\WorkExperience;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CvContentConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cv_revision_must_be_positive(): void
    {
        $cv = Cv::factory()->create();

        $this->expectException(QueryException::class);

        $cv->forceFill(['revision' => 0])->save();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('invalidWorkExperienceStates')]
    public function test_work_experience_constraints_reject_invalid_states(array $attributes): void
    {
        $cv = Cv::factory()->create();

        $this->expectException(QueryException::class);

        WorkExperience::factory()->for($cv)->create($attributes);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidWorkExperienceStates(): array
    {
        return [
            'non-current without end' => [[
                'is_current' => false,
                'end_date' => null,
            ]],
            'current with end' => [[
                'is_current' => true,
                'end_date' => '2025-01-01',
            ]],
            'inverted range' => [[
                'start_date' => '2025-02-01',
                'end_date' => '2025-01-01',
            ]],
            'daily precision' => [[
                'start_date' => '2025-01-02',
                'end_date' => '2025-02-01',
            ]],
            'non-array highlights' => [[
                'highlights' => ['summary' => 'not a list'],
            ]],
            'negative position' => [[
                'position' => -1,
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('invalidEducationStates')]
    public function test_education_constraints_reject_invalid_states(array $attributes): void
    {
        $cv = Cv::factory()->create();

        $this->expectException(QueryException::class);

        EducationEntry::factory()->for($cv)->create($attributes);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidEducationStates(): array
    {
        return [
            'non-current without end' => [[
                'is_current' => false,
                'end_date' => null,
            ]],
            'current with end' => [[
                'is_current' => true,
                'end_date' => '2025-01-01',
            ]],
            'inverted range' => [[
                'start_date' => '2025-02-01',
                'end_date' => '2025-01-01',
            ]],
            'daily precision' => [[
                'start_date' => '2025-01-15',
                'end_date' => '2025-02-01',
            ]],
            'negative position' => [[
                'position' => -1,
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('invalidProjectStates')]
    public function test_project_constraints_reject_invalid_states(array $attributes): void
    {
        $cv = Cv::factory()->create();

        $this->expectException(QueryException::class);

        Project::factory()->for($cv)->create($attributes);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidProjectStates(): array
    {
        return [
            'end without start' => [[
                'start_date' => null,
                'end_date' => '2025-01-01',
            ]],
            'current without start' => [[
                'start_date' => null,
                'end_date' => null,
                'is_current' => true,
            ]],
            'current with end' => [[
                'is_current' => true,
                'end_date' => '2025-01-01',
            ]],
            'inverted range' => [[
                'start_date' => '2025-02-01',
                'end_date' => '2025-01-01',
            ]],
            'daily precision' => [[
                'start_date' => '2025-01-02',
                'end_date' => null,
            ]],
            'non-array highlights' => [[
                'highlights' => ['summary' => 'not a list'],
            ]],
            'non-array technologies' => [[
                'technologies' => ['primary' => 'PHP'],
            ]],
            'negative position' => [[
                'position' => -1,
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('invalidCertificationStates')]
    public function test_certification_constraints_reject_invalid_states(array $attributes): void
    {
        $cv = Cv::factory()->create();

        $this->expectException(QueryException::class);

        Certification::factory()->for($cv)->create($attributes);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidCertificationStates(): array
    {
        return [
            'expiration without issue' => [[
                'issued_on' => null,
                'expires_on' => '2025-01-01',
            ]],
            'inverted range' => [[
                'issued_on' => '2025-02-01',
                'expires_on' => '2025-01-01',
            ]],
            'daily precision' => [[
                'issued_on' => '2025-01-15',
            ]],
            'negative position' => [[
                'position' => -1,
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('invalidLinkStates')]
    public function test_link_constraints_reject_invalid_states(array $attributes): void
    {
        $cv = Cv::factory()->create();

        $this->expectException(QueryException::class);

        CvLink::factory()->for($cv)->create($attributes);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidLinkStates(): array
    {
        return [
            'unknown type' => [[
                'type' => 'website',
            ]],
            'other without label' => [[
                'type' => 'other',
                'label' => null,
            ]],
            'other with blank label' => [[
                'type' => 'other',
                'label' => '   ',
            ]],
            'negative position' => [[
                'position' => -1,
            ]],
        ];
    }

    public function test_position_constraints_reject_negative_skill_positions(): void
    {
        $skillGroup = SkillGroup::factory()->create();

        $this->expectException(QueryException::class);

        Skill::factory()->for($skillGroup)->create(['position' => -1]);
    }

    public function test_position_constraints_reject_negative_skill_group_positions(): void
    {
        $cv = Cv::factory()->create();

        $this->expectException(QueryException::class);

        SkillGroup::factory()->for($cv)->create(['position' => -1]);
    }

    public function test_foreign_keys_reject_an_orphan_cv_child(): void
    {
        $this->expectException(QueryException::class);

        WorkExperience::factory()->create(['cv_id' => PHP_INT_MAX]);
    }

    public function test_foreign_keys_reject_an_orphan_skill(): void
    {
        $this->expectException(QueryException::class);

        Skill::factory()->create(['skill_group_id' => PHP_INT_MAX]);
    }
}
