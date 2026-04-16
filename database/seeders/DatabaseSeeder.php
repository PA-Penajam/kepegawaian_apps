<?php

namespace Database\Seeders;

use App\Enums\Agama;
use App\Enums\JenisKelamin;
use App\Enums\StatusKepegawaian;
use App\Enums\StatusPegawai;
use App\Enums\StatusPerkawinan;
use App\Models\IamApplication;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\Pegawai;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

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
            IamSeeder::class,
        ]);

        // Get IAM application for kepegawaian
        $kepegawaian = IamApplication::where('slug', 'kepegawaian')->first();

        if (! $kepegawaian) {
            $this->command->warn('IAM application kepegawaian tidak ditemukan. Lewati pembuatan user.');

            return;
        }

        // Buat admin pegawai (pranata komputer)
        $admin = Pegawai::query()->updateOrCreate([
            'nip' => '199107132020121003',
        ], [
            'nama_lengkap' => 'Pranata Komputer',
            'tempat_lahir' => 'Penajam',
            'tanggal_lahir' => '1991-07-13',
            'jenis_kelamin' => JenisKelamin::LakiLaki,
            'agama' => Agama::Islam,
            'status_perkawinan' => StatusPerkawinan::Kawin,
            'status_kepegawaian' => StatusKepegawaian::PNS,
            'status_pegawai' => StatusPegawai::Aktif,
            'tanggal_masuk' => '2020-12-01',
            'email' => 'admin@pa-penajam.go.id',
            'email_verified_at' => now(),
            'password' => env('SEEDER_ADMIN_PASSWORD', 'admin123'),
        ]);

        $adminRole = IamRole::where('iam_application_id', $kepegawaian->id)->where('slug', 'admin')->first();
        if ($adminRole) {
            IamUserRole::firstOrCreate(
                ['user_id' => $admin->id, 'iam_role_id' => $adminRole->id],
                ['assigned_at' => now()]
            );
        }

        // Buat operator pegawai
        $operator = Pegawai::query()->updateOrCreate([
            'nip' => '199201012021011001',
        ], [
            'nama_lengkap' => 'Operator',
            'tempat_lahir' => 'Penajam',
            'tanggal_lahir' => '1992-01-01',
            'jenis_kelamin' => JenisKelamin::Perempuan,
            'agama' => Agama::Islam,
            'status_perkawinan' => StatusPerkawinan::Kawin,
            'status_kepegawaian' => StatusKepegawaian::PNS,
            'status_pegawai' => StatusPegawai::Aktif,
            'tanggal_masuk' => '2021-01-01',
            'email' => 'operator@pa-penajam.go.id',
            'email_verified_at' => now(),
            'password' => env('SEEDER_OPERATOR_PASSWORD', 'operator123'),
        ]);

        $operatorRole = IamRole::where('iam_application_id', $kepegawaian->id)->where('slug', 'operator')->first();
        if ($operatorRole) {
            IamUserRole::firstOrCreate(
                ['user_id' => $operator->id, 'iam_role_id' => $operatorRole->id],
                ['assigned_at' => now()]
            );
        }

        $this->command->info('Pegawai admin dan operator berhasil dibuat dengan IAM roles.');
    }
}
