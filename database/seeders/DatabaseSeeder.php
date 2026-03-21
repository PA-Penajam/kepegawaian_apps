<?php

namespace Database\Seeders;

use App\Models\IamApplication;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
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
            $this->command->warn('IAM application kepegawaian not found. Skipping user role assignment.');

            return;
        }

        // Create admin user - Model User sudah punya cast 'hashed', jadi jangan bcrypt()
        $admin = User::query()->updateOrCreate([
            'email' => 'admin@pa-penajam.go.id',
        ], [
            'name' => 'Administrator',
            'password' => env('SEEDER_ADMIN_PASSWORD', 'admin123'), // fallback HANYA untuk local
            'email_verified_at' => now(),
        ]);

        $adminRole = IamRole::where('iam_application_id', $kepegawaian->id)->where('slug', 'admin')->first();
        if ($adminRole) {
            IamUserRole::firstOrCreate(
                ['user_id' => $admin->id, 'iam_role_id' => $adminRole->id],
                ['assigned_at' => now()]
            );
        }

        // Create operator user - Model User sudah punya cast 'hashed'
        $operator = User::query()->updateOrCreate([
            'email' => 'operator@pa-penajam.go.id',
        ], [
            'name' => 'Operator',
            'password' => env('SEEDER_OPERATOR_PASSWORD', 'operator123'), // fallback HANYA untuk local
            'email_verified_at' => now(),
        ]);

        $operatorRole = IamRole::where('iam_application_id', $kepegawaian->id)->where('slug', 'operator')->first();
        if ($operatorRole) {
            IamUserRole::firstOrCreate(
                ['user_id' => $operator->id, 'iam_role_id' => $operatorRole->id],
                ['assigned_at' => now()]
            );
        }

        $this->command->info('Created admin and operator users with IAM roles.');
    }
}
