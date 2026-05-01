<?php

namespace Database\Seeders;

use App\Models\Cuti\CutiJenisMaster;
use Illuminate\Database\Seeder;

class CutiJenisMasterSeeder extends Seeder
{
    public function run(): void
    {
        $jenisCuti = [
            [
                'kode' => 'CT',
                'nama' => 'Cuti Tahunan',
                'saldo_driven' => true,
                'hak_default_per_tahun' => 12,
                'durasi_min_kalender' => 1,
                'durasi_max_kalender' => 365,
                'butuh_lampiran' => false,
                'boleh_dicabut_setelah_disetujui' => true,
                'aktif' => true,
            ],
            [
                'kode' => 'CS_TIER1',
                'nama' => 'Cuti Sakit (1-14 hari)',
                'saldo_driven' => false,
                'hak_default_per_tahun' => null,
                'durasi_min_kalender' => 1,
                'durasi_max_kalender' => 14,
                'butuh_lampiran' => false,
                'boleh_dicabut_setelah_disetujui' => false,
                'aktif' => true,
            ],
            [
                'kode' => 'CS_TIER2',
                'nama' => 'Cuti Sakit (lebih dari 14 hari)',
                'saldo_driven' => false,
                'hak_default_per_tahun' => null,
                'durasi_min_kalender' => 15,
                'durasi_max_kalender' => 548,
                'butuh_lampiran' => true,
                'boleh_dicabut_setelah_disetujui' => false,
                'aktif' => true,
            ],
            [
                'kode' => 'CAP',
                'nama' => 'Cuti Alasan Penting',
                'saldo_driven' => false,
                'hak_default_per_tahun' => null,
                'durasi_min_kalender' => 1,
                'durasi_max_kalender' => 60,
                'butuh_lampiran' => true,
                'boleh_dicabut_setelah_disetujui' => true,
                'aktif' => true,
            ],
        ];

        foreach ($jenisCuti as $data) {
            CutiJenisMaster::firstOrCreate(
                ['kode' => $data['kode']],
                $data,
            );
        }
    }
}
