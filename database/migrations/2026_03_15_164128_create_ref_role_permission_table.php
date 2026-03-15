<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ref_role_permission', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('ref_role_id')->constrained('ref_roles')->cascadeOnDelete();
            $table->foreignUlid('ref_permission_id')->constrained('ref_permissions')->cascadeOnDelete();
            $table->unique(['ref_role_id', 'ref_permission_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_role_permission');
    }
};
