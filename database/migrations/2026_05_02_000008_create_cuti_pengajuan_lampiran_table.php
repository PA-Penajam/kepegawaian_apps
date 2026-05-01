<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuti_pengajuan_lampiran', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('pengajuan_id');
            $table->string('jenis_lampiran', 50);
            $table->string('nama_file_asli', 255);
            $table->string('path_file', 500);
            $table->string('mime_type', 100);
            $table->integer('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->string('uploaded_by_nip', 20);
            $table->timestamps();
            $table->foreign('pengajuan_id')->references('id')->on('cuti_pengajuan')->cascadeOnDelete();
            $table->foreign('uploaded_by_nip')->references('nip')->on('pegawai');
            $table->index('pengajuan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuti_pengajuan_lampiran');
    }
};
