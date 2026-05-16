<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. (rollback of unique index)
     */
    public function up(): void {}

    /**
     * Reverse the rollback (re-create unique partial index).
     */
    public function down(): void {}

    private function addMySqlUniqueActiveIndex(): void
    {
        Schema::table('riwayat_pangkat', function ($table): void {
            $table->string('aktif_unique')->nullable()->storedAs('case when is_aktif = 1 then pegawai_id else null end');
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
