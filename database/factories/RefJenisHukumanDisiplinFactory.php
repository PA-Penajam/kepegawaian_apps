<?php

namespace Database\Factories;

use App\Models\RefJenisHukumanDisiplin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RefJenisHukumanDisiplin>
 */
class RefJenisHukumanDisiplinFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->unique()->words(3, true),
            'tingkat' => fake()->randomElement(['ringan', 'sedang', 'berat']),
            'keterangan' => fake()->optional()->sentence(),
        ];
    }
}
