<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usulan_kp_state_history', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('usulan_kenaikan_pangkat_id')
                ->constrained('usulan_kenaikan_pangkat')->cascadeOnDelete();
            $table->string('from_state', 50)->nullable();
            $table->string('to_state', 50);
            $table->foreignUlid('transitioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['usulan_kenaikan_pangkat_id', 'created_at'], 'idx_state_hist_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usulan_kp_state_history');
    }
};
