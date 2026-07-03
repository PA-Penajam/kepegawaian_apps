<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan kolom keycloak_synced_at dan keycloak_user_id ke tabel pegawai.
     */
    public function up(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->timestamp('keycloak_synced_at')->nullable()->after('keterangan');
            $table->string('keycloak_user_id', 36)->nullable()->after('keycloak_synced_at');
        });
    }

    /**
     * Rollback migration.
     */
    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropColumn(['keycloak_synced_at', 'keycloak_user_id']);
        });
    }
};
