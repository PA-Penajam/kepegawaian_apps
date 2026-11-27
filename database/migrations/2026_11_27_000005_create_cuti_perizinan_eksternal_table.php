<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membuat tabel catatan perizinan cuti eksternal di PTA/MA beserta tautan SK Surat Masuk SiSukma.
     */
    public function up(): void
    {
        Schema::create('cuti_perizinan_eksternal', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('pengajuan_id')->unique();
            $table->string('pemberi_izin', 10);
            $table->string('instansi_nama', 150);
            $table->string('nomor_registrasi_eksternal', 100)->nullable();
            $table->date('tanggal_pengajuan_eksternal')->nullable();
            $table->text('catatan_pengajuan')->nullable();
            $table->string('sisukma_incoming_mail_public_id', 36)->nullable()->index();
            $table->string('sisukma_agenda_number', 50)->nullable();
            $table->string('nomor_sk', 100)->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->string('keputusan', 20)->nullable();
            $table->text('catatan_keputusan')->nullable();
            $table->string('recorded_by_nip', 20)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->foreign('pengajuan_id')->references('id')->on('cuti_pengajuan')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuti_perizinan_eksternal');
    }
};
