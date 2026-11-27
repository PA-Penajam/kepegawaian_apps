<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_inbound_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('source', 50)->default('sisukma');
            $table->uuid('event_id')->unique();
            $table->string('event_type', 100)->index();
            $table->json('payload');
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_inbound_events');
    }
};
