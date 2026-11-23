<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan indeks komposit untuk query monitoring kepegawaian dan pembersihan kode SSO.
     */
    public function up(): void
    {
        Schema::table('riwayat_pangkat', function (Blueprint $table): void {
            $table->index(['pegawai_id', 'is_aktif', 'deleted_at'], 'idx_riwayat_pangkat_pegawai_aktif_deleted');
        });

        Schema::table('riwayat_jabatan', function (Blueprint $table): void {
            $table->index(['pegawai_id', 'is_aktif', 'deleted_at'], 'idx_riwayat_jabatan_pegawai_aktif_deleted');
        });

        Schema::table('hukuman_disiplin', function (Blueprint $table): void {
            $table->index(['pegawai_id', 'deleted_at', 'tmt_selesai'], 'idx_hukuman_disiplin_pegawai_deleted_selesai');
        });

        Schema::table('iam_sso_codes', function (Blueprint $table): void {
            $table->index(['expires_at'], 'idx_iam_sso_codes_expires_at');
        });
    }

    /**
     * Menghapus indeks komposit yang ditambahkan pada migrasi ini.
     */
    public function down(): void
    {
        Schema::table('riwayat_pangkat', function (Blueprint $table): void {
            $table->dropIndex('idx_riwayat_pangkat_pegawai_aktif_deleted');
        });

        Schema::table('riwayat_jabatan', function (Blueprint $table): void {
            $table->dropIndex('idx_riwayat_jabatan_pegawai_aktif_deleted');
        });

        Schema::table('hukuman_disiplin', function (Blueprint $table): void {
            $table->dropIndex('idx_hukuman_disiplin_pegawai_deleted_selesai');
        });

        Schema::table('iam_sso_codes', function (Blueprint $table): void {
            $table->dropIndex('idx_iam_sso_codes_expires_at');
        });
    }
};
