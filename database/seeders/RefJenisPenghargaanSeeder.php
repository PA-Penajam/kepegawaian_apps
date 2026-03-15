<?php

namespace Database\Seeders;

use App\Models\RefJenisPenghargaan;
use Illuminate\Database\Seeder;

class RefJenisPenghargaanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['nama' => 'Satya Lencana Karya Satya 10 Tahun', 'keterangan' => 'Penghargaan masa bakti 10 tahun bagi ASN.'],
            ['nama' => 'Satya Lencana Karya Satya 20 Tahun', 'keterangan' => 'Penghargaan masa bakti 20 tahun bagi ASN.'],
            ['nama' => 'Satya Lencana Karya Satya 30 Tahun', 'keterangan' => 'Penghargaan masa bakti 30 tahun bagi ASN.'],
            ['nama' => 'Penghargaan Lainnya', 'keterangan' => 'Kategori umum untuk penghargaan selain satya lencana.'],
        ] as $jenisPenghargaan) {
            RefJenisPenghargaan::query()->updateOrCreate(
                ['nama' => $jenisPenghargaan['nama']],
                $jenisPenghargaan,
            );
        }
    }
}
