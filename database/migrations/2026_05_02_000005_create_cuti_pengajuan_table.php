<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuti_pengajuan', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('nomor_pengajuan', 50)->unique();
            $table->string('pegawai_nip', 20);
            $table->string('jenis_cuti_kode', 10);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->integer('jumlah_hari_kerja');
            $table->text('alasan');
            $table->text('alamat_selama_cuti')->nullable();
            $table->string('nomor_telp_selama_cuti', 30)->nullable();
            $table->string('state', 50)->default('DRAFT');
            // Snapshot (immutable)
            $table->string('petugas_kepegawaian_snapshot_nip', 20)->nullable();
            $table->string('atasan_langsung_snapshot_nip', 20)->nullable();
            $table->string('pejabat_berwenang_snapshot_nip', 20)->nullable();
            // Current (mutable)
            $table->string('petugas_kepegawaian_current_nip', 20)->nullable();
            $table->string('atasan_langsung_current_nip', 20)->nullable();
            $table->string('pejabat_berwenang_current_nip', 20)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->index('pegawai_nip');
            $table->index('state');
            $table->index('atasan_langsung_current_nip');
            $table->index('pejabat_berwenang_current_nip');
            $table->foreign('pegawai_nip')->references('nip')->on('pegawai');
            $table->foreign('atasan_langsung_snapshot_nip')->references('nip')->on('pegawai');
            $table->foreign('jenis_cuti_kode')->references('kode')->on('cuti_jenis_master');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuti_pengajuan');
    }
};
