<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_dinas_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulidMorphs('suratable');
            $table->string('jenis', 50);
            $table->string('klasifikasi', 50);
            $table->string('penandatangan', 20)->default('ketua');
            $table->string('document_type', 20)->default('surat_dinas');
            $table->string('perihal');
            $table->string('tujuan_nama');
            $table->string('tujuan_instansi')->nullable();
            $table->string('tujuan_alamat', 500)->nullable();
            $table->string('external_path', 255)->nullable();
            $table->string('status', 30)->default('BELUM_TERKIRIM')->index();
            $table->string('sisukma_public_id', 26)->nullable()->unique();
            $table->string('nomor_agenda', 30)->nullable();
            $table->string('nomor_surat', 100)->nullable()->unique();
            $table->text('catatan_revisi')->nullable();
            $table->text('alasan_void')->nullable();
            $table->string('final_checksum', 64)->nullable();
            $table->unsignedInteger('final_size_bytes')->nullable();
            $table->timestamp('final_uploaded_at')->nullable();
            $table->string('requested_by_nip', 20);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['suratable_type', 'suratable_id', 'jenis'], 'idx_surat_dinas_suratable_jenis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_dinas_requests');
    }
};
