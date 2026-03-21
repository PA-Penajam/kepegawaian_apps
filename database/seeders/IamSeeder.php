<?php

namespace Database\Seeders;

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamRolePermission;
use App\Models\IamUserRole;
use App\Models\User;
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

        // 2. Migrasi ref_roles -> iam_roles (hanya jika tabel masih ada)
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

        // 3. Migrasi ref_permissions -> iam_permissions (hanya jika tabel masih ada)
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

        // 4. Migrasi ref_role_permission -> iam_role_permissions (hanya jika tabel masih ada)
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

        // 5. Migrasi users.role -> iam_user_roles (jika kolom masih ada)
        if (DB::getSchemaBuilder()->hasColumn('users', 'role')) {
            $users = User::all();
            $defaultRole = IamRole::where('iam_application_id', $kepegawaian->id)
                ->where('slug', 'viewer')
                ->first();

            // Optimasi N+1: muat semua role sekali ke memory
            $rolesBySlug = IamRole::where('iam_application_id', $kepegawaian->id)
                ->get()
                ->keyBy('slug');

            foreach ($users as $user) {
                $roleSlug = $user->getRawOriginal('role') ?? 'viewer';
                $iamRole = $rolesBySlug->get($roleSlug) ?? $defaultRole;

                if ($iamRole) {
                    IamUserRole::firstOrCreate(
                        ['user_id' => $user->id, 'iam_role_id' => $iamRole->id],
                        ['assigned_at' => now()]
                    );
                }
            }
        }

        // 6. Pastikan role default tersedia (admin, operator, viewer)
        foreach (['admin', 'operator', 'viewer'] as $slug) {
            IamRole::firstOrCreate(
                ['iam_application_id' => $kepegawaian->id, 'slug' => $slug],
                ['nama' => ucfirst($slug), 'is_system' => true]
            );
        }

        // 7. Tambahkan permission iam-manage untuk akses penuh ke manajemen IAM
        $iamManage = $kepegawaian->permissions()->firstOrCreate(
            ['slug' => 'iam-manage'],
            ['nama' => 'Kelola IAM', 'group' => 'iam', 'keterangan' => 'Akses penuh ke manajemen IAM']
        );

        // Assign ke role admin
        $adminRole = IamRole::where('iam_application_id', $kepegawaian->id)
            ->where('slug', 'admin')
            ->first();

        if ($adminRole) {
            $adminRole->permissions()->syncWithoutDetaching([$iamManage->id]);
        }
    }
}
