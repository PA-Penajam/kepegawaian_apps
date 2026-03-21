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
        Schema::create('iam_applications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('nama');
            $table->string('slug')->unique();
            $table->string('url');
            $table->text('deskripsi')->nullable();
            $table->string('api_key')->unique();
            $table->string('api_secret_hash');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('iam_roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('iam_application_id')
                ->constrained('iam_applications')
                ->cascadeOnDelete();
            $table->string('nama');
            $table->string('slug');
            $table->text('keterangan')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['iam_application_id', 'slug']);
        });

        Schema::create('iam_permissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('iam_application_id')
                ->constrained('iam_applications')
                ->cascadeOnDelete();
            $table->string('nama');
            $table->string('slug');
            $table->string('group')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['iam_application_id', 'slug']);
        });

        Schema::create('iam_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('iam_role_id')
                ->constrained('iam_roles')
                ->cascadeOnDelete();
            $table->foreignUlid('iam_permission_id')
                ->constrained('iam_permissions')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['iam_role_id', 'iam_permission_id']);
        });

        Schema::create('iam_user_roles', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 26);
            $table->foreign('user_id')
                ->references('id')->on('pegawai')
                ->cascadeOnDelete();
            $table->foreignUlid('iam_role_id')
                ->constrained('iam_roles')
                ->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->char('assigned_by', 26)->nullable();
            $table->foreign('assigned_by')
                ->references('id')->on('pegawai')
                ->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'iam_role_id']);
        });

        Schema::create('iam_sso_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->char('user_id', 26);
            $table->foreign('user_id')
                ->references('id')->on('pegawai')
                ->cascadeOnDelete();
            $table->string('app_slug');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iam_sso_codes');
        Schema::dropIfExists('iam_user_roles');
        Schema::dropIfExists('iam_role_permissions');
        Schema::dropIfExists('iam_permissions');
        Schema::dropIfExists('iam_roles');
        Schema::dropIfExists('iam_applications');
    }
};
