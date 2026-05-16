<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('berkas_checklist_submissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('berkas_checklist_template_id')->constrained('berkas_checklist_templates', 'id', 'fk_bcs_template_id')->restrictOnDelete();
            $table->string('subject_type');
            $table->ulid('subject_id');
            $table->foreignUlid('pegawai_id')->constrained('pegawai', 'id', 'fk_bcs_pegawai_id')->restrictOnDelete();
            $table->string('status_kelengkapan')->default('belum_lengkap');
            $table->unsignedTinyInteger('persentase')->default(0);
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('berkas_checklist_submissions');
    }
};
