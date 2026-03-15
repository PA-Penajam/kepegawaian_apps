<?php

namespace Database\Factories;

use App\Models\RefRole;
use Illuminate\Database\Eloquent\Factories\Factory;

class RefRoleFactory extends Factory
{
    protected $model = RefRole::class;

    public function definition(): array
    {
        return [
            'nama' => fake()->unique()->words(2, true),
            'keterangan' => fake()->optional()->sentence(),
            'is_system' => false,
        ];
    }
}
