<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('berkas_checklist_submission_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('berkas_checklist_submission_id')
                ->constrained('berkas_checklist_submissions')->cascadeOnDelete();
            $table->foreignUlid('berkas_checklist_item_id')
                ->constrained('berkas_checklist_items')->restrictOnDelete();
            $table->string('status')->default('belum_ada');
            $table->text('catatan')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('file_original_name')->nullable();
            $table->string('file_mime', 100)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->foreignUlid('validated_by')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['berkas_checklist_submission_id', 'berkas_checklist_item_id'],
                'uq_bcsi_submission_item'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('berkas_checklist_submission_items');
    }
};
