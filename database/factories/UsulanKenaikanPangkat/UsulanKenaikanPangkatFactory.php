<?php

namespace Database\Factories\UsulanKenaikanPangkat;

use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsulanKenaikanPangkat>
 */
class UsulanKenaikanPangkatFactory extends Factory
{
    protected $model = UsulanKenaikanPangkat::class;

    public function definition(): array
    {
        $fakerId = fake('id_ID');
        $bulan = $fakerId->numberBetween(1, 12);
        $tahun = $fakerId->numberBetween((int) now()->format('Y'), (int) now()->addYear()->format('Y'));

        return [
            'pegawai_id' => Pegawai::factory(),
            'ref_pangkat_asal_id' => RefPangkat::factory(),
            'ref_pangkat_tujuan_id' => RefPangkat::factory(),
            'tmt_pangkat_asal' => $fakerId->dateTimeBetween('-5 years', '-4 years')->format('Y-m-d'),
            'periode_usul_bulan' => $bulan,
            'periode_usul_tahun' => $tahun,
            'nomor_usulan' => null,
            'tanggal_usulan' => null,
            'state' => 'DRAFT',
            'catatan_pengusul' => $fakerId->optional()->sentence(),
        ];
    }

    public function diajukan(): static
    {
        return $this->state(fn () => [
            'state' => 'DIAJUKAN',
            'nomor_usulan' => 'KP-'.now()->format('Ym').'-'.fake('id_ID')->unique()->numerify('####'),
            'tanggal_usulan' => now()->toDateString(),
            'submitted_at' => now(),
        ]);
    }

    public function skTerbit(): static
    {
        return $this->state(fn () => [
            'state' => 'SELESAI_SK_TERBIT',
            'nomor_usulan' => 'KP-'.now()->format('Ym').'-'.fake('id_ID')->unique()->numerify('####'),
            'tanggal_usulan' => now()->subMonth()->toDateString(),
            'nomor_sk' => 'SK-KP-'.now()->format('Ym').'-'.fake('id_ID')->unique()->numerify('####'),
            'tanggal_sk' => now()->toDateString(),
            'sk_file_path' => 'kenaikan-pangkat/sk/'.fake()->uuid().'.pdf',
            'sk_file_original_name' => 'sk-kenaikan-pangkat.pdf',
            'submitted_at' => now()->subMonth(),
            'finalized_at' => now(),
        ]);
    }
}
