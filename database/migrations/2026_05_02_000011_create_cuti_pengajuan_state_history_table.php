<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuti_pengajuan_state_history', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('pengajuan_id');
            $table->string('state_from', 50)->nullable();
            $table->string('state_to', 50);
            $table->string('aktor_pegawai_nip', 20);
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('pengajuan_id');
            $table->foreign('pengajuan_id')->references('id')->on('cuti_pengajuan')->cascadeOnDelete();
            $table->foreign('aktor_pegawai_nip')->references('nip')->on('pegawai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuti_pengajuan_state_history');
    }
};
