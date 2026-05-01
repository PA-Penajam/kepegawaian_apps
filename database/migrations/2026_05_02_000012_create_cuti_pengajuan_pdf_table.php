<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuti_pengajuan_pdf', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('pengajuan_id');
            $table->string('path_file', 500);
            $table->string('checksum_sha256', 64);
            $table->integer('size_bytes');
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->index('pengajuan_id');
            $table->foreign('pengajuan_id')->references('id')->on('cuti_pengajuan')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuti_pengajuan_pdf');
    }
};
