<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuti_pengajuan_approver_history', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('pengajuan_id');
            $table->enum('role', ['petugas_kepegawaian', 'atasan_langsung', 'pejabat_berwenang']);
            $table->string('from_pegawai_nip', 20)->nullable();
            $table->string('to_pegawai_nip', 20);
            $table->string('alasan', 500);
            $table->string('aktor_pegawai_nip', 20);
            $table->timestamp('created_at')->useCurrent();
            $table->index('pengajuan_id');
            $table->foreign('pengajuan_id')->references('id')->on('cuti_pengajuan')->cascadeOnDelete();
            $table->foreign('from_pegawai_nip')->references('nip')->on('pegawai');
            $table->foreign('to_pegawai_nip')->references('nip')->on('pegawai');
            $table->foreign('aktor_pegawai_nip')->references('nip')->on('pegawai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuti_pengajuan_approver_history');
    }
};
