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
        Schema::create('penghargaan', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignUlid('ref_jenis_penghargaan_id')->nullable()->constrained('ref_jenis_penghargaan')->nullOnDelete();
            $table->string('nama_penghargaan');
            $table->string('no_sk')->nullable();
            $table->date('tanggal_sk')->nullable();
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
        Schema::dropIfExists('penghargaan');
    }
};
