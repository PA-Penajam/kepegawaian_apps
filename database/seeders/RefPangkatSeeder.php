<?php

namespace Database\Seeders;

use App\Models\RefPangkat;
use Illuminate\Database\Seeder;

class RefPangkatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pangkats = [
            ['kode' => 'I/a', 'nama' => 'Juru Muda', 'golongan' => 'I', 'ruang' => 'a', 'tingkat' => 'Juru', 'urutan' => 1],
            ['kode' => 'I/b', 'nama' => 'Juru Muda Tk.I', 'golongan' => 'I', 'ruang' => 'b', 'tingkat' => 'Juru', 'urutan' => 2],
            ['kode' => 'I/c', 'nama' => 'Juru', 'golongan' => 'I', 'ruang' => 'c', 'tingkat' => 'Juru', 'urutan' => 3],
            ['kode' => 'I/d', 'nama' => 'Juru Tk.I', 'golongan' => 'I', 'ruang' => 'd', 'tingkat' => 'Juru', 'urutan' => 4],
            ['kode' => 'II/a', 'nama' => 'Pengatur Muda', 'golongan' => 'II', 'ruang' => 'a', 'tingkat' => 'Pengatur', 'urutan' => 5],
            ['kode' => 'II/b', 'nama' => 'Pengatur Muda Tk.I', 'golongan' => 'II', 'ruang' => 'b', 'tingkat' => 'Pengatur', 'urutan' => 6],
            ['kode' => 'II/c', 'nama' => 'Pengatur', 'golongan' => 'II', 'ruang' => 'c', 'tingkat' => 'Pengatur', 'urutan' => 7],
            ['kode' => 'II/d', 'nama' => 'Pengatur Tk.I', 'golongan' => 'II', 'ruang' => 'd', 'tingkat' => 'Pengatur', 'urutan' => 8],
            ['kode' => 'III/a', 'nama' => 'Penata Muda', 'golongan' => 'III', 'ruang' => 'a', 'tingkat' => 'Penata', 'urutan' => 9],
            ['kode' => 'III/b', 'nama' => 'Penata Muda Tk.I', 'golongan' => 'III', 'ruang' => 'b', 'tingkat' => 'Penata', 'urutan' => 10],
            ['kode' => 'III/c', 'nama' => 'Penata', 'golongan' => 'III', 'ruang' => 'c', 'tingkat' => 'Penata', 'urutan' => 11],
            ['kode' => 'III/d', 'nama' => 'Penata Tk.I', 'golongan' => 'III', 'ruang' => 'd', 'tingkat' => 'Penata', 'urutan' => 12],
            ['kode' => 'IV/a', 'nama' => 'Pembina', 'golongan' => 'IV', 'ruang' => 'a', 'tingkat' => 'Pembina', 'urutan' => 13],
            ['kode' => 'IV/b', 'nama' => 'Pembina Tk.I', 'golongan' => 'IV', 'ruang' => 'b', 'tingkat' => 'Pembina', 'urutan' => 14],
            ['kode' => 'IV/c', 'nama' => 'Pembina Utama Muda', 'golongan' => 'IV', 'ruang' => 'c', 'tingkat' => 'Pembina', 'urutan' => 15],
            ['kode' => 'IV/d', 'nama' => 'Pembina Utama Madya', 'golongan' => 'IV', 'ruang' => 'd', 'tingkat' => 'Pembina', 'urutan' => 16],
            ['kode' => 'IV/e', 'nama' => 'Pembina Utama', 'golongan' => 'IV', 'ruang' => 'e', 'tingkat' => 'Pembina', 'urutan' => 17],

            // PPPK (Pegawai Pemerintah dengan Perjanjian Kerja)
            ['kode' => 'IX', 'nama' => 'PPPK Jenjang 9', 'golongan' => 'IX', 'ruang' => '-', 'tingkat' => 'PPPK', 'urutan' => 18],
            ['kode' => 'V', 'nama' => 'PPPK Jenjang 5', 'golongan' => 'V', 'ruang' => '-', 'tingkat' => 'PPPK', 'urutan' => 19],
            ['kode' => 'I', 'nama' => 'PPPK Jenjang 1', 'golongan' => 'I', 'ruang' => '-', 'tingkat' => 'PPPK', 'urutan' => 20],
        ];

        foreach ($pangkats as $pangkat) {
            RefPangkat::query()->updateOrCreate(
                ['kode' => $pangkat['kode']],
                $pangkat,
            );
        }
    }
}
