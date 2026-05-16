<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {}

    /**
     * Reverse the migrations.
     */
    public function down(): void {}

    private function addMySqlUniqueActiveIndex(): void
    {
        Schema::table('riwayat_pangkat', function ($table): void {
            $table->string('aktif_unique')->nullable()->virtualAs('case when is_aktif = 1 then pegawai_id else null end');
            $table->unique('aktif_unique', 'riwayat_pangkat_aktif_unique');
        });
    }

    private function dropMySqlUniqueActiveIndex(): void
    {
        Schema::table('riwayat_pangkat', function ($table): void {
            $table->dropUnique('riwayat_pangkat_aktif_unique');
            $table->dropColumn('aktif_unique');
        });
    }
};
