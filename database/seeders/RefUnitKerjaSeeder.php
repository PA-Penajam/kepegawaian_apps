<?php

namespace Database\Seeders;

use App\Models\RefUnitKerja;
use Illuminate\Database\Seeder;

class RefUnitKerjaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $parents = [];

        foreach ([
            ['kode' => 'KEPANITERAAN', 'nama' => 'Kepaniteraan', 'parent_id' => null, 'urutan' => 1],
            ['kode' => 'KESEKRETARIATAN', 'nama' => 'Kesekretariatan', 'parent_id' => null, 'urutan' => 2],
        ] as $unitKerja) {
            $parents[$unitKerja['kode']] = RefUnitKerja::query()->updateOrCreate(
                ['kode' => $unitKerja['kode']],
                $unitKerja,
            );
        }

        foreach ([
            ['kode' => 'KEPANITERAAN_PERMOHONAN', 'nama' => 'Kepaniteraan Permohonan', 'parent_kode' => 'KEPANITERAAN', 'urutan' => 1],
            ['kode' => 'KEPANITERAAN_GUGATAN', 'nama' => 'Kepaniteraan Gugatan', 'parent_kode' => 'KEPANITERAAN', 'urutan' => 2],
            ['kode' => 'KEPANITERAAN_HUKUM', 'nama' => 'Kepaniteraan Hukum', 'parent_kode' => 'KEPANITERAAN', 'urutan' => 3],
            ['kode' => 'SUBBAG_KEPEGAWAIAN', 'nama' => 'Subbag Kepegawaian Org Tatalaksana', 'parent_kode' => 'KESEKRETARIATAN', 'urutan' => 1],
            ['kode' => 'SUBBAG_PERENCANAAN', 'nama' => 'Subbag Perencanaan TI Pelaporan', 'parent_kode' => 'KESEKRETARIATAN', 'urutan' => 2],
            ['kode' => 'SUBBAG_UMUM', 'nama' => 'Subbag Umum Keuangan', 'parent_kode' => 'KESEKRETARIATAN', 'urutan' => 3],
        ] as $unitKerja) {
            $parent = $parents[$unitKerja['parent_kode']];

            RefUnitKerja::query()->updateOrCreate(
                ['kode' => $unitKerja['kode']],
                [
                    'nama' => $unitKerja['nama'],
                    'parent_id' => $parent->id,
                    'urutan' => $unitKerja['urutan'],
                ],
            );
        }
    }
}
