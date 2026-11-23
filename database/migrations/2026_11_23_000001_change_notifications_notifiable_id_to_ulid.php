<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mengubah kolom notifiable_id menjadi char(26) agar kompatibel dengan primary key ULID Pegawai.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_notifiable_type_notifiable_id_index');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->char('notifiable_id', 26)->change();
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->index(['notifiable_type', 'notifiable_id'], 'notifications_notifiable_type_notifiable_id_index');
        });
    }

    /**
     * Mengembalikan kolom notifiable_id ke tipe unsignedBigInteger.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_notifiable_type_notifiable_id_index');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->unsignedBigInteger('notifiable_id')->change();
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->index(['notifiable_type', 'notifiable_id'], 'notifications_notifiable_type_notifiable_id_index');
        });
    }
};
