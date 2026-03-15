<?php

namespace Database\Seeders;

use App\Models\RefStatusPegawai;
use Illuminate\Database\Seeder;

class RefStatusPegawaiSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['kode' => 'aktif', 'nama' => 'Aktif', 'keterangan' => 'Pegawai masih aktif bekerja'],
            ['kode' => 'mutasi_keluar', 'nama' => 'Mutasi Keluar', 'keterangan' => 'Pegawai mutasi ke instansi lain'],
            ['kode' => 'pensiun', 'nama' => 'Pensiun', 'keterangan' => 'Pegawai sudah pensiun'],
            ['kode' => 'meninggal', 'nama' => 'Meninggal', 'keterangan' => 'Pegawai telah meninggal dunia'],
            ['kode' => 'diberhentikan', 'nama' => 'Diberhentikan', 'keterangan' => 'Pegawai diberhentikan'],
        ];

        foreach ($data as $item) {
            RefStatusPegawai::query()->updateOrCreate(
                ['kode' => $item['kode']],
                $item
            );
        }
    }
}
