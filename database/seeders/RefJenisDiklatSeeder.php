<?php

namespace Database\Seeders;

use App\Models\RefJenisDiklat;
use Illuminate\Database\Seeder;

class RefJenisDiklatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['nama' => 'Prajabatan', 'keterangan' => 'Diklat dasar untuk pegawai sebelum menjalankan tugas.'],
            ['nama' => 'Kepemimpinan Tk.IV', 'keterangan' => 'Diklat kepemimpinan untuk pejabat pengawas.'],
            ['nama' => 'Kepemimpinan Tk.III', 'keterangan' => 'Diklat kepemimpinan untuk pejabat administrator.'],
            ['nama' => 'Teknis Peradilan', 'keterangan' => 'Diklat teknis yang mendukung tugas pokok peradilan agama.'],
            ['nama' => 'Fungsional', 'keterangan' => 'Diklat penguatan jabatan fungsional dan kompetensi teknis.'],
        ] as $jenisDiklat) {
            RefJenisDiklat::query()->updateOrCreate(
                ['nama' => $jenisDiklat['nama']],
                $jenisDiklat,
            );
        }
    }
}
