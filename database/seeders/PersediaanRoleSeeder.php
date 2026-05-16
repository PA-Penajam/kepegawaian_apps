<?php

namespace Database\Seeders;

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamUserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder Role & Permission untuk aplikasi SIMBARA (Persediaan ATK).
 *
 * Mengisi:
 *   - 1 IamApplication slug='persediaan' (dilewati jika sudah ada)
 *   - 12 permission slug `resource.action` sesuai pemakaian di
 *     middleware/policy/route SIMBARA.
 *   - 5 role: admin, operator, pimpinan, pegawai, auditor.
 *   - Pivot iam_role_permissions sesuai matrix wewenang.
 *
 * Idempoten: aman dijalankan berulang via firstOrCreate + syncWithoutDetaching.
 *
 * Menjalankan:
 *   php artisan db:seed --class=PersediaanRoleSeeder
 */
class PersediaanRoleSeeder extends Seeder
{
    /**
     * Slug aplikasi SIMBARA di IAM Hub.
     */
    private const APP_SLUG = 'persediaan';

    public function run(): void
    {
        $app = $this->ensureApplication();
        $permissions = $this->seedPermissions($app);
        $roles = $this->seedRoles($app);
        $this->attachPermissions($roles, $permissions);
        $this->assignDefaultUsers($roles);
    }

    /**
     * Pastikan aplikasi 'persediaan' terdaftar. Tidak menggenerate kredensial
     * di sini (kredensial sudah di-issue saat registrasi awal). Bila aplikasi
     * belum ada, generate kredensial baru lewat helper model.
     */
    private function ensureApplication(): IamApplication
    {
        $app = IamApplication::where('slug', self::APP_SLUG)->first();

        if ($app) {
            return $app;
        }

        // Aplikasi belum terdaftar — buat dengan kredensial baru.
        ['key' => $key, 'secret' => $secret, 'hash' => $hash] = IamApplication::generateApiCredentials();

        $app = IamApplication::create([
            'nama' => 'Persediaan ATK',
            'slug' => self::APP_SLUG,
            'url' => 'http://localhost:8000',
            'deskripsi' => 'SIMBARA - Sistem Informasi Manajemen Barang Persediaan ATK PA Penajam.',
            'is_active' => true,
        ]);

        // Field sensitif tidak fillable — set manual.
        $app->api_key = $key;
        $app->api_secret_hash = $hash;
        $app->is_system = false;
        $app->save();

        $this->command->warn("Aplikasi 'persediaan' baru saja dibuat. Salin kredensial berikut ke .env aplikasi SIMBARA:");
        $this->command->line("  IAM_API_KEY={$key}");
        $this->command->line("  IAM_API_SECRET={$secret}");
        $this->command->warn('  PENTING: Secret hanya ditampilkan sekali. Jika hilang, hapus aplikasi dan jalankan seeder ulang.');

        return $app;
    }

    /**
     * Seed 12 permission yang dipakai di middleware/policy SIMBARA.
     *
     * @return array<string, IamPermission> Indexed by slug.
     */
    private function seedPermissions(IamApplication $app): array
    {
        $defs = [
            // Master data
            ['slug' => 'barang.manage', 'group' => 'barang', 'nama' => 'Kelola Barang', 'keterangan' => 'Mengelola data barang/inventaris (lihat, tambah, edit, hapus, kategori, satuan, supplier).'],

            // Permintaan
            ['slug' => 'permintaan.create', 'group' => 'permintaan', 'nama' => 'Buat Permintaan', 'keterangan' => 'Membuat permintaan pengambilan barang.'],
            ['slug' => 'permintaan.approve', 'group' => 'permintaan', 'nama' => 'Setujui Permintaan', 'keterangan' => 'Menyetujui atau menolak permintaan barang.'],
            ['slug' => 'permintaan.process', 'group' => 'permintaan', 'nama' => 'Proses Permintaan', 'keterangan' => 'Memproses permintaan yang telah disetujui (serah terima barang).'],

            // Pembelian
            ['slug' => 'pembelian.create', 'group' => 'pembelian', 'nama' => 'Buat Pembelian', 'keterangan' => 'Membuat order pembelian ke supplier.'],
            ['slug' => 'pembelian.approve', 'group' => 'pembelian', 'nama' => 'Setujui Pembelian', 'keterangan' => 'Menyetujui order pembelian.'],
            ['slug' => 'pembelian.process', 'group' => 'pembelian', 'nama' => 'Proses Pembelian', 'keterangan' => 'Memproses penerimaan barang dari pembelian.'],

            // Barang masuk
            ['slug' => 'barangmasuk.manage', 'group' => 'barangmasuk', 'nama' => 'Kelola Barang Masuk', 'keterangan' => 'Mengelola penerimaan barang (manual/return).'],

            // Stockopname
            ['slug' => 'stockopname.manage', 'group' => 'stockopname', 'nama' => 'Kelola Stockopname', 'keterangan' => 'Melakukan stockopname dan adjustment stok.'],

            // Laporan & Log
            ['slug' => 'laporan.read', 'group' => 'laporan', 'nama' => 'Lihat Laporan', 'keterangan' => 'Melihat dan mengunduh laporan persediaan.'],
            ['slug' => 'log.read', 'group' => 'log', 'nama' => 'Lihat Log Audit', 'keterangan' => 'Melihat log aktivitas dan audit sistem.'],

            // Pengaturan
            ['slug' => 'setting.manage', 'group' => 'setting', 'nama' => 'Kelola Pengaturan', 'keterangan' => 'Mengelola pengaturan aplikasi (penandatangan, batas stok, dll).'],
        ];

        $map = [];
        foreach ($defs as $def) {
            $perm = $app->permissions()->firstOrCreate(
                ['slug' => $def['slug']],
                [
                    'nama' => $def['nama'],
                    'group' => $def['group'],
                    'keterangan' => $def['keterangan'],
                ],
            );
            $map[$def['slug']] = $perm;
        }

        return $map;
    }

