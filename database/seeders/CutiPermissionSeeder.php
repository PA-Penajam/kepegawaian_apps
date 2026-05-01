<?php

namespace Database\Seeders;

use App\Models\IamApplication;
use App\Models\IamPermission;
use Illuminate\Database\Seeder;

class CutiPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $kepegawaian = IamApplication::where('slug', 'kepegawaian')->first();

        if (! $kepegawaian) {
            $this->command?->warn('IAM application kepegawaian tidak ditemukan. Lewati seeding permission cuti.');

            return;
        }

        $permissions = [
            ['slug' => 'cuti.pengajuan.create', 'nama' => 'Buat Pengajuan Cuti', 'group' => 'cuti', 'keterangan' => 'Membuat pengajuan cuti baru'],
            ['slug' => 'cuti.pengajuan.view-own', 'nama' => 'Lihat Pengajuan Cuti Sendiri', 'group' => 'cuti', 'keterangan' => 'Melihat pengajuan cuti milik sendiri'],
            ['slug' => 'cuti.pengajuan.view-team', 'nama' => 'Lihat Pengajuan Cuti Tim', 'group' => 'cuti', 'keterangan' => 'Melihat pengajuan cuti anggota tim'],
            ['slug' => 'cuti.pengajuan.view-all', 'nama' => 'Lihat Semua Pengajuan Cuti', 'group' => 'cuti', 'keterangan' => 'Melihat semua pengajuan cuti'],
            ['slug' => 'cuti.pengajuan.verify', 'nama' => 'Verifikasi Pengajuan Cuti', 'group' => 'cuti', 'keterangan' => 'Memverifikasi pengajuan cuti sebagai petugas kepegawaian'],
            ['slug' => 'cuti.pengajuan.approve-langsung', 'nama' => 'Approve Cuti Atasan Langsung', 'group' => 'cuti', 'keterangan' => 'Menyetujui pengajuan cuti sebagai atasan langsung'],
            ['slug' => 'cuti.pengajuan.approve-pejabat', 'nama' => 'Approve Cuti Pejabat Berwenang', 'group' => 'cuti', 'keterangan' => 'Menyetujui pengajuan cuti sebagai pejabat berwenang'],
            ['slug' => 'cuti.pengajuan.cancel-own', 'nama' => 'Batalkan Pengajuan Cuti Sendiri', 'group' => 'cuti', 'keterangan' => 'Membatalkan pengajuan cuti milik sendiri'],
            ['slug' => 'cuti.pengajuan.cancel-any', 'nama' => 'Batalkan Pengajuan Cuti Siapapun', 'group' => 'cuti', 'keterangan' => 'Membatalkan pengajuan cuti pegawai manapun'],
            ['slug' => 'cuti.pengajuan.reassign', 'nama' => 'Reassign Approver Cuti', 'group' => 'cuti', 'keterangan' => 'Mengubah approver pada pengajuan cuti'],
            ['slug' => 'cuti.saldo.view-own', 'nama' => 'Lihat Saldo Cuti Sendiri', 'group' => 'cuti', 'keterangan' => 'Melihat saldo cuti milik sendiri'],
            ['slug' => 'cuti.saldo.view-all', 'nama' => 'Lihat Semua Saldo Cuti', 'group' => 'cuti', 'keterangan' => 'Melihat saldo cuti semua pegawai'],
            ['slug' => 'cuti.saldo.adjust', 'nama' => 'Penyesuaian Saldo Cuti', 'group' => 'cuti', 'keterangan' => 'Melakukan penyesuaian saldo cuti manual'],
            ['slug' => 'cuti.audit.view', 'nama' => 'Lihat Audit Log Cuti', 'group' => 'cuti', 'keterangan' => 'Melihat log audit modul cuti'],
        ];

        foreach ($permissions as $perm) {
            IamPermission::firstOrCreate(
                [
                    'iam_application_id' => $kepegawaian->id,
                    'slug' => $perm['slug'],
                ],
                [
                    'nama' => $perm['nama'],
                    'group' => $perm['group'],
                    'keterangan' => $perm['keterangan'],
                ],
            );
        }
    }
}
