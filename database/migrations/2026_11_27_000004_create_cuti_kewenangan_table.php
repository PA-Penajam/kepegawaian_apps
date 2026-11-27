<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel matriks kewenangan persetujuan cuti ke instansi atasan (PTA/MA).
     * Sengaja dimulai kosong agar diisi manual oleh admin kepegawaian.
     */
    public function up(): void
    {
        Schema::create('cuti_kewenangan', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('jenis_cuti_kode', 20);
            $table->string('jenis_jabatan', 30);
            $table->string('pemberi_izin', 10);
            $table->string('instansi_nama', 150);
            $table->string('nama_aplikasi', 100)->nullable();
            $table->string('url_aplikasi', 255)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique(['jenis_cuti_kode', 'jenis_jabatan'], 'cuti_kewenangan_jenis_jabatan_unique');
            $table->foreign('jenis_cuti_kode')->references('kode')->on('cuti_jenis_master')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuti_kewenangan');
    }
};
