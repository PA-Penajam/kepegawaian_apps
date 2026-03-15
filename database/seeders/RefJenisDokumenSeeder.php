<?php

namespace Database\Seeders;

use App\Models\RefJenisDokumen;
use Illuminate\Database\Seeder;

class RefJenisDokumenSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['nama' => 'SK CPNS', 'keterangan' => 'Surat Keputusan Calon Pegawai Negeri Sipil'],
            ['nama' => 'SK PNS', 'keterangan' => 'Surat Keputusan Pegawai Negeri Sipil'],
            ['nama' => 'SK Jabatan', 'keterangan' => 'Surat Keputusan Jabatan'],
            ['nama' => 'SK Pangkat', 'keterangan' => 'Surat Keputusan Kenaikan Pangkat'],
            ['nama' => 'Ijazah', 'keterangan' => 'Ijazah Pendidikan'],
            ['nama' => 'Sertifikat Diklat', 'keterangan' => 'Sertifikat Pelatihan'],
            ['nama' => 'KGB', 'keterangan' => 'Kenaikan Gaji Berkala'],
            ['nama' => 'Kartu Pegawai', 'keterangan' => 'Kartu Tanda Pengenal Pegawai'],
            ['nama' => 'Lainnya', 'keterangan' => 'Dokumen lainnya'],
        ];

        foreach ($data as $item) {
            RefJenisDokumen::query()->updateOrCreate(
                ['nama' => $item['nama']],
                $item
            );
        }
    }
}
