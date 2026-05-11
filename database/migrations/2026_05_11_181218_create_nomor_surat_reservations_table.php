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
        Schema::create('nomor_surat_reservations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedInteger('nomor_urut');
            $table->string('nomor_lengkap')->unique();
            $table->string('klasifikasi', 50);
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan');
            $table->string('status', 30)->default('reserved');
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['klasifikasi', 'tahun']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomor_surat_reservations');
    }
};
