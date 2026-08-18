<?php

namespace Database\Factories;

use App\Models\Skill;
use App\Models\SkillGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Skill>
 */
class SkillFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'skill_group_id' => SkillGroup::factory(),
            'name' => fake()->randomElement(['PHP', 'TypeScript', 'PostgreSQL', 'Vue', 'Laravel']),
            'position' => 0,
        ];
    }
}
