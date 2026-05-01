<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuti_jenis_master', function (Blueprint $table) {
            $table->string('kode', 10)->primary();
            $table->string('nama', 100);
            $table->boolean('saldo_driven')->default(false);
            $table->integer('hak_default_per_tahun')->nullable();
            $table->integer('durasi_min_kalender')->nullable();
            $table->integer('durasi_max_kalender')->nullable();
            $table->boolean('butuh_lampiran')->default(false);
            $table->boolean('boleh_dicabut_setelah_disetujui')->default(false);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuti_jenis_master');
    }
};