    /**
     * Seed 5 role default untuk aplikasi persediaan.
     *
     * @return array<string, IamRole> Indexed by slug.
     */
    private function seedRoles(IamApplication $app): array
    {
        $defs = [
            ['slug' => 'admin', 'nama' => 'Admin', 'keterangan' => 'Akses penuh terhadap seluruh fitur SIMBARA.'],
            ['slug' => 'operator', 'nama' => 'Operator', 'keterangan' => 'Operator gudang dan pembelian: kelola permintaan, pembelian, barang masuk, stockopname.'],
            ['slug' => 'pimpinan', 'nama' => 'Pimpinan', 'keterangan' => 'Atasan/pejabat penyetuju permintaan dan pembelian.'],
            ['slug' => 'pegawai', 'nama' => 'Pegawai', 'keterangan' => 'Pegawai umum yang membuat permintaan ATK.'],
            ['slug' => 'auditor', 'nama' => 'Auditor', 'keterangan' => 'Tim TI/Inspektorat/BPK: read-only laporan dan log audit.'],
        ];

        $map = [];
        foreach ($defs as $def) {
            $role = IamRole::firstOrCreate(
                [
                    'iam_application_id' => $app->id,
                    'slug' => $def['slug'],
                ],
                [
                    'nama' => $def['nama'],
                    'keterangan' => $def['keterangan'],
                    'is_system' => true,
                ],
            );
            $map[$def['slug']] = $role;
        }

        return $map;
    }

    /**
     * Pasang permission ke role sesuai matrix wewenang.
     *
     * @param  array<string, IamRole>  $roles
     * @param  array<string, IamPermission>  $perms
     */
    private function attachPermissions(array $roles, array $perms): void
    {
        // Matrix: role slug => list of permission slug.
        $matrix = [
            // Admin: semua permission.
            'admin' => array_keys($perms),

            // Operator: kelola data + proses, TIDAK approve.
            'operator' => [
                'barang.manage',
                'permintaan.process',
                'pembelian.create',
                'pembelian.process',
                'barangmasuk.manage',
                'stockopname.manage',
                'laporan.read',
                'log.read',
            ],

            // Pimpinan: setujui + lihat laporan.
            'pimpinan' => [
                'permintaan.approve',
                'pembelian.approve',
                'laporan.read',
            ],

            // Pegawai: buat permintaan saja.
            'pegawai' => [
                'permintaan.create',
            ],

            // Auditor: read-only laporan + log.
            'auditor' => [
                'laporan.read',
                'log.read',
            ],
        ];

        foreach ($matrix as $roleSlug => $permSlugs) {
            $role = $roles[$roleSlug] ?? null;
            if (! $role) {
                continue;
            }

            $permIds = collect($permSlugs)
                ->map(fn (string $slug) => $perms[$slug]?->id)
                ->filter()
                ->values()
                ->all();

            // syncWithoutDetaching: tambah relasi baru tanpa hapus yang sudah ada.
            // Aman saat re-seed dan tidak menghapus assignment manual oleh admin.
            $role->permissions()->syncWithoutDetaching($permIds);
        }
    }

    /**
     * Assign default user ke role aplikasi persediaan.
     *
     * Menggunakan email sebagai matcher karena stabil & mudah dibaca.
     * Idempoten: pakai firstOrCreate pada pivot iam_user_roles.
     *
     * @param  array<string, IamRole>  $roles
     */
    private function assignDefaultUsers(array $roles): void
    {
        // Mapping default: email pegawai => slug role di aplikasi persediaan.
        // Tambahkan/ubah daftar di bawah bila ingin assignment berbeda.
        $assignments = [
            'admin@pa-penajam.go.id' => 'admin',
        ];

        foreach ($assignments as $email => $roleSlug) {
            $role = $roles[$roleSlug] ?? null;
            if (! $role) {
                $this->command->warn("Role '{$roleSlug}' tidak ditemukan, lewati.");

                continue;
            }

            $userId = DB::table('pegawai')
                ->where('email', $email)
                ->whereNull('deleted_at')
                ->value('id');

            if (! $userId) {
                $this->command->warn("Pegawai dengan email {$email} tidak ditemukan, lewati assignment.");

                continue;
            }

            IamUserRole::firstOrCreate(
                [
                    'user_id' => $userId,
                    'iam_role_id' => $role->id,
                ],
                [
                    'assigned_at' => now(),
                ],
            );

            $this->command->info("Assigned {$email} -> {$roleSlug} (app persediaan).");
        }
    }
}
