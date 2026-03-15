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
        Schema::create('ref_unit_kerja', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->foreignUlid('parent_id')->nullable()->constrained('ref_unit_kerja')->nullOnDelete();
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
        Schema::dropIfExists('ref_unit_kerja');
    }
};
