<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pegawai (user model untuk Sanctum) menggunakan ULID sebagai primary key
     * (`char(26)`). Default `morphs()` menghasilkan `bigint unsigned` untuk
     * `tokenable_id` sehingga ULID di-truncate. Migration ini mengubah
     * `tokenable_id` ke `char(26)` agar konsisten dengan ULID Pegawai.
     */
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // Drop index yang dibuat oleh morphs() supaya kolom dapat diubah.
            $table->dropIndex(['tokenable_type', 'tokenable_id']);
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->char('tokenable_id', 26)->change();
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->index(['tokenable_type', 'tokenable_id']);
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex(['tokenable_type', 'tokenable_id']);
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('tokenable_id')->change();
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->index(['tokenable_type', 'tokenable_id']);
        });
    }
};
