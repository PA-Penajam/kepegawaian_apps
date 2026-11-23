<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usulan_kenaikan_pangkat', function (Blueprint $table): void {
            $table->date('tmt_pangkat_baru')->nullable()->after('tanggal_sk');
            $table->unsignedInteger('masa_kerja_tahun')->nullable()->after('tmt_pangkat_baru');
            $table->unsignedTinyInteger('masa_kerja_bulan')->nullable()->after('masa_kerja_tahun');
        });
    }

    public function down(): void
    {
        Schema::table('usulan_kenaikan_pangkat', function (Blueprint $table): void {
            $table->dropColumn([
                'tmt_pangkat_baru',
                'masa_kerja_tahun',
                'masa_kerja_bulan',
            ]);
        });
    }
};
