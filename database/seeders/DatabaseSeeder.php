<?php

namespace Database\Seeders;

use App\Models\Pegawai;
use App\Models\RefRole;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
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
        ]);

        $adminRole = RefRole::query()->where('nama', 'Admin')->first();
        $operatorRole = RefRole::query()->where('nama', 'Operator')->first();

        // Assign admin ke pranata komputer (NIP: 199107132020121003)
        $admin = Pegawai::query()->where('nip', '199107132020121003')->first();

        if (! $admin) {
            $admin = Pegawai::query()->updateOrCreate([
                'nip' => '199107132020121003',
            ], [
                'nama_lengkap' => 'Pranata Komputer',
                'tempat_lahir' => 'Penajam',
                'tanggal_lahir' => '1991-07-13',
                'jenis_kelamin' => 'Laki-Laki',
                'agama' => 'Islam',
                'status_perkawinan' => 'Kawin',
                'status_kepegawaian' => 'PNS',
                'status_pegawai' => 'Aktif',
                'tanggal_masuk' => '2020-12-01',
                'email' => 'admin@pa-penajam.go.id',
            ]);
        }

        $admin->update([
            'email_verified_at' => now(),
            'password' => bcrypt('admin123'),
        ]);

        if ($adminRole) {
            $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        }

        // Buat pegawai operator
        $operator = Pegawai::query()->updateOrCreate([
            'nip' => '199201012021011001',
        ], [
            'nama_lengkap' => 'Operator',
            'tempat_lahir' => 'Penajam',
            'tanggal_lahir' => '1992-01-01',
            'jenis_kelamin' => 'Perempuan',
            'agama' => 'Islam',
            'status_perkawinan' => 'Kawin',
            'status_kepegawaian' => 'PNS',
            'status_pegawai' => 'Aktif',
            'tanggal_masuk' => '2021-01-01',
            'email' => 'operator@pa-penajam.go.id',
            'email_verified_at' => now(),
            'password' => bcrypt('operator123'),
        ]);

        if ($operatorRole) {
            $operator->roles()->syncWithoutDetaching([$operatorRole->id]);
        }

        $this->command->info('Pegawai admin dan operator berhasil dibuat.');
    }
}
