<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menghapus tiga tabel modul cuti yatim yang tidak digunakan di lingkungan inkremental.
     */
    public function up(): void
    {
        Schema::dropIfExists('cuti_pengajuan_periode');
        Schema::dropIfExists('cuti_jenis_per_status_pegawai');
        Schema::dropIfExists('cuti_konfigurasi');
    }

    /**
     * Tidak mengembalikan tabel yatim karena sudah tidak direferensikan oleh aplikasi.
     */
    public function down(): void
    {
        // Sengaja dikosongkan karena tabel yatim telah dihapus permanen.
    }
};
