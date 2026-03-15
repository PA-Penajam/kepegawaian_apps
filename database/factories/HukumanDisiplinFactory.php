<?php

namespace Database\Factories;

use App\Models\HukumanDisiplin;
use App\Models\Pegawai;
use App\Models\RefJenisHukumanDisiplin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HukumanDisiplin>
 */
class HukumanDisiplinFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tmtBerlaku = fake()->dateTimeBetween('-2 years', '+1 month');
        $tmtSelesai = fake()->optional()->dateTimeBetween($tmtBerlaku, '+1 year');

        return [
            'pegawai_id' => Pegawai::factory(),
            'ref_jenis_hukuman_disiplin_id' => RefJenisHukumanDisiplin::factory(),
            'no_sk' => fake()->unique()->bothify('SK-###/HD/####'),
            'tanggal_sk' => fake()->dateTimeBetween('-2 years', $tmtBerlaku)->format('Y-m-d'),
            'tmt_berlaku' => $tmtBerlaku->format('Y-m-d'),
            'tmt_selesai' => $tmtSelesai?->format('Y-m-d'),
            'pelanggaran' => fake()->sentence(),
            'pejabat_penetap' => fake()->optional()->name(),
            'keterangan' => fake()->optional()->sentence(),
        ];
    }
}
