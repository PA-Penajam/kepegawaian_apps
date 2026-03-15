<?php

namespace Database\Factories;

use App\Enums\HubunganKeluarga;
use App\Enums\JenisKelamin;
use App\Models\Keluarga;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Keluarga>
 */
class KeluargaFactory extends Factory
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
            'hubungan' => fake()->randomElement(HubunganKeluarga::cases())->value,
            'nama' => fake()->name(),
            'tempat_lahir' => fake()->optional()->city(),
            'tanggal_lahir' => fake()->optional()->date('Y-m-d'),
            'jenis_kelamin' => fake()->boolean(80)
                ? fake()->randomElement(JenisKelamin::cases())->value
                : null,
            'pekerjaan' => fake()->optional()->jobTitle(),
            'pendidikan' => fake()->optional()->randomElement(['SD', 'SMP', 'SMA', 'D3', 'S1', 'S2']),
            'keterangan' => fake()->optional()->sentence(),
        ];
    }
}
