<?php

namespace Database\Seeders;

use App\Models\BerkasChecklist\BerkasChecklistItem;
use App\Models\BerkasChecklist\BerkasChecklistTemplate;
use Illuminate\Database\Seeder;

class ChecklistKenaikanPangkatSeeder extends Seeder
{
    public function run(): void
    {
        $template = BerkasChecklistTemplate::query()->updateOrCreate(
            [
                'jenis' => 'kenaikan_pangkat',
                'kode' => 'checklist-kp-reguler',
            ],
            [
                'nama' => 'Checklist KP Reguler',
                'deskripsi' => 'Template kelengkapan berkas kenaikan pangkat reguler.',
                'aktif' => true,
                'urutan' => 1,
            ],
        );

        collect([
            ['kode' => 'sk_cpns', 'nama' => 'SK CPNS', 'wajib' => true, 'urutan' => 1],
            ['kode' => 'sk_pns', 'nama' => 'SK PNS', 'wajib' => true, 'urutan' => 2],
            ['kode' => 'sk_pangkat_terakhir', 'nama' => 'SK Pangkat Terakhir', 'wajib' => true, 'urutan' => 3],
            ['kode' => 'sk_jabatan_terakhir', 'nama' => 'SK Jabatan Terakhir', 'wajib' => true, 'urutan' => 4],
            ['kode' => 'skp_2_tahun', 'nama' => 'SKP 2 Tahun Terakhir', 'wajib' => true, 'urutan' => 5],
            ['kode' => 'sertifikat_diklat', 'nama' => 'Sertifikat Diklat', 'wajib' => false, 'urutan' => 6],
            ['kode' => 'dokumen_pendukung_lain', 'nama' => 'Dokumen Pendukung Lain', 'wajib' => false, 'urutan' => 7],
        ])->each(function (array $item) use ($template): void {
            BerkasChecklistItem::query()->updateOrCreate(
                [
                    'berkas_checklist_template_id' => $template->id,
                    'kode' => $item['kode'],
                ],
                [
                    'nama' => $item['nama'],
                    'deskripsi' => null,
                    'wajib' => $item['wajib'],
                    'urutan' => $item['urutan'],
                ],
            );
        });
    }
}
