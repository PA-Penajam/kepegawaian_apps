<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usulan_kp_approval_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('usulan_kenaikan_pangkat_id')
                ->constrained('usulan_kenaikan_pangkat')->cascadeOnDelete();
            $table->unsignedTinyInteger('urutan');
            $table->string('role_required', 50);
            $table->foreignUlid('approver_user_id')->nullable()
                ->constrained('pegawai')->nullOnDelete();
            $table->string('status', 30)->default('menunggu');
            $table->text('catatan')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->unique(['usulan_kenaikan_pangkat_id', 'urutan'], 'unique_step_urutan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usulan_kp_approval_steps');
    }
};
