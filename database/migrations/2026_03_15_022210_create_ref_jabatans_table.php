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
        Schema::create('ref_jabatan', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->string('jenis_jabatan');
            $table->string('eselon')->nullable();
            $table->integer('kelas_jabatan')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ref_jabatan');
    }
};
