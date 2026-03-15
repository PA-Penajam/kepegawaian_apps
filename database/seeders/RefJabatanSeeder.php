<?php

namespace Database\Seeders;

use App\Enums\JenisJabatan;
use App\Models\RefJabatan;
use Illuminate\Database\Seeder;

class RefJabatanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jabatans = [
            ['kode' => 'KETUA', 'nama' => 'Ketua', 'jenis_jabatan' => JenisJabatan::Struktural->value, 'eselon' => 'II', 'kelas_jabatan' => 17],
            ['kode' => 'WAKIL_KETUA', 'nama' => 'Wakil Ketua', 'jenis_jabatan' => JenisJabatan::Struktural->value, 'eselon' => 'III', 'kelas_jabatan' => 16],
            ['kode' => 'HAKIM', 'nama' => 'Hakim', 'jenis_jabatan' => JenisJabatan::Fungsional->value, 'eselon' => null, 'kelas_jabatan' => 15],
            ['kode' => 'PANITERA', 'nama' => 'Panitera', 'jenis_jabatan' => JenisJabatan::Struktural->value, 'eselon' => 'III', 'kelas_jabatan' => 15],
            ['kode' => 'SEKRETARIS', 'nama' => 'Sekretaris', 'jenis_jabatan' => JenisJabatan::Struktural->value, 'eselon' => 'III', 'kelas_jabatan' => 15],
            ['kode' => 'PANMUD_PERMOHONAN', 'nama' => 'Panitera Muda Permohonan', 'jenis_jabatan' => JenisJabatan::Struktural->value, 'eselon' => 'IV', 'kelas_jabatan' => 13],
            ['kode' => 'PANMUD_GUGATAN', 'nama' => 'Panitera Muda Gugatan', 'jenis_jabatan' => JenisJabatan::Struktural->value, 'eselon' => 'IV', 'kelas_jabatan' => 13],
            ['kode' => 'PANMUD_HUKUM', 'nama' => 'Panitera Muda Hukum', 'jenis_jabatan' => JenisJabatan::Struktural->value, 'eselon' => 'IV', 'kelas_jabatan' => 13],
            ['kode' => 'KASUBBAG_KEPEGAWAIAN', 'nama' => 'Kasubbag Kepegawaian, Organisasi, dan Tatalaksana', 'jenis_jabatan' => JenisJabatan::Struktural->value, 'eselon' => 'IV', 'kelas_jabatan' => 11],
            ['kode' => 'KASUBBAG_PERENCANAAN', 'nama' => 'Kasubbag Perencanaan, TI, dan Pelaporan', 'jenis_jabatan' => JenisJabatan::Struktural->value, 'eselon' => 'IV', 'kelas_jabatan' => 11],
            ['kode' => 'KASUBBAG_UMUM', 'nama' => 'Kasubbag Umum dan Keuangan', 'jenis_jabatan' => JenisJabatan::Struktural->value, 'eselon' => 'IV', 'kelas_jabatan' => 11],
            ['kode' => 'PANITERA_PENGGANTI', 'nama' => 'Panitera Pengganti', 'jenis_jabatan' => JenisJabatan::Fungsional->value, 'eselon' => null, 'kelas_jabatan' => 10],
            ['kode' => 'JURUSITA', 'nama' => 'Jurusita', 'jenis_jabatan' => JenisJabatan::Fungsional->value, 'eselon' => null, 'kelas_jabatan' => 8],
            ['kode' => 'JURUSITA_PENGGANTI', 'nama' => 'Jurusita Pengganti', 'jenis_jabatan' => JenisJabatan::Fungsional->value, 'eselon' => null, 'kelas_jabatan' => 8],
            ['kode' => 'STAF_PELAKSANA', 'nama' => 'Staf/Pelaksana', 'jenis_jabatan' => JenisJabatan::Pelaksana->value, 'eselon' => null, 'kelas_jabatan' => 6],
        ];

        foreach ($jabatans as $jabatan) {
            RefJabatan::query()->updateOrCreate(
                ['kode' => $jabatan['kode']],
                $jabatan,
            );
        }
    }
}
