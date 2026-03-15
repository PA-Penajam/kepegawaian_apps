<?php

namespace Database\Factories;

use App\Models\RefPermission;
use Illuminate\Database\Eloquent\Factories\Factory;

class RefPermissionFactory extends Factory
{
    protected $model = RefPermission::class;

    public function definition(): array
    {
        return [
            'nama' => fake()->unique()->words(2, true),
            'group' => fake()->optional()->word(),
            'keterangan' => fake()->optional()->sentence(),
        ];
    }
}
