<?php

namespace Database\Factories;

use App\Models\Pegawai;
use App\Models\RefJenisDiklat;
use App\Models\RiwayatDiklat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiwayatDiklat>
 */
class RiwayatDiklatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fakerId = fake('id_ID');
        $tanggalMulai = $fakerId->dateTimeBetween('-5 years', '-1 month');
        $tanggalSelesai = (clone $tanggalMulai)->modify(sprintf('+%d days', $fakerId->numberBetween(1, 21)));
        $tanggalSertifikat = $fakerId->optional()->dateTimeBetween($tanggalSelesai, 'now');

        return [
            'pegawai_id' => Pegawai::factory(),
            'ref_jenis_diklat_id' => $fakerId->boolean(70) ? RefJenisDiklat::factory() : null,
            'nama_diklat' => $fakerId->words(3, true),
            'penyelenggara' => $fakerId->company(),
            'tempat' => $fakerId->optional()->city(),
            'tanggal_mulai' => $tanggalMulai->format('Y-m-d'),
            'tanggal_selesai' => $tanggalSelesai->format('Y-m-d'),
            'jam_pelajaran' => $fakerId->optional()->numberBetween(8, 120),
            'no_sertifikat' => $fakerId->optional()->bothify('SERT-####-??'),
            'tanggal_sertifikat' => $tanggalSertifikat?->format('Y-m-d'),
            'keterangan' => $fakerId->optional()->sentence(),
        ];
    }
}
