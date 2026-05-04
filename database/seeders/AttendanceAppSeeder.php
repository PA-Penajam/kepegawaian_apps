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
        $slug = 'attendance-qr-system';

        $app = IamApplication::where('slug', $slug)->first();

        if ($app) {
            $this->command->info("IAM Application '{$slug}' sudah ada (id: {$app->id}).");
        } else {
            $secret = Str::random(64);

            $app = new IamApplication();
            $app->nama = 'Attendance QR System';
            $app->slug = $slug;
            $app->url = env('ATTENDANCE_APP_URL', 'http://localhost:8001');
            $app->deskripsi = 'Sistem absensi berbasis QR code PA Penajam';
            $app->is_active = true;
            $app->is_system = false;
            // Samakan api_key dengan slug agar attendance cukup satu IAM_CLIENT_ID
            // untuk SSO login (?app=slug) dan HMAC signature (X-App-Key=api_key)
            $app->api_key = $slug;
            $app->api_secret_hash = Crypt::encryptString($secret);
            $app->save();

            $this->command->info("IAM Application '{$slug}' berhasil dibuat.");
            $this->command->newLine();
            $this->command->warn('╔══════════════════════════════════════════════════════════════════╗');
            $this->command->warn('║  SIMPAN KREDENSIAL BERIKUT (hanya ditampilkan sekali!)          ║');
            $this->command->warn('╠══════════════════════════════════════════════════════════════════╣');
            $this->command->warn("║  IAM_CLIENT_ID={$slug}");
            $this->command->warn("║  IAM_CLIENT_SECRET={$secret}");
            $this->command->warn("║  IAM_HMAC_SECRET={$secret}");
            $this->command->warn('╚══════════════════════════════════════════════════════════════════╝');
            $this->command->newLine();
        }

        // Roles untuk attendance
        $roles = [
            ['slug' => 'admin', 'nama' => 'Admin', 'is_system' => true],
            ['slug' => 'operator', 'nama' => 'Operator', 'is_system' => true],
            ['slug' => 'user', 'nama' => 'User', 'is_system' => true],
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

        // Operator mendapat attendance.* dan users.view dan reports.*
        $operatorRole = IamRole::where('iam_application_id', $app->id)->where('slug', 'operator')->first();
        if ($operatorRole) {
            $operatorRole->permissions()->syncWithoutDetaching([
                $permissionIds['attendance.view'],
                $permissionIds['attendance.manage'],
                $permissionIds['users.view'],
                $permissionIds['reports.view'],
                $permissionIds['reports.generate'],
            ]);
        }

        // User mendapat attendance.view saja
        $userRole = IamRole::where('iam_application_id', $app->id)->where('slug', 'user')->first();
        if ($userRole) {
            $userRole->permissions()->syncWithoutDetaching([
                $permissionIds['attendance.view'],
            ]);
        }

        $this->command->info("Roles & permissions untuk '{$slug}' selesai di-seed.");
    }
}
