<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan kolom outcome untuk mencatat hasil percobaan emergency login.
     */
    public function up(): void
    {
        Schema::table('keycloak_emergency_login_log', function (Blueprint $table) {
            $table->string('outcome', 20)->after('logged_out_at')->default('failure');
        });
    }

    /**
     * Rollback migration.
     */
    public function down(): void
    {
        Schema::table('keycloak_emergency_login_log', function (Blueprint $table) {
            $table->dropColumn('outcome');
        });
    }
};
