<?php

namespace Database\Factories;

use App\Models\Pegawai;
use App\Models\Penghargaan;
use App\Models\RefJenisPenghargaan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Penghargaan>
 */
class PenghargaanFactory extends Factory
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
            'ref_jenis_penghargaan_id' => fake()->boolean(80) ? RefJenisPenghargaan::factory() : null,
            'nama_penghargaan' => fake()->unique()->sentence(3),
            'no_sk' => fake()->optional()->bothify('SK-###/PA.PNJ/##/####'),
            'tanggal_sk' => fake()->optional()->date(),
            'pejabat_penetap' => fake()->optional()->name(),
            'keterangan' => fake()->optional()->sentence(),
        ];
    }
}
