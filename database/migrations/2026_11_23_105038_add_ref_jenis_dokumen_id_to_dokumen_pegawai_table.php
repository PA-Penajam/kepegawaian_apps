<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dokumen_pegawai', function (Blueprint $table) {
            $table->foreignUlid('ref_jenis_dokumen_id')
                ->nullable()
                ->after('pegawai_id')
                ->constrained('ref_jenis_dokumen')
                ->nullOnDelete();

            $table->index(['pegawai_id', 'ref_jenis_dokumen_id'], 'idx_dokumen_pegawai_jenis');
        });

        // Backfill relasi ref_jenis_dokumen_id untuk baris existing yang namanya cocok
        $jenisMap = DB::table('ref_jenis_dokumen')
            ->whereNull('deleted_at')
            ->pluck('id', 'nama')
            ->mapWithKeys(fn (string $id, string $nama): array => [
                strtolower(trim(str_replace('_', ' ', $nama))) => $id,
            ]);

        if ($jenisMap->isNotEmpty()) {
            DB::table('dokumen_pegawai')
                ->whereNull('ref_jenis_dokumen_id')
                ->select(['id', 'jenis_dokumen'])
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($jenisMap): void {
                    foreach ($rows as $row) {
                        $normalized = strtolower(trim(str_replace('_', ' ', (string) $row->jenis_dokumen)));
                        $matchedId = $jenisMap->get($normalized);

                        if ($matchedId !== null) {
                            DB::table('dokumen_pegawai')
                                ->where('id', $row->id)
                                ->update(['ref_jenis_dokumen_id' => $matchedId]);
                        }
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dokumen_pegawai', function (Blueprint $table) {
            $table->dropIndex('idx_dokumen_pegawai_jenis');
            $table->dropConstrainedForeignId('ref_jenis_dokumen_id');
        });
    }
};
