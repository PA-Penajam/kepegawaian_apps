<?php

namespace Database\Seeders;

use App\Models\RefRole;
use Illuminate\Database\Seeder;

class RefRoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['nama' => 'Admin', 'keterangan' => 'Administrator sistem', 'is_system' => true],
            ['nama' => 'Operator', 'keterangan' => 'Operator kepegawaian', 'is_system' => true],
            ['nama' => 'Viewer', 'keterangan' => 'Pengguna hanya bisa melihat', 'is_system' => true],
        ];
        foreach ($roles as $role) {
            RefRole::query()->updateOrCreate(['nama' => $role['nama']], $role);
        }
    }
}
