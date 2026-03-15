<?php

namespace Database\Factories;

use App\Models\RefJenisPenghargaan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RefJenisPenghargaan>
 */
class RefJenisPenghargaanFactory extends Factory
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
            'keterangan' => fake()->optional()->sentence(),
        ];
    }
}
