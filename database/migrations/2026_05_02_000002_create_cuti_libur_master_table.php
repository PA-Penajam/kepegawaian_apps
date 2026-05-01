<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuti_libur_master', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->date('tanggal')->unique();
            $table->string('keterangan', 200);
            $table->boolean('is_cuti_bersama')->default(false);
            $table->smallInteger('tahun');
            $table->index('tahun');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuti_libur_master');
    }
};
