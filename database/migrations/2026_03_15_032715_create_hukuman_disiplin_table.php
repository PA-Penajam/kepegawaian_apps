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
        Schema::create('hukuman_disiplin', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignUlid('ref_jenis_hukuman_disiplin_id')->nullable()->constrained('ref_jenis_hukuman_disiplin')->nullOnDelete();
            $table->string('no_sk');
            $table->date('tanggal_sk');
            $table->date('tmt_berlaku');
            $table->date('tmt_selesai')->nullable();
            $table->text('pelanggaran');
            $table->string('pejabat_penetap')->nullable();
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
        Schema::dropIfExists('hukuman_disiplin');
    }
};
