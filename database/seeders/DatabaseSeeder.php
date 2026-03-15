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
        ]);

        User::query()->updateOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'password' => 'password',
        ]);
    }
}
