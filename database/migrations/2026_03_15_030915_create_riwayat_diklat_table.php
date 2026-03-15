<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('riwayat_diklat', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignUlid('ref_jenis_diklat_id')->nullable()->constrained('ref_jenis_diklat')->nullOnDelete();
            $table->string('nama_diklat');
            $table->string('penyelenggara');
            $table->string('tempat')->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->integer('jam_pelajaran')->nullable();
            $table->string('no_sertifikat')->nullable();
            $table->date('tanggal_sertifikat')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_diklat');
    }
};
