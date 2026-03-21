<?php

namespace Database\Seeders;

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamRolePermission;
use App\Models\IamUserRole;
use App\Models\Pegawai;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IamSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Daftarkan kepegawaian-apps sebagai aplikasi pertama
        // generateApiCredentials menggunakan Crypt::encryptString (bukan Hash::make)
        ['key' => $key, 'hash' => $hash] = IamApplication::generateApiCredentials();

        // Buat instance dan set field sensitif secara manual (bukan mass assignment)
        $kepegawaian = new IamApplication([
            'nama' => 'Kepegawaian Apps',
            'slug' => 'kepegawaian',
            'url' => config('app.url'),
            'deskripsi' => 'Sistem master data kepegawaian PA Penajam',
            'is_active' => true,
        ]);
        $kepegawaian->api_key = $key;           // Tidak fillable — set manual
        $kepegawaian->api_secret_hash = $hash;  // Tidak fillable — set manual
        $kepegawaian->is_system = true;         // Tidak fillable — set manual
        $kepegawaian->save();

        // 2. Pastikan role default tersedia terlebih dahulu (sebelum migrasi user)
        $adminRole = IamRole::firstOrCreate(
            ['iam_application_id' => $kepegawaian->id, 'slug' => 'admin'],
            ['nama' => 'Admin', 'is_system' => true]
        );
        $operatorRole = IamRole::firstOrCreate(
            ['iam_application_id' => $kepegawaian->id, 'slug' => 'operator'],
            ['nama' => 'Operator', 'is_system' => true]
        );
        $viewerRole = IamRole::firstOrCreate(
            ['iam_application_id' => $kepegawaian->id, 'slug' => 'viewer'],
            ['nama' => 'Viewer', 'is_system' => true]
        );

        // 3. Migrasi ref_roles -> iam_roles (hanya jika tabel masih ada)
        $roleMap = []; // ref_role_id => iam_role_id
        $hasRefRoles = false;

        if (DB::getSchemaBuilder()->hasTable('ref_roles')) {
            $hasRefRoles = true;
            $refRoles = DB::table('ref_roles')->whereNull('deleted_at')->get();

            foreach ($refRoles as $refRole) {
                $slug = Str::slug($refRole->nama);
                $iamRole = IamRole::firstOrCreate(
                    ['iam_application_id' => $kepegawaian->id, 'slug' => $slug],
                    [
                        'nama' => $refRole->nama,
                        'keterangan' => $refRole->keterangan,
                        'is_system' => $refRole->is_system ?? false,
                    ]
                );
                $roleMap[$refRole->id] = $iamRole->id;
            }
        }

        // 4. Migrasi ref_permissions -> iam_permissions (hanya jika tabel masih ada)
        $permMap = [];
        $hasRefPermissions = false;

        if (DB::getSchemaBuilder()->hasTable('ref_permissions')) {
            $hasRefPermissions = true;
            $refPerms = DB::table('ref_permissions')->whereNull('deleted_at')->get();

            foreach ($refPerms as $refPerm) {
                $slug = Str::slug($refPerm->nama, ':');
                $iamPerm = IamPermission::create([
                    'iam_application_id' => $kepegawaian->id,
                    'nama' => $refPerm->nama,
                    'slug' => $slug,
                    'group' => $refPerm->group,
                    'keterangan' => $refPerm->keterangan,
                ]);
                $permMap[$refPerm->id] = $iamPerm->id;
            }
        }

        // 5. Migrasi ref_role_permission -> iam_role_permissions (hanya jika tabel masih ada)
        if ($hasRefRoles && $hasRefPermissions && DB::getSchemaBuilder()->hasTable('ref_role_permission')) {
            $pivots = DB::table('ref_role_permission')->get();
            foreach ($pivots as $pivot) {
                if (isset($roleMap[$pivot->ref_role_id]) && isset($permMap[$pivot->ref_permission_id])) {
                    IamRolePermission::create([
                        'iam_role_id' => $roleMap[$pivot->ref_role_id],
                        'iam_permission_id' => $permMap[$pivot->ref_permission_id],
                    ]);
                }
            }
        }

        // 6. Migrasi users.role -> iam_user_roles (jika kolom masih ada)
        if (DB::getSchemaBuilder()->hasColumn('users', 'role')) {
            // Optimasi N+1: muat semua role sekali ke memory
            $rolesBySlug = IamRole::where('iam_application_id', $kepegawaian->id)
                ->get()
                ->keyBy('slug');

            // Gunakan chunking untuk menghindari memory issues dengan data besar
            Pegawai::chunk(100, function ($users) use ($kepegawaian, $rolesBySlug, $viewerRole) {
                foreach ($users as $user) {
                    $roleSlug = $user->getRawOriginal('role') ?? 'viewer';
                    $iamRole = $rolesBySlug->get($roleSlug) ?? $viewerRole;

                    if ($iamRole) {
                        IamUserRole::firstOrCreate(
                            ['user_id' => $user->id, 'iam_role_id' => $iamRole->id],
                            ['assigned_at' => now()]
                        );
                    }
                }
            });
        }

        // 7. Tambahkan default permissions untuk kepegawaian
        $defaultPermissions = [
            ['slug' => 'iam-manage', 'nama' => 'Kelola IAM', 'group' => 'iam', 'keterangan' => 'Akses penuh ke manajemen IAM'],
            ['slug' => 'pegawai.view', 'nama' => 'Lihat Pegawai', 'group' => 'pegawai', 'keterangan' => 'Melihat data pegawai'],
            ['slug' => 'pegawai.create', 'nama' => 'Tambah Pegawai', 'group' => 'pegawai', 'keterangan' => 'Menambah data pegawai'],
            ['slug' => 'pegawai.update', 'nama' => 'Ubah Pegawai', 'group' => 'pegawai', 'keterangan' => 'Mengubah data pegawai'],
            ['slug' => 'pegawai.delete', 'nama' => 'Hapus Pegawai', 'group' => 'pegawai', 'keterangan' => 'Menghapus data pegawai'],
            ['slug' => 'referensi.view', 'nama' => 'Lihat Referensi', 'group' => 'referensi', 'keterangan' => 'Melihat data referensi'],
            ['slug' => 'referensi.create', 'nama' => 'Tambah Referensi', 'group' => 'referensi', 'keterangan' => 'Menambah data referensi'],
            ['slug' => 'referensi.update', 'nama' => 'Ubah Referensi', 'group' => 'referensi', 'keterangan' => 'Mengubah data referensi'],
            ['slug' => 'referensi.delete', 'nama' => 'Hapus Referensi', 'group' => 'referensi', 'keterangan' => 'Menghapus data referensi'],
            ['slug' => 'rbac.manage', 'nama' => 'Kelola RBAC', 'group' => 'rbac', 'keterangan' => 'Mengelola role & permission'],
        ];

        $permissionIds = [];
        foreach ($defaultPermissions as $perm) {
            $p = $kepegawaian->permissions()->firstOrCreate(
                ['slug' => $perm['slug']],
                ['nama' => $perm['nama'], 'group' => $perm['group'], 'keterangan' => $perm['keterangan']]
            );
            $permissionIds[$perm['slug']] = $p->id;
        }

        // Admin mendapat semua permission
        if ($adminRole) {
            $adminRole->permissions()->syncWithoutDetaching(array_values($permissionIds));
        }

        // Operator mendapat pegawai.* dan referensi.view
        if ($operatorRole) {
            $operatorRole->permissions()->syncWithoutDetaching([
                $permissionIds['pegawai.view'],
                $permissionIds['pegawai.create'],
                $permissionIds['pegawai.update'],
                $permissionIds['pegawai.delete'],
                $permissionIds['referensi.view'],
            ]);
        }
    }
}
