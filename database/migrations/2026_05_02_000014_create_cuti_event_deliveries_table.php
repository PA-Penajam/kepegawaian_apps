<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuti_event_deliveries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->uuid('event_id');
            $table->string('consumer_id', 50);
            $table->enum('status', ['pending', 'in_flight', 'delivered', 'failed', 'dead_letter'])->default('pending');
            $table->integer('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'consumer_id'], 'uk_event_consumer');
            $table->index(['status', 'next_retry_at'], 'idx_status_retry');
            $table->foreign('event_id')->references('id')->on('cuti_events');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuti_event_deliveries');
    }
};
