<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usulan_kp_approver_history', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('usulan_kenaikan_pangkat_id')
                ->constrained('usulan_kenaikan_pangkat')->cascadeOnDelete();
            $table->unsignedTinyInteger('step_urutan');
            $table->foreignUlid('user_id')->constrained('pegawai')->restrictOnDelete();
            $table->string('action', 30);
            $table->text('catatan')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['usulan_kenaikan_pangkat_id', 'created_at'], 'idx_approver_hist_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usulan_kp_approver_history');
    }
};
