<?php

namespace Database\Factories;

use App\Models\Cv;
use App\Models\SkillGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkillGroup>
 */
class SkillGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cv_id' => Cv::factory(),
            'name' => fake()->randomElement(['Lenguajes', 'Frameworks', 'Herramientas']),
            'position' => 0,
        ];
    }
}
