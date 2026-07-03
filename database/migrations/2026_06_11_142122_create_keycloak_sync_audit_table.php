<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migration untuk membuat tabel audit sinkronisasi Keycloak.
     */
    public function up(): void
    {
        Schema::create('keycloak_sync_audit', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 50);
            $table->foreignUlid('pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->string('nip', 18)->index();
            $table->string('conflict_type', 50)->nullable();
            $table->json('pegawai_snapshot')->nullable();
            $table->json('keycloak_snapshot')->nullable();
            $table->json('resolution')->nullable();
            $table->string('resolved_by', 50);
            $table->char('caused_by', 26)->nullable();
            $table->foreign('caused_by')->references('id')->on('pegawai')->nullOnDelete();
            $table->string('caused_by_nip', 18)->nullable();
            $table->timestamps();

            // Composite indexes untuk query filtering yang efisien
            $table->index(['event_type', 'created_at']);
            $table->index(['nip', 'created_at']);
        });
    }

    /**
     * Rollback migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('keycloak_sync_audit');
    }
};
