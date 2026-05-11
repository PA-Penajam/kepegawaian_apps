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
        Schema::create('nomor_surat_sequences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('klasifikasi', 50);
            $table->unsignedSmallInteger('tahun');
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['klasifikasi', 'tahun']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nomor_surat_sequences');
    }
};
