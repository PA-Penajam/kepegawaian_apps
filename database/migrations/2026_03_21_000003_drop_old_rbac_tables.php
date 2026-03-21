<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop kolom users.role
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        // Drop tabel lama (urutan: pivot dulu, lalu parent)
        Schema::dropIfExists('ref_role_permission');
        Schema::dropIfExists('ref_permissions');
        Schema::dropIfExists('ref_roles');
    }

    public function down(): void
    {
        // Tidak bisa di-reverse secara otomatis (data sudah di IAM tables)
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('viewer');
        });
    }
};
