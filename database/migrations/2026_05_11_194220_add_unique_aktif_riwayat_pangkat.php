<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        match (DB::connection()->getDriverName()) {
            'sqlite' => DB::statement('CREATE UNIQUE INDEX riwayat_pangkat_aktif_unique ON riwayat_pangkat(pegawai_id) WHERE is_aktif = 1'),
            'mysql' => $this->addMySqlUniqueActiveIndex(),
            default => DB::statement('CREATE UNIQUE INDEX riwayat_pangkat_aktif_unique ON riwayat_pangkat(pegawai_id) WHERE is_aktif = true'),
        };
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        match (DB::connection()->getDriverName()) {
            'sqlite' => DB::statement('DROP INDEX IF EXISTS riwayat_pangkat_aktif_unique'),
            'mysql' => $this->dropMySqlUniqueActiveIndex(),
            default => DB::statement('DROP INDEX IF EXISTS riwayat_pangkat_aktif_unique'),
        };
    }

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
