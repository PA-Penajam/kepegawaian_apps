<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_consumers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('nama');
            $table->string('slug')->unique();
            $table->string('base_url')->nullable();
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_pull_at')->nullable();
            $table->string('last_pull_status')->nullable();
            $table->integer('last_pull_rows')->default(0);
            $table->timestamp('last_connection_test_at')->nullable();
            $table->string('last_connection_test_status')->nullable();
            $table->string('last_connection_test_message')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active']);
            $table->index('last_pull_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_consumers');
    }
};
