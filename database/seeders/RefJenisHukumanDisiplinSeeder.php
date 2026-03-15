<?php

namespace Database\Seeders;

use App\Models\RefJenisHukumanDisiplin;
use Illuminate\Database\Seeder;

class RefJenisHukumanDisiplinSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['nama' => 'Teguran Lisan', 'tingkat' => 'ringan', 'keterangan' => 'Hukuman disiplin ringan berupa teguran lisan.'],
            ['nama' => 'Teguran Tertulis', 'tingkat' => 'ringan', 'keterangan' => 'Hukuman disiplin ringan berupa teguran tertulis.'],
            ['nama' => 'Pernyataan Tidak Puas Secara Tertulis', 'tingkat' => 'ringan', 'keterangan' => 'Pernyataan ketidakpuasan secara tertulis dari pejabat berwenang.'],
            ['nama' => 'Penundaan KGB', 'tingkat' => 'sedang', 'keterangan' => 'Penundaan kenaikan gaji berkala sesuai ketentuan disiplin.'],
            ['nama' => 'Penurunan Pangkat', 'tingkat' => 'berat', 'keterangan' => 'Penurunan pangkat setingkat lebih rendah dalam jangka waktu tertentu.'],
            ['nama' => 'Pemberhentian Dengan Tidak Hormat', 'tingkat' => 'berat', 'keterangan' => 'Pemberhentian tidak hormat sebagai hukuman disiplin berat.'],
        ] as $jenisHukuman) {
            RefJenisHukumanDisiplin::query()->updateOrCreate(
                ['nama' => $jenisHukuman['nama']],
                $jenisHukuman,
            );
        }
    }
}
