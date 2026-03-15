<?php

namespace Database\Factories;

use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Factories\Factory;

class DokumenPegawaiFactory extends Factory
{
    public function definition(): array
    {
        $fakerId = fake('id_ID');
        $tanggalDokumen = $fakerId->boolean(80) ? $fakerId->date() : null;

        return [
            'pegawai_id' => Pegawai::factory(),
            'jenis_dokumen' => $fakerId->randomElement([
                'KTP',
                'NPWP',
                'IJAZAH',
                'SK_CPNS',
                'SK_PNS',
            ]),
            'nomor_dokumen' => $fakerId->optional()->bothify('DOC-####-####'),
            'tanggal_dokumen' => $tanggalDokumen,
            'file_path' => $fakerId->boolean(70)
                ? 'dokumen/pegawai/'.$fakerId->bothify('file-####').'.pdf'
                : null,
            'keterangan' => $fakerId->optional()->sentence(),
        ];
    }
}
