<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('riwayat_jabatan', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignUlid('ref_jabatan_id')->nullable()->constrained('ref_jabatan')->nullOnDelete();
            $table->foreignUlid('ref_unit_kerja_id')->nullable()->constrained('ref_unit_kerja')->nullOnDelete();
            $table->string('no_sk');
            $table->date('tanggal_sk');
            $table->date('tmt');
            $table->string('pejabat_penetap')->nullable();
            $table->boolean('is_aktif')->default(false);
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_jabatan');
    }
};
