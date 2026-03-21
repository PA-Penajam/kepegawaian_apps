<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('iam_sso_codes', function (Blueprint $table) {
            $table->index('app_slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('iam_sso_codes', function (Blueprint $table) {
            $table->dropIndex(['app_slug']);
        });
    }
};
