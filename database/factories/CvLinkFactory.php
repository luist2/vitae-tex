<?php

namespace Database\Factories;

use App\Models\Cv;
use App\Models\CvLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CvLink>
 */
class CvLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cv_id' => Cv::factory(),
            'type' => 'other',
            'label' => fake()->word(),
            'url' => fake()->url(),
            'position' => 0,
        ];
    }
}
