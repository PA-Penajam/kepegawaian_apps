<?php

namespace Database\Factories;

use App\Models\RefStatusKepegawaian;
use Illuminate\Database\Eloquent\Factories\Factory;

class RefStatusKepegawaianFactory extends Factory
{
    protected $model = RefStatusKepegawaian::class;

    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->word(),
            'nama' => fake()->words(2, true),
            'keterangan' => fake()->optional()->sentence(),
        ];
    }
}
