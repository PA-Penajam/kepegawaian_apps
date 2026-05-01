<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuti_pengajuan_approval_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('pengajuan_id');
            $table->enum('role', ['petugas_kepegawaian', 'atasan_langsung', 'pejabat_berwenang']);
            $table->enum('action', ['approve', 'reject', 'verify']);
            $table->string('aktor_pegawai_nip', 20);
            $table->text('catatan')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();
            $table->foreign('pengajuan_id')->references('id')->on('cuti_pengajuan')->cascadeOnDelete();
            $table->foreign('aktor_pegawai_nip')->references('nip')->on('pegawai');
            $table->index('pengajuan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuti_pengajuan_approval_steps');
    }
};
