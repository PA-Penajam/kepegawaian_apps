<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migration untuk membuat tabel state sinkronisasi Keycloak.
     */
    public function up(): void
    {
        Schema::create('keycloak_sync_state', function (Blueprint $table) {
            $table->id();
            $table->string('last_sync_at');
            $table->string('last_sync_type', 20);
            $table->integer('total_synced')->default(0);
            $table->integer('total_conflicts')->default(0);
            $table->json('sync_metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Rollback migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('keycloak_sync_state');
    }
};
