<?php

namespace Database\Factories;

use App\Enums\JenisJabatan;
use App\Models\RefJabatan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RefJabatan>
 */
class RefJabatanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $jenisJabatan = fake()->randomElement(JenisJabatan::cases());

        return [
            'kode' => fake()->unique()->bothify('JAB-###'),
            'nama' => fake()->jobTitle(),
            'jenis_jabatan' => $jenisJabatan->value,
            'eselon' => $jenisJabatan === JenisJabatan::Struktural
                ? fake()->randomElement(['II', 'III', 'IV'])
                : null,
            'kelas_jabatan' => fake()->optional()->numberBetween(5, 17),
        ];
    }
}
