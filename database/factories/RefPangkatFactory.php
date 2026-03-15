<?php

namespace Database\Factories;

use App\Models\RefPangkat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RefPangkat>
 */
class RefPangkatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $golongan = fake()->randomElement(['I', 'II', 'III', 'IV']);

        return [
            'kode' => fake()->unique()->bothify('PANGKAT-###'),
            'nama' => fake()->words(2, true),
            'golongan' => $golongan,
            'ruang' => fake()->randomElement($golongan === 'IV' ? ['a', 'b', 'c', 'd', 'e'] : ['a', 'b', 'c', 'd']),
            'tingkat' => match ($golongan) {
                'I' => 'Juru',
                'II' => 'Pengatur',
                'III' => 'Penata',
                default => 'Pembina',
            },
            'urutan' => fake()->numberBetween(1, 99),
        ];
    }
}
