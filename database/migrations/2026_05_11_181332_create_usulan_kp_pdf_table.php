<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usulan_kp_pdf', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('usulan_kenaikan_pangkat_id')
                ->constrained('usulan_kenaikan_pangkat')->cascadeOnDelete();
            $table->string('jenis_pdf', 50);
            $table->string('nomor_surat');
            $table->string('file_path', 500);
            $table->foreignUlid('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();

            $table->index(['usulan_kenaikan_pangkat_id', 'jenis_pdf'], 'idx_pdf_jenis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usulan_kp_pdf');
    }
};
