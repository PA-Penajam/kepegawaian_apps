<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usulan_kenaikan_pangkat', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->foreignUlid('ref_pangkat_asal_id')->constrained('ref_pangkat')->restrictOnDelete();
            $table->foreignUlid('ref_pangkat_tujuan_id')->constrained('ref_pangkat')->restrictOnDelete();
            $table->date('tmt_pangkat_asal');
            $table->unsignedTinyInteger('periode_usul_bulan');
            $table->year('periode_usul_tahun');
            $table->string('nomor_usulan', 50)->nullable();
            $table->date('tanggal_usulan')->nullable();
            $table->string('state', 30)->default('DRAFT');
            $table->text('catatan_pengusul')->nullable();
            $table->text('catatan_penolakan')->nullable();
            $table->string('nomor_sk', 50)->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->string('sk_file_path', 500)->nullable();
            $table->string('sk_file_original_name', 255)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Composite indexes
            $table->index(['periode_usul_tahun', 'periode_usul_bulan', 'state'], 'idx_periode_state');
            $table->index(['pegawai_id', 'state'], 'idx_pegawai_state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usulan_kenaikan_pangkat');
    }
};
