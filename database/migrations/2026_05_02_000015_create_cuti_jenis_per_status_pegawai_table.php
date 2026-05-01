<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuti_jenis_per_status_pegawai', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('jenis_cuti_kode', 10);
            $table->string('status_kepegawaian', 20);
            $table->boolean('boleh')->default(true);
            $table->integer('hak_per_tahun')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->unique(['jenis_cuti_kode', 'status_kepegawaian'], 'uk_jenis_status');
            $table->foreign('jenis_cuti_kode')->references('kode')->on('cuti_jenis_master');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuti_jenis_per_status_pegawai');
    }
};
