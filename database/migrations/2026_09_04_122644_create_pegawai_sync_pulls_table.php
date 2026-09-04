<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pegawai_sync_pulls', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('sync_consumer_id')->nullable();
            $table->string('status')->default('success');
            $table->unsignedInteger('rows_returned')->default(0);
            $table->unsignedInteger('page')->default(1);
            $table->unsignedInteger('per_page')->default(100);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('token_name')->nullable();
            $table->string('client_agent')->nullable();
            $table->timestamp('pulled_at')->useCurrent();
            $table->timestamps();

            $table->foreign('sync_consumer_id')
                ->references('id')
                ->on('sync_consumers')
                ->nullOnDelete();
            $table->index(['sync_consumer_id', 'pulled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pegawai_sync_pulls');
    }
};
