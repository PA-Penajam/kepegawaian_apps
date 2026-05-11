<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('berkas_checklist_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('berkas_checklist_template_id')->references('id')->on('berkas_checklist_templates')->cascadeOnDelete();
            $table->string('kode');
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->boolean('wajib')->default(true);
            $table->string('syarat_format')->nullable();
            $table->unsignedInteger('ukuran_maksimal_kb')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->string('konfigurasi_tambahan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['berkas_checklist_template_id', 'kode']);
            $table->index(['berkas_checklist_template_id', 'wajib']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('berkas_checklist_items');
    }
};
