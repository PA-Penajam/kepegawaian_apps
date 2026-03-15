<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dokumen_pegawai', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->string('jenis_dokumen', 100);
            $table->string('nomor_dokumen', 100)->nullable();
            $table->date('tanggal_dokumen')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_pegawai');
    }
};
