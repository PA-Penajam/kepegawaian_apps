<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuti_alokasi_tahunan', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('pegawai_nip', 20);
            $table->string('jenis_cuti_kode', 10);
            $table->smallInteger('tahun_hak');
            $table->integer('hak_awal');
            $table->string('catatan', 500)->nullable();
            $table->timestamps();
            $table->unique(['pegawai_nip', 'jenis_cuti_kode', 'tahun_hak'], 'uk_alokasi');
            $table->foreign('pegawai_nip')->references('nip')->on('pegawai');
            $table->foreign('jenis_cuti_kode')->references('kode')->on('cuti_jenis_master');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuti_alokasi_tahunan');
    }
};
