<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom auth ke pegawai
        Schema::table('pegawai', function (Blueprint $table) {
            $table->string('password')->nullable()->after('keterangan');
            $table->rememberToken()->after('password');
            $table->text('two_factor_secret')->nullable()->after('remember_token');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });

        // 2. Buat pivot pegawai_role
        Schema::create('pegawai_role', function (Blueprint $table) {
            $table->char('pegawai_id', 26);
            $table->char('ref_role_id', 26);
            $table->timestamp('created_at')->nullable();

            $table->primary(['pegawai_id', 'ref_role_id']);
            $table->foreign('pegawai_id')->references('id')->on('pegawai')->cascadeOnDelete();
            $table->foreign('ref_role_id')->references('id')->on('ref_roles')->cascadeOnDelete();
        });

        // 3. Migrasi data dari users ke pegawai
        if (Schema::hasTable('users')) {
            $users = DB::table('users')->get();

            foreach ($users as $user) {
                if ($user->pegawai_id) {
                    // User sudah punya pegawai → copy auth data
                    DB::table('pegawai')->where('id', $user->pegawai_id)->update([
                        'password' => $user->password,
                        'remember_token' => $user->remember_token,
                        'two_factor_secret' => $user->two_factor_secret ?? null,
                        'two_factor_recovery_codes' => $user->two_factor_recovery_codes ?? null,
                        'two_factor_confirmed_at' => $user->two_factor_confirmed_at ?? null,
                        'email_verified_at' => $user->email_verified_at ?? null,
                    ]);

                    if ($user->ref_role_id) {
                        DB::table('pegawai_role')->insertOrIgnore([
                            'pegawai_id' => $user->pegawai_id,
                            'ref_role_id' => $user->ref_role_id,
                            'created_at' => now(),
                        ]);
                    }
                } else {
                    // User tanpa pegawai → buat pegawai baru
                    $pegawaiId = (string) Str::ulid();

                    DB::table('pegawai')->insert([
                        'id' => $pegawaiId,
                        'nama_lengkap' => $user->name,
                        'tempat_lahir' => 'Tidak Diketahui',
                        'tanggal_lahir' => '1970-01-01',
                        'jenis_kelamin' => 'Laki-Laki',
                        'agama' => 'Islam',
                        'status_perkawinan' => 'Belum_Kawin',
                        'status_kepegawaian' => 'PNS',
                        'status_pegawai' => 'Aktif',
                        'tanggal_masuk' => now()->toDateString(),
                        'email' => $user->email,
                        'email_verified_at' => $user->email_verified_at,
                        'nip' => 'TEMP-' . $user->id,
                        'password' => $user->password,
                        'remember_token' => $user->remember_token,
                        'two_factor_secret' => $user->two_factor_secret ?? null,
                        'two_factor_recovery_codes' => $user->two_factor_recovery_codes ?? null,
                        'two_factor_confirmed_at' => $user->two_factor_confirmed_at ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    if ($user->ref_role_id) {
                        DB::table('pegawai_role')->insertOrIgnore([
                            'pegawai_id' => $pegawaiId,
                            'ref_role_id' => $user->ref_role_id,
                            'created_at' => now(),
                        ]);
                    }
                }
            }

            // 4. Update sessions table — ubah user_id ke varchar untuk ULID
            if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
                Schema::table('sessions', function (Blueprint $table) {
                    $table->string('user_id', 26)->nullable()->change();
                });
            }

            // 5. Hapus tabel users
            Schema::dropIfExists('users');
        }
    }

    public function down(): void
    {
        // Recreate users table
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('pegawai_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->char('ref_role_id', 26)->nullable();
            $table->timestamps();

            $table->foreign('pegawai_id')->references('id')->on('pegawai')->nullOnDelete();
            $table->foreign('ref_role_id')->references('id')->on('ref_roles')->nullOnDelete();
        });

        Schema::dropIfExists('pegawai_role');

        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropColumn([
                'password', 'remember_token',
                'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
                'email_verified_at',
            ]);
        });
    }
};
