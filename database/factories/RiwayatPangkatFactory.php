<?php

namespace Database\Factories;

use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\RiwayatPangkat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiwayatPangkat>
 */
class RiwayatPangkatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pegawai_id' => Pegawai::factory(),
            'ref_pangkat_id' => RefPangkat::factory(),
            'no_sk' => fake()->unique()->bothify('SK-###/PA.PNJ/##/####'),
            'tanggal_sk' => fake()->date(),
            'tmt' => fake()->date(),
            'pejabat_penetap' => fake()->optional()->name(),
            'masa_kerja_tahun' => fake()->numberBetween(0, 35),
            'masa_kerja_bulan' => fake()->numberBetween(0, 11),
            'gaji_pokok' => fake()->optional()->randomFloat(2, 1000000, 10000000),
            'is_aktif' => false,
            'keterangan' => fake()->optional()->sentence(),
        ];
    }
}
