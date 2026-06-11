<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migration untuk membuat tabel log emergency login Keycloak.
     */
    public function up(): void
    {
        Schema::create('keycloak_emergency_login_log', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 26)->nullable();
            $table->foreign('user_id')->references('id')->on('pegawai')->nullOnDelete();
            $table->string('username');
            $table->string('ip_address', 45);
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('logged_in_at');
            $table->timestamp('logged_out_at')->nullable();
            $table->timestamps();

            // Index untuk query berdasarkan waktu login
            $table->index('logged_in_at');
        });
    }

    /**
     * Rollback migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('keycloak_emergency_login_log');
    }
};
