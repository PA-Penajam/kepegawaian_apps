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
        Schema::create('ref_pangkat', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('golongan');
            $table->string('ruang');
            $table->string('tingkat');
            $table->integer('urutan')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ref_pangkat');
    }
};
