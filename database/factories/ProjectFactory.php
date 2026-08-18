<?php

namespace Database\Factories;

use App\Models\Cv;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = now()->subMonths(fake()->numberBetween(6, 60))->startOfMonth();

        return [
            'cv_id' => Cv::factory(),
            'name' => fake()->words(3, true),
            'role' => fake()->optional()->jobTitle(),
            'description' => fake()->optional()->sentence(),
            'url' => fake()->optional()->url(),
            'start_date' => $startDate,
            'end_date' => $startDate->copy()->addMonths(fake()->numberBetween(1, 6)),
            'is_current' => false,
            'highlights' => [fake()->sentence()],
            'technologies' => fake()->randomElements(
                ['PHP', 'Laravel', 'Vue', 'TypeScript', 'PostgreSQL'],
                2,
            ),
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

    public function withoutDates(): static
    {
        return $this->state(fn (): array => [
            'start_date' => null,
            'end_date' => null,
            'is_current' => false,
        ]);
    }
}
