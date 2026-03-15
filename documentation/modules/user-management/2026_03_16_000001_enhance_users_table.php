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
        Schema::table('users', function (Blueprint $table) {
            // Status & Lifecycle
            $table->boolean('is_active')
                ->default(true)
                ->after('email_verified_at')
                ->comment('Status aktif/non-aktif user');

            $table->timestamp('last_login_at')
                ->nullable()
                ->after('remember_token')
                ->comment('Timestamp login terakhir');

            $table->string('last_login_ip', 45)
                ->nullable()
                ->after('last_login_at')
                ->comment('IP address login terakhir');

            $table->boolean('must_change_password')
                ->default(false)
                ->after('is_active')
                ->comment('Flag force password change');

            $table->timestamp('password_changed_at')
                ->nullable()
                ->after('must_change_password')
                ->comment('Timestamp terakhir ubah password');

            // Audit Trail
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->after('remember_token')
                ->comment('User yang membuat');

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->after('created_by')
                ->comment('User terakhir update');

            // Constraint
            $table->unique('pegawai_id', 'users_pegawai_id_unique')
                ->whereNotNull('pegawai_id')
                ->comment('1 pegawai hanya boleh punya 1 user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_pegawai_id_unique');
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn([
                'is_active',
                'last_login_at',
                'last_login_ip',
                'must_change_password',
                'password_changed_at',
                'created_by',
                'updated_by',
            ]);
        });
    }
};
