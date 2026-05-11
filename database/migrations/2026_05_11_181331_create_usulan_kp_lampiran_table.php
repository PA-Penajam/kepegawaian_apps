<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usulan_kp_lampiran', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('usulan_kenaikan_pangkat_id')
                ->constrained('usulan_kenaikan_pangkat')->cascadeOnDelete();
            $table->string('jenis', 50);
            $table->string('nama_file');
            $table->string('file_path', 500);
            $table->string('file_original_name');
            $table->string('file_mime', 100);
            $table->unsignedInteger('file_size');
            $table->foreignUlid('uploaded_by')->constrained('pegawai')->restrictOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['usulan_kenaikan_pangkat_id', 'jenis'], 'idx_lampiran_jenis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usulan_kp_lampiran');
    }
};
