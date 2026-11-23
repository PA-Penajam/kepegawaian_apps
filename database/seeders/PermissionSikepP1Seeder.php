<?php

namespace Database\Seeders;

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamRolePermission;
use Illuminate\Database\Seeder;

class PermissionSikepP1Seeder extends Seeder
{
    public function run(): void
    {
        // 1. Cari aplikasi "kepegawaian" (harus sudah ada via IamSeeder)
        $app = IamApplication::where('slug', 'kepegawaian')->firstOrFail();
        $appId = $app->id;

        // 2. Daftar permission baru untuk SIKEP P1
        $permissions = [
            // ===== KENAIKAN PANGKAT - USULAN =====
            ['slug' => 'kenaikan-pangkat.usulan.view', 'nama' => 'Lihat Usulan Kenaikan Pangkat', 'group' => 'kenaikan-pangkat-usulan', 'keterangan' => 'Melihat daftar usulan dan detail usulan'],
            ['slug' => 'kenaikan-pangkat.usulan.create', 'nama' => 'Buat Usulan Kenaikan Pangkat', 'group' => 'kenaikan-pangkat-usulan', 'keterangan' => 'Membuat usulan baru'],
            ['slug' => 'kenaikan-pangkat.usulan.update', 'nama' => 'Ubah Usulan Kenaikan Pangkat', 'group' => 'kenaikan-pangkat-usulan', 'keterangan' => 'Mengubah usulan (sebelum submit)'],
            ['slug' => 'kenaikan-pangkat.usulan.delete', 'nama' => 'Hapus Usulan Kenaikan Pangkat', 'group' => 'kenaikan-pangkat-usulan', 'keterangan' => 'Menghapus usulan (sebelum submit)'],
            ['slug' => 'kenaikan-pangkat.usulan.submit', 'nama' => 'Submit Usulan Kenaikan Pangkat', 'group' => 'kenaikan-pangkat-usulan', 'keterangan' => 'Mengirimkan usulan ke alur verifikasi'],
            ['slug' => 'kenaikan-pangkat.usulan.verifikasi-kasubbag', 'nama' => 'Verifikasi Usulan (Kasubbag)', 'group' => 'kenaikan-pangkat-usulan', 'keterangan' => 'Verifikasi usulan sebagai Kasubbag Kepegawaian'],
            ['slug' => 'kenaikan-pangkat.usulan.verifikasi-sekretaris', 'nama' => 'Verifikasi Usulan (Sekretaris)', 'group' => 'kenaikan-pangkat-usulan', 'keterangan' => 'Verifikasi usulan sebagai Sekretaris Pengadilan'],
            ['slug' => 'kenaikan-pangkat.usulan.tanda-tangan-ketua', 'nama' => 'Tanda Tangan Usulan (Ketua)', 'group' => 'kenaikan-pangkat-usulan', 'keterangan' => 'Menandatangani surat pengantar KBMA'],
            ['slug' => 'kenaikan-pangkat.usulan.kirim-biro', 'nama' => 'Kirim Usulan ke Biro', 'group' => 'kenaikan-pangkat-usulan', 'keterangan' => 'Mengirim usulan yang sudah ditandatangani ke Biro Kepegawaian MA'],
            ['slug' => 'kenaikan-pangkat.usulan.upload-sk', 'nama' => 'Upload SK Kenaikan Pangkat', 'group' => 'kenaikan-pangkat-usulan', 'keterangan' => 'Mengunggah SK hasil keputusan KBMA'],
            ['slug' => 'kenaikan-pangkat.usulan.finalize', 'nama' => 'Finalisasi Usulan', 'group' => 'kenaikan-pangkat-usulan', 'keterangan' => 'Menutup usulan setelah proses selesai'],

            // ===== MONITORING (skip jika ada) =====
            ['slug' => 'kenaikan-pangkat.monitoring.view', 'nama' => 'Lihat Monitoring Kenaikan Pangkat', 'group' => 'monitoring', 'keterangan' => 'Dashboard monitoring 12 periode KP'],

            // ===== CHECKLIST TEMPLATE =====
            ['slug' => 'checklist.template.view', 'nama' => 'Lihat Template Checklist', 'group' => 'checklist-template', 'keterangan' => 'Melihat master checklist template'],
            ['slug' => 'checklist.template.create', 'nama' => 'Buat Template Checklist', 'group' => 'checklist-template', 'keterangan' => 'Membuat template checklist baru'],
            ['slug' => 'checklist.template.update', 'nama' => 'Ubah Template Checklist', 'group' => 'checklist-template', 'keterangan' => 'Mengubah template checklist'],
            ['slug' => 'checklist.template.delete', 'nama' => 'Hapus Template Checklist', 'group' => 'checklist-template', 'keterangan' => 'Menghapus template checklist'],

            // ===== CHECKLIST SUBMISSION =====
            ['slug' => 'checklist.submission.view', 'nama' => 'Lihat Checklist Submission', 'group' => 'checklist-submission', 'keterangan' => 'Melihat checklist per pegawai/usulan'],
            ['slug' => 'checklist.submission.update-item', 'nama' => 'Update Item Checklist', 'group' => 'checklist-submission', 'keterangan' => 'Mencentang/membatalkan centang item checklist'],
            ['slug' => 'checklist.submission.validate-item', 'nama' => 'Validasi Item Checklist', 'group' => 'checklist-submission', 'keterangan' => 'Memvalidasi item checklist (oleh sekretaris)'],
        ];

        // 3. Upsert permission (idempotent) dan simpan peta slug => id untuk aplikasi ini
        $permissionIdsBySlug = [];
        foreach ($permissions as $p) {
            $perm = IamPermission::updateOrCreate(
                ['iam_application_id' => $appId, 'slug' => $p['slug']],
                $p
            );
            $permissionIdsBySlug[$p['slug']] = $perm->id;
        }

        // 4. Mapping role -> permission (selaras dengan slug kanonik IamSeeder + legacy opsional)
        $roleSlugs = [
            'admin',
            'operator',
            'validator',
            'viewer',
            'kasubbag_kepegawaian',
            'sekretaris',
            'ketua_pengadilan',
            'admin_kepegawaian',
            'pegawai',
        ];

        $roles = IamRole::where('iam_application_id', $appId)
            ->whereIn('slug', $roleSlugs)
            ->get()
            ->keyBy('slug');

        // ===== ADMIN (semua 19 permission P1) =====
        if ($roles->has('admin')) {
            $r = $roles['admin']->id;
            foreach ($permissions as $p) {
                self::assignPermission($r, $p['slug'], $appId, $permissionIdsBySlug);
            }
        }

        // ===== OPERATOR (kelola usulan & checklist template/submission, TANPA monitoring KP) =====
        if ($roles->has('operator')) {
            $r = $roles['operator']->id;
            $slugs = [
                'kenaikan-pangkat.usulan.view',
                'kenaikan-pangkat.usulan.create',
                'kenaikan-pangkat.usulan.update',
                'kenaikan-pangkat.usulan.delete',
                'kenaikan-pangkat.usulan.submit',
                'kenaikan-pangkat.usulan.upload-sk',
                'kenaikan-pangkat.usulan.finalize',
                'checklist.template.view',
                'checklist.template.create',
                'checklist.template.update',
                'checklist.template.delete',
                'checklist.submission.view',
                'checklist.submission.update-item',
            ];
            foreach ($slugs as $s) {
                self::assignPermission($r, $s, $appId, $permissionIdsBySlug);
            }
        }

        // ===== VALIDATOR =====
        if ($roles->has('validator')) {
            $r = $roles['validator']->id;
            $slugs = [
                'kenaikan-pangkat.usulan.view',
                'kenaikan-pangkat.usulan.verifikasi-kasubbag',
                'kenaikan-pangkat.usulan.verifikasi-sekretaris',
                'checklist.submission.view',
                'checklist.submission.validate-item',
            ];
            foreach ($slugs as $s) {
                self::assignPermission($r, $s, $appId, $permissionIdsBySlug);
            }
        }

        // ===== VIEWER =====
        if ($roles->has('viewer')) {
            $r = $roles['viewer']->id;
            self::assignPermission($r, 'kenaikan-pangkat.usulan.view', $appId, $permissionIdsBySlug);
        }

        // ===== KASUBBAG KEPEGAWAIAN (legacy opsional) =====
        if ($roles->has('kasubbag_kepegawaian')) {
            $r = $roles['kasubbag_kepegawaian']->id;
            $slugs = [
                'kenaikan-pangkat.usulan.view', 'kenaikan-pangkat.usulan.create', 'kenaikan-pangkat.usulan.update', 'kenaikan-pangkat.usulan.submit', 'kenaikan-pangkat.usulan.verifikasi-kasubbag',
                'checklist.template.view', 'checklist.template.create', 'checklist.template.update', 'checklist.template.delete',
                'checklist.submission.view', 'checklist.submission.update-item', 'checklist.submission.validate-item',
            ];
            foreach ($slugs as $s) {
                self::assignPermission($r, $s, $appId, $permissionIdsBySlug);
            }
        }

        // ===== SEKRETARIS (legacy opsional) =====
        if ($roles->has('sekretaris')) {
            $r = $roles['sekretaris']->id;
            $slugs = ['kenaikan-pangkat.usulan.view', 'kenaikan-pangkat.usulan.verifikasi-sekretaris', 'checklist.submission.view', 'checklist.submission.validate-item'];
            foreach ($slugs as $s) {
                self::assignPermission($r, $s, $appId, $permissionIdsBySlug);
            }
        }

        // ===== KETUA PENGADILAN (legacy opsional) =====
        if ($roles->has('ketua_pengadilan')) {
            $r = $roles['ketua_pengadilan']->id;
            $slugs = ['kenaikan-pangkat.usulan.view', 'kenaikan-pangkat.usulan.tanda-tangan-ketua', 'kenaikan-pangkat.usulan.kirim-biro'];
            foreach ($slugs as $s) {
                self::assignPermission($r, $s, $appId, $permissionIdsBySlug);
            }
        }

        // ===== ADMIN KEPEGAWAIAN (legacy opsional) =====
        if ($roles->has('admin_kepegawaian')) {
            $r = $roles['admin_kepegawaian']->id;
            foreach ($permissions as $p) {
                self::assignPermission($r, $p['slug'], $appId, $permissionIdsBySlug);
            }
        }

        // ===== PEGAWAI (legacy opsional) =====
        if ($roles->has('pegawai')) {
            $r = $roles['pegawai']->id;
            self::assignPermission($r, 'kenaikan-pangkat.usulan.view', $appId, $permissionIdsBySlug);
        }
    }

    /**
     * @param  array<string, string>  $permissionIdsBySlug
     */
    private static function assignPermission(string $roleId, string $slug, string $appId, array $permissionIdsBySlug = []): void
    {
        $permId = $permissionIdsBySlug[$slug] ?? IamPermission::query()
            ->where('iam_application_id', $appId)
            ->where('slug', $slug)
            ->value('id');

        if ($permId !== null) {
            IamRolePermission::updateOrCreate(
                ['iam_role_id' => $roleId, 'iam_permission_id' => $permId]
            );
        }
    }
}
