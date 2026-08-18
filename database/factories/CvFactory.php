<?php

namespace Database\Factories;

use App\Models\Cv;
use App\Models\User;
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
}
