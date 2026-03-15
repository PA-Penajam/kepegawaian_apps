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
        Schema::create('pegawai', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('nip')->nullable()->unique();
            $table->string('nip_lama')->nullable();
            $table->string('nama_lengkap');
            $table->string('tempat_lahir');
            $table->date('tanggal_lahir');
            $table->string('jenis_kelamin');
            $table->string('agama');
            $table->string('status_perkawinan');
            $table->string('golongan_darah')->nullable();
            $table->text('alamat')->nullable();
            $table->string('no_telepon')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('status_kepegawaian');
            $table->string('status_pegawai');
            $table->date('tmt_cpns')->nullable();
            $table->date('tmt_pns')->nullable();
            $table->string('pendidikan_terakhir')->nullable();
            $table->date('tanggal_masuk');
            $table->date('tanggal_pensiun_bup')->nullable();
            $table->foreignUlid('ref_pangkat_id')->nullable()->constrained('ref_pangkat')->nullOnDelete();
            $table->foreignUlid('ref_jabatan_id')->nullable()->constrained('ref_jabatan')->nullOnDelete();
            $table->foreignUlid('ref_unit_kerja_id')->nullable()->constrained('ref_unit_kerja')->nullOnDelete();
            $table->string('no_karpeg')->nullable();
            $table->string('no_karis_karsu')->nullable();
            $table->string('npwp')->nullable();
            $table->string('no_bpjs_kesehatan')->nullable();
            $table->string('no_bpjs_ketenagakerjaan')->nullable();
            $table->string('no_taspen')->nullable();
            $table->string('foto')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pegawai');
    }
};
