<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan kolom keycloak_id ke tabel pegawai untuk linking ke Keycloak UUID.
     * Catatan: di aplikasi ini, pegawai adalah authenticatable model (pengganti users).
     */
    public function up(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->string('keycloak_id', 36)->nullable()->unique()->after('id');
        });
    }

    /**
     * Rollback migration.
     */
    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropUnique(['keycloak_id']);
            $table->dropColumn('keycloak_id');
        });
    }
};
