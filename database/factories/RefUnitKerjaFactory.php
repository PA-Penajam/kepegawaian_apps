<?php

namespace Database\Factories;

use App\Models\RefUnitKerja;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RefUnitKerja>
 */
class RefUnitKerjaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->bothify('UNIT-###'),
            'nama' => fake()->company(),
            'parent_id' => null,
            'urutan' => fake()->numberBetween(1, 20),
        ];
    }
}
