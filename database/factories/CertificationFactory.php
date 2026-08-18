<?php

namespace Database\Factories;

use App\Models\Certification;
use App\Models\Cv;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certification>
 */
class CertificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issuedOn = now()->subMonths(fake()->numberBetween(1, 48))->startOfMonth();

        return [
            'cv_id' => Cv::factory(),
            'name' => fake()->words(3, true),
            'issuer' => fake()->company(),
            'issued_on' => $issuedOn,
            'expires_on' => $issuedOn->copy()->addYears(2),
            'credential_id' => fake()->optional()->bothify('CERT-####'),
            'credential_url' => fake()->optional()->url(),
            'position' => 0,
        ];
    }

    public function withoutDates(): static
    {
        return $this->state(fn (): array => [
            'issued_on' => null,
            'expires_on' => null,
        ]);
    }
}
