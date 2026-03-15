<?php

namespace Database\Seeders;

use App\Models\RefPermission;
use App\Models\RefRole;
use Illuminate\Database\Seeder;

class RefPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Pegawai
            ['nama' => 'pegawai.view', 'group' => 'pegawai', 'keterangan' => 'Melihat data pegawai'],
            ['nama' => 'pegawai.create', 'group' => 'pegawai', 'keterangan' => 'Membuat data pegawai'],
            ['nama' => 'pegawai.update', 'group' => 'pegawai', 'keterangan' => 'Mengubah data pegawai'],
            ['nama' => 'pegawai.delete', 'group' => 'pegawai', 'keterangan' => 'Menghapus data pegawai'],
            // Referensi
            ['nama' => 'referensi.view', 'group' => 'referensi', 'keterangan' => 'Melihat data referensi'],
            ['nama' => 'referensi.create', 'group' => 'referensi', 'keterangan' => 'Membuat data referensi'],
            ['nama' => 'referensi.update', 'group' => 'referensi', 'keterangan' => 'Mengubah data referensi'],
            ['nama' => 'referensi.delete', 'group' => 'referensi', 'keterangan' => 'Menghapus data referensi'],
            // Monitoring
            ['nama' => 'monitoring.view', 'group' => 'monitoring', 'keterangan' => 'Melihat monitoring'],
            // Self Service
            ['nama' => 'self-service.view', 'group' => 'self-service', 'keterangan' => 'Akses self service'],
            // RBAC (admin only)
            ['nama' => 'rbac.manage', 'group' => 'rbac', 'keterangan' => 'Mengelola roles dan permissions'],
        ];
        foreach ($permissions as $permission) {
            RefPermission::query()->updateOrCreate(['nama' => $permission['nama']], $permission);
        }
        // Assign all permissions to Admin role
        $adminRole = RefRole::query()->where('nama', 'Admin')->first();
        $allPermissions = RefPermission::query()->pluck('id');
        if ($adminRole) {
            $adminRole->permissions()->sync($allPermissions);
        }
        // Assign pegawai & referensi permissions to Operator
        $operatorRole = RefRole::query()->where('nama', 'Operator')->first();
        if ($operatorRole) {
            $operatorPermissions = RefPermission::query()
                ->whereIn('group', ['pegawai', 'referensi'])
                ->pluck('id');
            $operatorRole->permissions()->sync($operatorPermissions);
        }
        // Assign view-only permissions to Viewer
        $viewerRole = RefRole::query()->where('nama', 'Viewer')->first();
        if ($viewerRole) {
            $viewerPermissions = RefPermission::query()
                ->where('nama', 'like', '%.view')
                ->pluck('id');
            $viewerRole->permissions()->sync($viewerPermissions);
        }
    }
}
