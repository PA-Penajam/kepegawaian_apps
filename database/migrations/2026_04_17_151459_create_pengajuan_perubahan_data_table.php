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
        Schema::create('pengajuan_perubahan_data', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('nomor_pengajuan')->unique();
            $table->foreignUlid('pengaju_id')->constrained('pegawai');
            $table->foreignUlid('subject_pegawai_id')->constrained('pegawai');
            $table->foreignUlid('validator_id')->nullable()->constrained('pegawai');
            $table->string('jenis_pengaju');
            $table->string('domain');
            $table->string('aksi');
            $table->string('scope_key')->index();
            $table->string('target_type');
            $table->ulid('target_id')->nullable();
            $table->string('status')->default('pending');
            $table->json('before_payload')->nullable();
            $table->json('after_payload');
            $table->json('changed_fields')->nullable();
            $table->json('lampiran_paths')->nullable();
            $table->text('alasan_penolakan')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengajuan_perubahan_data');
    }
};
