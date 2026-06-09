<?php

namespace Database\Seeders;

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class AttendanceAppSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedApplication();
        $this->seedRolesAndPermissions();
    }

    /**
     * Registrasi aplikasi attendance di tabel IamApplication.
     * Slug "attendance" digunakan untuk SSO login (?app=attendance)
     * dan HMAC signature (X-App-Key=attendance).
     */
    private function seedApplication(): void
    {
        $slug = 'attendance';

        $app = IamApplication::where('slug', $slug)->first();

        if ($app) {
            $this->command->info("IAM Application '{$slug}' sudah ada (id: {$app->id}).");

            return;
        }

        $secret = Str::random(64);

        $app = new IamApplication;
        $app->nama = 'Attendance System';
        $app->slug = $slug;
        $app->url = env('ATTENDANCE_APP_URL', 'http://localhost:3000');
        $app->deskripsi = 'Sistem absensi QR code PA Penajam';
        $app->is_active = true;
        $app->is_system = false;
        // Samakan api_key dengan slug agar attendance cukup satu identifier
        // untuk SSO login (?app=slug) dan HMAC signature (X-App-Key=api_key)
        $app->api_key = $slug;
        $app->api_secret_hash = Crypt::encryptString($secret);
        $app->save();

        $this->command->info("IAM Application '{$slug}' berhasil dibuat.");
        $this->command->newLine();
        $this->command->warn('╔══════════════════════════════════════════════════════════════════════════╗');
        $this->command->warn('║  SIMPAN KREDENSIAL BERIKUT (hanya ditampilkan sekali!)                  ║');
        $this->command->warn('║  Konfigurasi ini diperlukan di .env attendance-system                   ║');
        $this->command->warn('╠══════════════════════════════════════════════════════════════════════════╣');
        $this->command->warn("║  SSO_APP_KEY={$slug}");
        $this->command->warn("║  SSO_APP_SECRET={$secret}");
        $this->command->warn('╚══════════════════════════════════════════════════════════════════════════╝');
        $this->command->newLine();
    }

    /**
     * Seed roles dan permissions untuk aplikasi attendance.
     * Role: pegawai, admin, pimpinan (sesuai spec SSO integration).
     */
    private function seedRolesAndPermissions(): void
    {
        $app = IamApplication::where('slug', 'attendance')->firstOrFail();

        // Roles untuk attendance sesuai spec SSO integration
        $roles = [
            ['slug' => 'pegawai', 'nama' => 'Pegawai', 'is_system' => true],
            ['slug' => 'admin', 'nama' => 'Admin', 'is_system' => true],
            ['slug' => 'pimpinan', 'nama' => 'Pimpinan', 'is_system' => true],
        ];

        foreach ($roles as $roleData) {
            IamRole::firstOrCreate(
                ['iam_application_id' => $app->id, 'slug' => $roleData['slug']],
                ['nama' => $roleData['nama'], 'is_system' => $roleData['is_system']]
            );
        }

        // Permissions untuk attendance
        $permissions = [
            ['slug' => 'attendance.view', 'nama' => 'Lihat Absensi', 'group' => 'attendance'],
            ['slug' => 'attendance.manage', 'nama' => 'Kelola Absensi', 'group' => 'attendance'],
            ['slug' => 'users.view', 'nama' => 'Lihat Users', 'group' => 'users'],
            ['slug' => 'users.manage', 'nama' => 'Kelola Users', 'group' => 'users'],
            ['slug' => 'reports.view', 'nama' => 'Lihat Laporan', 'group' => 'reports'],
            ['slug' => 'reports.generate', 'nama' => 'Generate Laporan', 'group' => 'reports'],
            ['slug' => 'settings.manage', 'nama' => 'Kelola Pengaturan', 'group' => 'settings'],
        ];

        $permissionIds = [];
        foreach ($permissions as $perm) {
            $p = IamPermission::firstOrCreate(
                ['iam_application_id' => $app->id, 'slug' => $perm['slug']],
                ['nama' => $perm['nama'], 'group' => $perm['group']]
            );
            $permissionIds[$perm['slug']] = $p->id;
        }

        // Admin mendapat semua permission
        $adminRole = IamRole::where('iam_application_id', $app->id)->where('slug', 'admin')->first();
        if ($adminRole) {
            $adminRole->permissions()->syncWithoutDetaching(array_values($permissionIds));
        }

        // Pimpinan mendapat view dan reports
        $pimpinanRole = IamRole::where('iam_application_id', $app->id)->where('slug', 'pimpinan')->first();
        if ($pimpinanRole) {
            $pimpinanRole->permissions()->syncWithoutDetaching([
                $permissionIds['attendance.view'],
                $permissionIds['users.view'],
                $permissionIds['reports.view'],
                $permissionIds['reports.generate'],
            ]);
        }

        // Pegawai mendapat attendance.view saja
        $pegawaiRole = IamRole::where('iam_application_id', $app->id)->where('slug', 'pegawai')->first();
        if ($pegawaiRole) {
            $pegawaiRole->permissions()->syncWithoutDetaching([
                $permissionIds['attendance.view'],
            ]);
        }

        $this->command->info("Roles & permissions untuk 'attendance' selesai di-seed.");
    }
}
