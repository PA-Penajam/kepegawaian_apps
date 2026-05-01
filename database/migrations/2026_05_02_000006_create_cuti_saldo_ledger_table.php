<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuti_saldo_ledger', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('pegawai_nip', 20);
            $table->string('jenis_cuti_kode', 10);
            $table->smallInteger('tahun_hak');
            $table->enum('jenis_transaksi', ['kredit', 'debit_pending', 'debit_void', 'debit_confirmed', 'kredit_refund', 'expire', 'penyesuaian']);
            $table->integer('jumlah_hari');
            $table->ulid('pengajuan_id')->nullable();
            $table->string('keterangan', 500)->nullable();
            $table->string('aktor_pegawai_nip', 20);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['pegawai_nip', 'jenis_cuti_kode', 'tahun_hak'], 'idx_pegawai_jenis_tahun');
            $table->index('pengajuan_id', 'idx_pengajuan');
            $table->foreign('pegawai_nip')->references('nip')->on('pegawai');
            $table->foreign('aktor_pegawai_nip')->references('nip')->on('pegawai');
            $table->foreign('pengajuan_id')->references('id')->on('cuti_pengajuan');
            $table->foreign('jenis_cuti_kode')->references('kode')->on('cuti_jenis_master');
            $table->unique(['pengajuan_id', 'jenis_transaksi', 'tahun_hak'], 'uk_pengajuan_transaksi_bucket');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuti_saldo_ledger');
    }
};
