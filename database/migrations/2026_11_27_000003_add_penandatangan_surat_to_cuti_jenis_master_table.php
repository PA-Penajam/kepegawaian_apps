<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuti_jenis_master', function (Blueprint $table): void {
            $table->string('penandatangan_surat', 20)->default('ketua')->after('boleh_dicabut_setelah_disetujui');
        });
    }

    public function down(): void
    {
        Schema::table('cuti_jenis_master', function (Blueprint $table): void {
            $table->dropColumn('penandatangan_surat');
        });
    }
};
