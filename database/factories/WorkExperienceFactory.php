<?php

namespace Database\Factories;

use App\Models\Cv;
use App\Models\WorkExperience;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkExperience>
 */
class WorkExperienceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = now()->subMonths(fake()->numberBetween(25, 120))->startOfMonth();

        return [
            'cv_id' => Cv::factory(),
            'employer' => fake()->company(),
            'role' => fake()->jobTitle(),
            'location' => fake()->optional()->city(),
            'start_date' => $startDate,
            'end_date' => $startDate->copy()->addMonths(fake()->numberBetween(1, 24)),
            'is_current' => false,
            'highlights' => [fake()->sentence()],
            'position' => 0,
        ];
    }

    public function current(): static
    {
        return $this->state(fn (): array => [
            'end_date' => null,
            'is_current' => true,
        ]);
    }
}
