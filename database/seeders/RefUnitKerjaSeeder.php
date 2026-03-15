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
        $units = [];

        // Level 1: Satker (Pengadilan)
        $units['SATKER_PA_PENAJAM'] = RefUnitKerja::query()->updateOrCreate(
            ['kode' => 'SATKER_PA_PENAJAM'],
            ['kode' => 'SATKER_PA_PENAJAM', 'nama' => 'Pengadilan Agama Penajam', 'parent_id' => null, 'urutan' => 1],
        );

        // Level 1: Jabatan Struktural sebagai Unit Kerja
        foreach ([
            ['kode' => 'PANITERA', 'nama' => 'Panitera', 'urutan' => 2],
            ['kode' => 'SEKRETARIS', 'nama' => 'Sekretaris', 'urutan' => 3],
        ] as $unitKerja) {
            $units[$unitKerja['kode']] = RefUnitKerja::query()->updateOrCreate(
                ['kode' => $unitKerja['kode']],
                array_merge($unitKerja, ['parent_id' => null]),
            );
        }

        // Level 2: Di bawah Panitera
        foreach ([
            ['kode' => 'PANMUD_PERMOHONAN', 'nama' => 'Panitera Muda Permohonan', 'parent_kode' => 'PANITERA', 'urutan' => 1],
            ['kode' => 'PANMUD_GUGATAN', 'nama' => 'Panitera Muda Gugatan', 'parent_kode' => 'PANITERA', 'urutan' => 2],
            ['kode' => 'PANMUD_HUKUM', 'nama' => 'Panitera Muda Hukum', 'parent_kode' => 'PANITERA', 'urutan' => 3],
        ] as $unitKerja) {
            $parent = $units[$unitKerja['parent_kode']];

            $units[$unitKerja['kode']] = RefUnitKerja::query()->updateOrCreate(
                ['kode' => $unitKerja['kode']],
                [
                    'kode' => $unitKerja['kode'],
                    'nama' => $unitKerja['nama'],
                    'parent_id' => $parent->id,
                    'urutan' => $unitKerja['urutan'],
                ],
            );
        }

        // Level 2: Di bawah Sekretaris
        foreach ([
            ['kode' => 'SUBBAG_KEPEGAWAIAN', 'nama' => 'Subbag Kepegawaian, Organisasi, dan Tata Laksana', 'parent_kode' => 'SEKRETARIS', 'urutan' => 1],
            ['kode' => 'SUBBAG_PERENCANAAN', 'nama' => 'Subbag Perencanaan, TI, dan Pelaporan', 'parent_kode' => 'SEKRETARIS', 'urutan' => 2],
            ['kode' => 'SUBBAG_UMUM', 'nama' => 'Subbag Umum dan Keuangan', 'parent_kode' => 'SEKRETARIS', 'urutan' => 3],
        ] as $unitKerja) {
            $parent = $units[$unitKerja['parent_kode']];

            RefUnitKerja::query()->updateOrCreate(
                ['kode' => $unitKerja['kode']],
                [
                    'kode' => $unitKerja['kode'],
                    'nama' => $unitKerja['nama'],
                    'parent_id' => $parent->id,
                    'urutan' => $unitKerja['urutan'],
                ],
            );
        }
    }
}
