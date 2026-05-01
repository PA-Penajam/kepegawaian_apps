<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuti_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('aggregate_type', 50);
            $table->ulid('aggregate_id');
            $table->string('event_type', 100);
            $table->json('payload');
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['aggregate_type', 'aggregate_id'], 'idx_aggregate');
            $table->index('event_type');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuti_events');
    }
};
