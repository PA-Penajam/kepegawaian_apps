<?php

namespace Database\Factories;

use App\Models\RefStatusPegawai;
use Illuminate\Database\Eloquent\Factories\Factory;

class RefStatusPegawaiFactory extends Factory
{
    protected $model = RefStatusPegawai::class;

    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->word(),
            'nama' => fake()->words(2, true),
            'keterangan' => fake()->optional()->sentence(),
        ];
    }
}
