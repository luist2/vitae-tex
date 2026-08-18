<?php

namespace Database\Factories;

use App\Models\Certification;
use App\Models\Cv;
use App\Models\CvLink;
use App\Models\EducationEntry;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillGroup;
use App\Models\User;
use App\Models\WorkExperience;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cv>
 */
class CvFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->words(3, true),
            'template_key' => 'jakes-resume',
            'full_name' => fake()->name(),
            'professional_headline' => fake()->optional()->jobTitle(),
            'contact_email' => fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'location' => fake()->optional()->city(),
            'professional_summary' => fake()->optional()->paragraph(),
        ];
    }

    public function withContent(): static
    {
        return $this->afterCreating(function (Cv $cv): void {
            WorkExperience::factory()->for($cv)->create();
            EducationEntry::factory()->for($cv)->create();

            $skillGroup = SkillGroup::factory()->for($cv)->create();
            Skill::factory()->count(2)->for($skillGroup)->sequence(
                ['position' => 0],
                ['position' => 1],
            )->create();

            Project::factory()->for($cv)->create();
            Certification::factory()->for($cv)->create();
            CvLink::factory()->for($cv)->create();
        });
    }
}
