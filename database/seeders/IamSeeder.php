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

        $kepegawaian = IamApplication::create([
            'nama'            => 'Kepegawaian Apps',
            'slug'            => 'kepegawaian',
            'url'             => config('app.url'),
            'deskripsi'       => 'Sistem master data kepegawaian PA Penajam',
            'api_key'         => $key,
            'api_secret_hash' => $hash,
            'is_system'       => true,
            'is_active'       => true,
        ]);

        // 2. Migrasi ref_roles -> iam_roles
        $refRoles = DB::table('ref_roles')->whereNull('deleted_at')->get();
        $roleMap  = []; // ref_role_id => iam_role_id

        foreach ($refRoles as $refRole) {
            $slug = Str::slug($refRole->nama);
            $iamRole = IamRole::firstOrCreate(
                ['iam_application_id' => $kepegawaian->id, 'slug' => $slug],
                [
                    'nama'      => $refRole->nama,
                    'keterangan' => $refRole->keterangan,
                    'is_system' => $refRole->is_system ?? false,
                ]
            );
            $roleMap[$refRole->id] = $iamRole->id;
        }

        // 3. Migrasi ref_permissions -> iam_permissions
        $refPerms = DB::table('ref_permissions')->whereNull('deleted_at')->get();
        $permMap  = [];

        foreach ($refPerms as $refPerm) {
            $slug = Str::slug($refPerm->nama, ':');
            $iamPerm = IamPermission::create([
                'iam_application_id' => $kepegawaian->id,
                'nama'               => $refPerm->nama,
                'slug'               => $slug,
                'group'              => $refPerm->group,
                'keterangan'         => $refPerm->keterangan,
            ]);
            $permMap[$refPerm->id] = $iamPerm->id;
        }

        // 4. Migrasi ref_role_permission -> iam_role_permissions
        $pivots = DB::table('ref_role_permission')->get();
        foreach ($pivots as $pivot) {
            if (isset($roleMap[$pivot->ref_role_id]) && isset($permMap[$pivot->ref_permission_id])) {
                IamRolePermission::create([
                    'iam_role_id'       => $roleMap[$pivot->ref_role_id],
                    'iam_permission_id' => $permMap[$pivot->ref_permission_id],
                ]);
            }
        }

        // 5. Migrasi users.role -> iam_user_roles (jika kolom masih ada)
        if (DB::getSchemaBuilder()->hasColumn('users', 'role')) {
            $users = User::all();
            $defaultRole = IamRole::where('iam_application_id', $kepegawaian->id)
                ->where('slug', 'viewer')
                ->first();

            foreach ($users as $user) {
                $roleSlug = $user->getRawOriginal('role') ?? 'viewer';
                $iamRole = IamRole::where('iam_application_id', $kepegawaian->id)
                    ->where('slug', $roleSlug)
                    ->first() ?? $defaultRole;

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
    }
}
