<?php

namespace Database\Seeders;

use App\Models\RefStatusKepegawaian;
use Illuminate\Database\Seeder;

class RefStatusKepegawaianSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['kode' => 'pns', 'nama' => 'PNS', 'keterangan' => 'Pegawai Negeri Sipil'],
            ['kode' => 'pppk', 'nama' => 'PPPK', 'keterangan' => 'Pegawai Pemerintah dengan Perjanjian Kerja'],
            ['kode' => 'ptth', 'nama' => 'PTTH', 'keterangan' => 'Pegawai Tidak Tetap Honorer'],
            ['kode' => 'ptt', 'nama' => 'PTT', 'keterangan' => 'Pegawai Tidak Tetap'],
        ];

        foreach ($data as $item) {
            RefStatusKepegawaian::query()->updateOrCreate(
                ['kode' => $item['kode']],
                $item
            );
        }
    }
}
