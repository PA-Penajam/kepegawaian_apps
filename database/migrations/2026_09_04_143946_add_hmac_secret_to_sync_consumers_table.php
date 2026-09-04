<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_consumers', function (Blueprint $table) {
            // Secret HMAC unik per konsumen, disimpan terenkripsi (cast
            // 'encrypted' pada model). Nullable agar baris lawas tetap
            // berjalan dengan fallback secret global selama masa transisi.
            $table->text('hmac_secret')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sync_consumers', function (Blueprint $table) {
            $table->dropColumn('hmac_secret');
        });
    }
};
