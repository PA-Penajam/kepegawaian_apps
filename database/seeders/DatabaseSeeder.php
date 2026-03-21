<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
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
            RefRoleSeeder::class,
            RefPermissionSeeder::class,
            PegawaiSeeder::class,
            IamSeeder::class,
        ]);

        // Create admin user
        User::query()->updateOrCreate([
            'email' => 'admin@pa-penajam.go.id',
        ], [
            'name' => 'Administrator',
            'password' => bcrypt('admin123'),
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);

        // Create operator user
        User::query()->updateOrCreate([
            'email' => 'operator@pa-penajam.go.id',
        ], [
            'name' => 'Operator',
            'password' => bcrypt('operator123'),
            'email_verified_at' => now(),
            'role' => 'operator',
        ]);

        $this->command->info('Created admin and operator users.');
    }
}
