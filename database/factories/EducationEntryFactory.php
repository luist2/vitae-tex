<?php

namespace Database\Factories;

use App\Models\Cv;
use App\Models\EducationEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EducationEntry>
 */
class EducationEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = now()->subMonths(fake()->numberBetween(25, 120))->startOfMonth();

        return [
            'cv_id' => Cv::factory(),
            'institution' => fake()->company().' University',
            'qualification' => fake()->randomElement(['Licenciatura', 'Ingeniería', 'Magíster']),
            'field_of_study' => fake()->optional()->words(2, true),
            'location' => fake()->optional()->city(),
            'start_date' => $startDate,
            'end_date' => $startDate->copy()->addMonths(fake()->numberBetween(12, 48)),
            'is_current' => false,
            'description' => fake()->optional()->sentence(),
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
