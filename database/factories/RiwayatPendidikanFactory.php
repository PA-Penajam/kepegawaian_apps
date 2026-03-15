<?php

namespace Database\Factories;

use App\Enums\JenjangPendidikan;
use App\Models\Pegawai;
use App\Models\RiwayatPendidikan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiwayatPendidikan>
 */
class RiwayatPendidikanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fakerId = fake('id_ID');
        $tahunLulus = $fakerId->numberBetween(1980, (int) date('Y'));

        return [
            'pegawai_id' => Pegawai::factory(),
            'jenjang' => $fakerId->randomElement(JenjangPendidikan::cases())->value,
            'nama_sekolah' => $fakerId->randomElement([
                'SMA Negeri 1 Balikpapan',
                'Universitas Mulawarman',
                'Universitas Indonesia',
                'Institut Teknologi Bandung',
                'Politeknik Negeri Samarinda',
            ]),
            'jurusan' => $fakerId->optional()->randomElement([
                'IPA',
                'IPS',
                'Ilmu Hukum',
                'Manajemen',
                'Teknik Informatika',
                'Administrasi Publik',
            ]),
            'tahun_lulus' => $tahunLulus,
            'no_ijazah' => $fakerId->optional()->bothify('IJZ-####-####'),
            'tanggal_ijazah' => $fakerId->boolean(80)
                ? sprintf(
                    '%d-%02d-%02d',
                    $tahunLulus,
                    $fakerId->numberBetween(1, 12),
                    $fakerId->numberBetween(1, 28),
                )
                : null,
            'keterangan' => $fakerId->optional()->sentence(),
        ];
    }
}
