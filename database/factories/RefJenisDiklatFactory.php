<?php

namespace Database\Factories;

use App\Models\RefJenisDiklat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RefJenisDiklat>
 */
class RefJenisDiklatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->unique()->words(2, true),
            'keterangan' => fake()->optional()->sentence(),
        ];
    }
}
