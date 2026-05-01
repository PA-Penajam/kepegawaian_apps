<?php

namespace Database\Seeders;

use App\Models\IamApplication;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\Pegawai;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Guard: di production, password WAJIB dari env variable
        if (App::isProduction()) {
            if (empty(env('SEEDER_ADMIN_PASSWORD')) || empty(env('SEEDER_OPERATOR_PASSWORD'))) {
                throw new \RuntimeException('SEEDER_ADMIN_PASSWORD dan SEEDER_OPERATOR_PASSWORD wajib diset di environment production');
            }
        }

        $this->call([
            RefPangkatSeeder::class,
            RefJabatanSeeder::class,
            RefUnitKerjaSeeder::class,
            RefJenisDiklatSeeder::class,
            RefJenisPenghargaanSeeder::class,
            RefJenisHukumanDisiplinSeeder::class,
            RefJenisDokumenSeeder::class,
            RefStatusKepegawaianSeeder::class,
            RefStatusPegawaiSeeder::class,
            // Seed data pegawai dari JSON terlebih dahulu sebagai sumber data utama
            PegawaiSeeder::class,
            IamSeeder::class,
            CutiJenisMasterSeeder::class,
            CutiJenisPerStatusPegawaiSeeder::class,
            CutiPermissionSeeder::class,
        ]);

        // --- Assign password & IAM role ke pegawai berdasarkan data JSON ---

        $kepegawaian = IamApplication::where('slug', 'kepegawaian')->first();

        if (! $kepegawaian) {
            $this->command->warn('IAM application kepegawaian tidak ditemukan. Lewati assignment role.');

            return;
        }

        // NIP admin: Pranata Komputer (IT) dari data JSON
        $nipAdmin = '199107132020121003';

        // NIP operator: Kasubbag Kepegawaian dari data JSON
        $nipOperator = '198411192011011012';

        // Gunakan DB::table() langsung untuk bypass cast 'hashed' pada model
        // agar password di-hash sekali saja dengan benar
        $this->assignCredentialsAndRole(
            nip: $nipAdmin,
            email: 'admin@pa-penajam.go.id',
            password: env('SEEDER_ADMIN_PASSWORD', 'admin123'),
            kepegawaianId: $kepegawaian->id,
            roleSlug: 'admin',
        );

        $this->assignCredentialsAndRole(
            nip: $nipOperator,
            email: 'operator@pa-penajam.go.id',
            password: env('SEEDER_OPERATOR_PASSWORD', 'operator123'),
            kepegawaianId: $kepegawaian->id,
            roleSlug: 'operator',
        );

        $this->command->info('Seeding selesai. Total pegawai: '.Pegawai::query()->count());
    }

    /**
     * Set email, password, dan IAM role ke pegawai berdasarkan NIP.
     * Password di-hash via DB::table() langsung untuk menghindari double-hashing
     * dari cast 'hashed' pada model Pegawai.
     */
    private function assignCredentialsAndRole(
        string $nip,
        string $email,
        string $password,
        string $kepegawaianId,
        string $roleSlug,
    ): void {
        $pegawai = Pegawai::withoutGlobalScopes()->where('nip', $nip)->first();

        if (! $pegawai) {
            $this->command->warn("NIP {$nip} tidak ditemukan di data JSON.");

            return;
        }

        // Update email dan password menggunakan DB::table() untuk bypass cast model
        DB::table('pegawai')->where('id', $pegawai->id)->update([
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make($password),
        ]);

        // Assign IAM role
        $role = IamRole::where('iam_application_id', $kepegawaianId)
            ->where('slug', $roleSlug)->first();

        if ($role) {
            IamUserRole::firstOrCreate(
                ['user_id' => $pegawai->id, 'iam_role_id' => $role->id],
                ['assigned_at' => now()]
            );
            $this->command->info("{$pegawai->nama_lengkap} ({$nip}) → role {$roleSlug}");
        }
    }
}
