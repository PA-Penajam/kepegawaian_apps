<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuti_konfigurasi', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key', 100)->unique();
            $table->json('value');
            $table->string('keterangan', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuti_konfigurasi');
    }
};
