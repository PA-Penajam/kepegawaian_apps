<?php

use App\Models\RefRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah kolom ref_role_id
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUlid('ref_role_id')
                ->nullable()
                ->after('remember_token')
                ->constrained('ref_roles')
                ->nullOnDelete();
        });

        // Migrasi data: map enum role ke ref_roles
        $roleMap = RefRole::query()->pluck('id', 'nama');

        DB::table('users')->where('role', 'admin')
            ->update(['ref_role_id' => $roleMap->get('Admin')]);
        DB::table('users')->where('role', 'operator')
            ->update(['ref_role_id' => $roleMap->get('Operator')]);
        DB::table('users')->where('role', 'viewer')
            ->update(['ref_role_id' => $roleMap->get('Viewer')]);

        // User tanpa role -> default Viewer
        $viewerRoleId = $roleMap->get('Viewer');
        DB::table('users')->whereNull('ref_role_id')
            ->update(['ref_role_id' => $viewerRoleId]);

        // Hapus kolom role enum
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('viewer');
        });

        $roles = RefRole::query()->pluck('nama', 'id');
        foreach ($roles as $id => $nama) {
            DB::table('users')->where('ref_role_id', $id)
                ->update(['role' => strtolower($nama)]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['ref_role_id']);
            $table->dropColumn('ref_role_id');
        });
    }
};
