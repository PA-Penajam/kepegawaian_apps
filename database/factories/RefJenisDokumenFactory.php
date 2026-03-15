<?php

namespace Database\Factories;

use App\Models\RefJenisDokumen;
use Illuminate\Database\Eloquent\Factories\Factory;

class RefJenisDokumenFactory extends Factory
{
    protected $model = RefJenisDokumen::class;

    public function definition(): array
    {
        return [
            'nama' => fake()->words(2, true),
            'keterangan' => fake()->optional()->sentence(),
        ];
    }
}
