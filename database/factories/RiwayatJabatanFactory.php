<?php

namespace Database\Factories;

use App\Models\Pegawai;
use App\Models\RefJabatan;
use App\Models\RefUnitKerja;
use App\Models\RiwayatJabatan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiwayatJabatan>
 */
class RiwayatJabatanFactory extends Factory
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
            'ref_jabatan_id' => RefJabatan::factory(),
            'ref_unit_kerja_id' => RefUnitKerja::factory(),
            'no_sk' => fake()->unique()->numerify('SK-####'),
            'tanggal_sk' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'tmt' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'pejabat_penetap' => fake()->name(),
            'is_aktif' => false,
            'keterangan' => fake()->optional()->sentence(),
        ];
    }
}
