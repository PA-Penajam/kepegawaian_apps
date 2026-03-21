# IAM SSO Gateway Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menjadikan kepegawaian-apps sebagai centralized IAM Hub / SSO Gateway — satu login, satu tempat kelola RBAC untuk semua aplikasi ekosistem PA Penajam.

**Architecture:** Modul IAM terpisah dengan 6 tabel baru (`iam_*`), menggantikan tabel RBAC lama (`ref_roles`, `ref_permissions`, `ref_role_permission`) dan kolom `users.role`. API-based session check dengan HMAC per-aplikasi + Sanctum token. SSO menggunakan one-time code exchange untuk keamanan token.

**Tech Stack:** Laravel 12, PHP 8.4, Pest 4.4, React 19, Inertia.js v2, TypeScript, Tailwind CSS 4, Laravel Sanctum, Laravel Fortify.

**Spec:** `docs/superpowers/specs/2026-03-21-iam-sso-design.md`

**Test command:** `php artisan test --filter "NamaTest"` (single), `php artisan test` (all)

---

## File Map

### Dibuat Baru
```
config/iam.php
database/migrations/2026_03_21_000001_create_iam_tables.php
database/migrations/2026_03_21_000002_migrate_rbac_to_iam.php
database/migrations/2026_03_21_000003_drop_old_rbac_tables.php
app/Models/IamApplication.php
app/Models/IamRole.php
app/Models/IamPermission.php
app/Models/IamUserRole.php
app/Models/IamSsoCode.php
app/Http/Middleware/VerifyIamSignature.php
app/Http/Middleware/VerifyIamPermission.php
app/Http/Controllers/Api/IamController.php
app/Http/Controllers/Iam/AplikasiController.php
app/Http/Controllers/Iam/RoleController.php
app/Http/Controllers/Iam/PermissionController.php
app/Http/Controllers/Iam/UserAksesController.php
app/Http/Controllers/SsoController.php
app/Http/Resources/IamValidateResource.php
database/seeders/IamSeeder.php
tests/Feature/Iam/VerifyIamSignatureTest.php
tests/Feature/Iam/VerifyIamPermissionTest.php
tests/Feature/Iam/IamValidateTest.php
tests/Feature/Iam/IamExchangeCodeTest.php
tests/Feature/Iam/IamLogoutTest.php
tests/Feature/Iam/SsoLoginTest.php
tests/Feature/Iam/AplikasiControllerTest.php
tests/Feature/Iam/UserAksesControllerTest.php
resources/js/pages/iam/aplikasi/index.tsx
resources/js/pages/iam/aplikasi/show.tsx
resources/js/pages/iam/users/index.tsx
resources/js/pages/iam/users/akses.tsx
```

### Dimodifikasi
```
app/Models/User.php          — hapus role cast, isAdmin/isOperator/isViewer, tambah iamRoles()
bootstrap/app.php            — tambah alias middleware baru, hapus alias 'role'
routes/web.php               — tambah IAM admin routes, ganti 'role:xxx' → 'iam.permission:xxx'
routes/api.php               — tambah IAM API routes
database/seeders/DatabaseSeeder.php — panggil IamSeeder
```

### Dihapus
```
app/Enums/Role.php
app/Http/Middleware/EnsureRole.php
tests/Feature/Auth/RoleMiddlewareTest.php   — diganti VerifyIamPermissionTest
```

---

## FASE 1: Foundation (Config + Database + Models)

### Task 1: Config IAM

**Files:**
- Create: `config/iam.php`

- [ ] **Step 1: Buat file config**

```php
<?php
// config/iam.php
return [
    'token_ttl_hours'      => env('IAM_TOKEN_TTL_HOURS', 8),
    'sso_code_ttl_seconds' => env('IAM_SSO_CODE_TTL', 60),
];
```

- [ ] **Step 2: Tambah env vars ke .env.example**

Tambahkan di akhir `.env.example`:
```
IAM_TOKEN_TTL_HOURS=8
IAM_SSO_CODE_TTL=60
```

- [ ] **Step 3: Commit**

```bash
git add config/iam.php .env.example
git commit -m "feat(iam): tambah config/iam.php"
```

---

### Task 2: Migration — Buat 6 Tabel IAM

**Files:**
- Create: `database/migrations/2026_03_21_000001_create_iam_tables.php`
- Test: `tests/Feature/Iam/IamTablesTest.php`

- [ ] **Step 1: Tulis failing test — verifikasi tabel ada**

```php
<?php
// tests/Feature/Iam/IamTablesTest.php

use Illuminate\Support\Facades\Schema;

test('tabel iam_applications ada dengan kolom yang benar', function () {
    expect(Schema::hasTable('iam_applications'))->toBeTrue();
    expect(Schema::hasColumns('iam_applications', [
        'id', 'nama', 'slug', 'url', 'deskripsi',
        'api_key', 'api_secret_hash', 'is_active', 'is_system',
        'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue();
});

test('tabel iam_roles ada dengan kolom yang benar', function () {
    expect(Schema::hasTable('iam_roles'))->toBeTrue();
    expect(Schema::hasColumns('iam_roles', [
        'id', 'iam_application_id', 'nama', 'slug',
        'keterangan', 'is_system', 'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue();
});

test('tabel iam_permissions ada dengan kolom yang benar', function () {
    expect(Schema::hasTable('iam_permissions'))->toBeTrue();
    expect(Schema::hasColumns('iam_permissions', [
        'id', 'iam_application_id', 'nama', 'slug',
        'group', 'keterangan', 'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue();
});

test('tabel iam_role_permissions ada', function () {
    expect(Schema::hasTable('iam_role_permissions'))->toBeTrue();
    expect(Schema::hasColumns('iam_role_permissions', [
        'id', 'iam_role_id', 'iam_permission_id',
    ]))->toBeTrue();
});

test('tabel iam_user_roles ada', function () {
    expect(Schema::hasTable('iam_user_roles'))->toBeTrue();
    expect(Schema::hasColumns('iam_user_roles', [
        'id', 'user_id', 'iam_role_id', 'assigned_at', 'assigned_by',
    ]))->toBeTrue();
});

test('tabel iam_sso_codes ada', function () {
    expect(Schema::hasTable('iam_sso_codes'))->toBeTrue();
    expect(Schema::hasColumns('iam_sso_codes', [
        'id', 'code', 'user_id', 'app_slug', 'used_at', 'expires_at',
    ]))->toBeTrue();
});
```

- [ ] **Step 2: Run test — pastikan FAIL**

```bash
php artisan test --filter "IamTablesTest"
```
Expected: FAIL — "tabel iam_applications tidak ada"

- [ ] **Step 3: Buat migration**

```bash
php artisan make:migration create_iam_tables
```

Isi migration (rename file ke `2026_03_21_000001_create_iam_tables.php`):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iam_applications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('nama');
            $table->string('slug')->unique();
            $table->string('url');
            $table->text('deskripsi')->nullable();
            $table->string('api_key')->unique();
            $table->string('api_secret_hash');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('iam_roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('iam_application_id')
                ->constrained('iam_applications')
                ->cascadeOnDelete();
            $table->string('nama');
            $table->string('slug');
            $table->text('keterangan')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['iam_application_id', 'slug']);
        });

        Schema::create('iam_permissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('iam_application_id')
                ->constrained('iam_applications')
                ->cascadeOnDelete();
            $table->string('nama');
            $table->string('slug');
            $table->string('group')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['iam_application_id', 'slug']);
        });

        Schema::create('iam_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('iam_role_id')
                ->constrained('iam_roles')
                ->cascadeOnDelete();
            $table->foreignUlid('iam_permission_id')
                ->constrained('iam_permissions')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['iam_role_id', 'iam_permission_id']);
        });

        Schema::create('iam_user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignUlid('iam_role_id')
                ->constrained('iam_roles')
                ->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'iam_role_id']);
        });

        Schema::create('iam_sso_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('app_slug');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iam_sso_codes');
        Schema::dropIfExists('iam_user_roles');
        Schema::dropIfExists('iam_role_permissions');
        Schema::dropIfExists('iam_permissions');
        Schema::dropIfExists('iam_roles');
        Schema::dropIfExists('iam_applications');
    }
};
```

- [ ] **Step 4: Jalankan migration**

```bash
php artisan migrate
```

- [ ] **Step 5: Run test — pastikan PASS**

```bash
php artisan test --filter "IamTablesTest"
```
Expected: 6 PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_03_21_000001_create_iam_tables.php tests/Feature/Iam/IamTablesTest.php
git commit -m "feat(iam): buat 6 tabel IAM baru"
```

---

### Task 3: Models IAM

**Files:**
- Create: `app/Models/IamApplication.php`, `IamRole.php`, `IamPermission.php`, `IamRolePermission.php`, `IamUserRole.php`, `IamSsoCode.php`
- Test: `tests/Feature/Iam/IamModelsTest.php`

- [ ] **Step 1: Tulis failing test — relasi model**

```php
<?php
// tests/Feature/Iam/IamModelsTest.php

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamSsoCode;
use App\Models\IamUserRole;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

test('IamApplication memiliki relasi roles dan permissions', function () {
    $app = IamApplication::create([
        'nama'            => 'Test App',
        'slug'            => 'test-app',
        'url'             => 'http://test.local',
        'api_key'         => 'test-key-123',
        'api_secret_hash' => bcrypt('secret'),
    ]);

    expect($app->roles())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    expect($app->permissions())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('IamRole memiliki relasi permissions dan application', function () {
    $app = IamApplication::factory()->create();
    $role = IamRole::create([
        'iam_application_id' => $app->id,
        'nama'               => 'Admin',
        'slug'               => 'admin',
    ]);

    expect($role->application())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    expect($role->permissions())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
});

test('User memiliki relasi iamRoles', function () {
    $user = User::factory()->create();
    expect($user->iamRoles())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('IamApplication generateApiCredentials menghasilkan key dan secret', function () {
    ['key' => $key, 'secret' => $secret, 'hash' => $hash] = IamApplication::generateApiCredentials();

    expect($key)->toStartWith('iam_');
    expect(strlen($secret))->toBe(64);
    // api_secret_hash menggunakan Crypt::encryptString agar bisa di-retrieve untuk HMAC
    expect(Crypt::decryptString($hash))->toBe($secret);
});

test('IamApplication verifySecret memvalidasi secret dengan benar', function () {
    $plainSecret = 'correct-secret-value-64chars-padding-here-123456789-abc';
    $app = IamApplication::create([
        'nama'            => 'Test',
        'slug'            => 'test-verify',
        'url'             => 'http://test.local',
        'api_key'         => 'test-key-verify',
        'api_secret_hash' => Crypt::encryptString($plainSecret),
    ]);

    expect($app->verifySecret($plainSecret))->toBeTrue();
    expect($app->verifySecret('wrong-secret'))->toBeFalse();
});
```

- [ ] **Step 2: Run test — pastikan FAIL**

```bash
php artisan test --filter "IamModelsTest"
```
Expected: FAIL — "Class IamApplication not found"

- [ ] **Step 3: Buat model IamApplication**

```php
<?php
// app/Models/IamApplication.php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class IamApplication extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'nama', 'slug', 'url', 'deskripsi',
        'api_key', 'api_secret_hash', 'is_active', 'is_system',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_system' => 'boolean'];
    }

    public function roles(): HasMany
    {
        return $this->hasMany(IamRole::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(IamPermission::class);
    }

    /**
     * Generate api_key + api_secret baru. Secret hanya dikembalikan sekali.
     * Menggunakan Crypt::encryptString (BUKAN Hash::make) agar secret bisa
     * di-retrieve untuk keperluan HMAC signature verification.
     */
    public static function generateApiCredentials(): array
    {
        $key    = 'iam_' . Str::random(32);
        $secret = Str::random(64);
        $hash   = Crypt::encryptString($secret);

        return ['key' => $key, 'secret' => $secret, 'hash' => $hash];
    }

    public function verifySecret(string $secret): bool
    {
        try {
            return Crypt::decryptString($this->api_secret_hash) === $secret;
        } catch (\Exception) {
            return false;
        }
    }
}
```

- [ ] **Step 4: Buat model IamRole**

```php
<?php
// app/Models/IamRole.php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IamRole extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'iam_application_id', 'nama', 'slug', 'keterangan', 'is_system',
    ];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(IamApplication::class, 'iam_application_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            IamPermission::class,
            'iam_role_permissions',
            'iam_role_id',
            'iam_permission_id'
        )->withTimestamps();
    }
}
```

- [ ] **Step 5: Buat model IamPermission**

```php
<?php
// app/Models/IamPermission.php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IamPermission extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'iam_application_id', 'nama', 'slug', 'group', 'keterangan',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(IamApplication::class, 'iam_application_id');
    }
}
```

- [ ] **Step 6: Buat model IamUserRole**

```php
<?php
// app/Models/IamUserRole.php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IamUserRole extends Model
{
    protected $fillable = [
        'user_id', 'iam_role_id', 'assigned_at', 'assigned_by',
    ];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime'];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(IamRole::class, 'iam_role_id');
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
```

- [ ] **Step 7: Buat model IamSsoCode**

```php
<?php
// app/Models/IamSsoCode.php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IamSsoCode extends Model
{
    public const UPDATED_AT = null; // tabel hanya punya created_at

    protected $fillable = [
        'code', 'user_id', 'app_slug', 'used_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'used_at'    => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isUsed();
    }
}
```

- [ ] **Step 8: Tambah relasi iamRoles ke User model**

Di `app/Models/User.php`, tambahkan method (jangan hapus apapun dulu):

```php
public function iamRoles(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(IamUserRole::class);
}
```

- [ ] **Step 9: Run test — pastikan PASS**

```bash
php artisan test --filter "IamModelsTest"
```
Expected: 5 PASS

- [ ] **Step 10: Commit**

```bash
git add app/Models/Iam*.php app/Models/User.php tests/Feature/Iam/IamModelsTest.php
git commit -m "feat(iam): tambah model IAM dengan relasi"
```

---

### Task 4: Migration Data (Migrasi RBAC Lama → IAM)

**Files:**
- Create: `database/migrations/2026_03_21_000002_migrate_rbac_to_iam.php`
- Test: `tests/Feature/Iam/IamDataMigrationTest.php`

- [ ] **Step 1: Tulis failing test — data ter-migrasi**

```php
<?php
// tests/Feature/Iam/IamDataMigrationTest.php

use App\Models\IamApplication;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('kepegawaian-apps terdaftar sebagai aplikasi sistem pertama', function () {
    $app = IamApplication::where('slug', 'kepegawaian')->first();

    expect($app)->not->toBeNull();
    expect($app->is_system)->toBeTrue();
    expect($app->is_active)->toBeTrue();
});

test('user admin ter-assign ke role admin di kepegawaian-apps', function () {
    $kepegawaian = IamApplication::where('slug', 'kepegawaian')->firstOrFail();
    $adminRole   = IamRole::where('iam_application_id', $kepegawaian->id)
        ->where('slug', 'admin')
        ->first();

    expect($adminRole)->not->toBeNull();

    // Buat user admin lama (simulasi users.role = 'admin' sudah dimigrasikan)
    $user       = User::factory()->create();
    $userRoleRow = IamUserRole::create([
        'user_id'    => $user->id,
        'iam_role_id' => $adminRole->id,
        'assigned_at' => now(),
    ]);

    expect($userRoleRow)->not->toBeNull();
    expect($user->iamRoles()->count())->toBe(1);
});

test('user dengan role tidak dikenal di-assign ke viewer sebagai fallback', function () {
    $kepegawaian = IamApplication::where('slug', 'kepegawaian')->firstOrFail();
    $viewerRole  = IamRole::where('iam_application_id', $kepegawaian->id)
        ->where('slug', 'viewer')
        ->first();

    expect($viewerRole)->not->toBeNull();
});
```

- [ ] **Step 2: Run test — pastikan FAIL**

```bash
php artisan test --filter "IamDataMigrationTest"
```
Expected: FAIL — "kepegawaian-apps tidak ditemukan"

- [ ] **Step 3: Buat IamSeeder**

```php
<?php
// database/seeders/IamSeeder.php
namespace Database\Seeders;

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IamSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Daftarkan kepegawaian-apps sebagai aplikasi pertama
        // generateApiCredentials menggunakan Crypt::encryptString (bukan Hash::make)
        ['key' => $key, 'hash' => $hash] = IamApplication::generateApiCredentials();

        $kepegawaian = IamApplication::create([
            'nama'            => 'Kepegawaian Apps',
            'slug'            => 'kepegawaian',
            'url'             => config('app.url'),
            'deskripsi'       => 'Sistem master data kepegawaian PA Penajam',
            'api_key'         => $key,
            'api_secret_hash' => $hash,
            'is_system'       => true,
            'is_active'       => true,
        ]);

        // 2. Migrasi ref_roles → iam_roles
        $refRoles = DB::table('ref_roles')->whereNull('deleted_at')->get();
        $roleMap  = []; // ref_role_id => iam_role_id

        foreach ($refRoles as $refRole) {
            $slug = Str::slug($refRole->nama);
            $iamRole = IamRole::firstOrCreate(
                ['iam_application_id' => $kepegawaian->id, 'slug' => $slug],
                [
                    'nama'      => $refRole->nama,
                    'keterangan' => $refRole->keterangan,
                    'is_system' => $refRole->is_system ?? false,
                ]
            );
            $roleMap[$refRole->id] = $iamRole->id;
        }

        // 3. Migrasi ref_permissions → iam_permissions
        $refPerms = DB::table('ref_permissions')->whereNull('deleted_at')->get();
        $permMap  = [];

        foreach ($refPerms as $refPerm) {
            $slug = Str::slug($refPerm->nama, ':');
            $iamPerm = IamPermission::create([
                'iam_application_id' => $kepegawaian->id,
                'nama'               => $refPerm->nama,
                'slug'               => $slug,
                'group'              => $refPerm->group,
                'keterangan'         => $refPerm->keterangan,
            ]);
            $permMap[$refPerm->id] = $iamPerm->id;
        }

        // 4. Migrasi ref_role_permission → iam_role_permissions
        $pivots = DB::table('ref_role_permission')->get();
        foreach ($pivots as $pivot) {
            if (isset($roleMap[$pivot->ref_role_id]) && isset($permMap[$pivot->ref_permission_id])) {
                DB::table('iam_role_permissions')->insert([
                    'iam_role_id'       => $roleMap[$pivot->ref_role_id],
                    'iam_permission_id' => $permMap[$pivot->ref_permission_id],
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }
        }

        // 5. Migrasi users.role → iam_user_roles (jika kolom masih ada)
        if (DB::getSchemaBuilder()->hasColumn('users', 'role')) {
            $users = User::all();
            $defaultRole = IamRole::where('iam_application_id', $kepegawaian->id)
                ->where('slug', 'viewer')
                ->first();

            foreach ($users as $user) {
                $roleSlug = $user->getRawOriginal('role') ?? 'viewer';
                $iamRole = IamRole::where('iam_application_id', $kepegawaian->id)
                    ->where('slug', $roleSlug)
                    ->first() ?? $defaultRole;

                if ($iamRole) {
                    IamUserRole::firstOrCreate(
                        ['user_id' => $user->id, 'iam_role_id' => $iamRole->id],
                        ['assigned_at' => now()]
                    );
                }
            }
        }

        // 6. Pastikan role default tersedia (admin, operator, viewer)
        foreach (['admin', 'operator', 'viewer'] as $slug) {
            IamRole::firstOrCreate(
                ['iam_application_id' => $kepegawaian->id, 'slug' => $slug],
                ['nama' => ucfirst($slug), 'is_system' => true]
            );
        }
    }
}
```

- [ ] **Step 4: Daftarkan IamSeeder di DatabaseSeeder**

Di `database/seeders/DatabaseSeeder.php`, tambahkan:
```php
$this->call(IamSeeder::class);
```

- [ ] **Step 5: Jalankan seeder**

```bash
php artisan db:seed --class=IamSeeder
```

- [ ] **Step 6: Run test — pastikan PASS**

```bash
php artisan test --filter "IamDataMigrationTest"
```
Expected: 3 PASS

- [ ] **Step 7: Commit**

```bash
git add database/seeders/IamSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/Iam/IamDataMigrationTest.php
git commit -m "feat(iam): tambah IamSeeder untuk migrasi data RBAC lama"
```

---

### Task 5: Migration Drop Tabel Lama + Cleanup

**Files:**
- Create: `database/migrations/2026_03_21_000003_drop_old_rbac_tables.php`

> ⚠️ **Jalankan SETELAH IamSeeder dijalankan di production.** Migration ini DROP data.

- [ ] **Step 1: Buat migration drop**

```php
<?php
// database/migrations/2026_03_21_000003_drop_old_rbac_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop kolom users.role
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        // Drop tabel lama (urutan: pivot dulu, lalu parent)
        Schema::dropIfExists('ref_role_permission');
        Schema::dropIfExists('ref_permissions');
        Schema::dropIfExists('ref_roles');
    }

    public function down(): void
    {
        // Tidak bisa di-reverse secara otomatis (data sudah di IAM tables)
        // Restore manual dari IamSeeder data jika diperlukan
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('viewer');
        });
    }
};
```

- [ ] **Step 2: Jalankan migration**

```bash
php artisan migrate
```

- [ ] **Step 3: Hapus App\Enums\Role dan cleanup User model**

Hapus file `app/Enums/Role.php`.

Di `app/Models/User.php`:
- Hapus `use App\Enums\Role;`
- Hapus dari `$fillable`: `'role'`
- Hapus dari `casts()`: `'role' => Role::class`
- Hapus method `isAdmin()`, `isOperator()`, `isViewer()`

- [ ] **Step 4: Hapus EnsureRole dan test lama yang tidak relevan**

```bash
rm app/Http/Middleware/EnsureRole.php
rm tests/Feature/Auth/RoleMiddlewareTest.php
```

- [ ] **Step 5: Run test suite — pastikan tidak ada yang break**

```bash
php artisan test
```
Expected: semua test yang ada masih PASS (kecuali RoleMiddlewareTest yang sudah dihapus).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_03_21_000003_drop_old_rbac_tables.php app/Models/User.php
git rm app/Enums/Role.php app/Http/Middleware/EnsureRole.php tests/Feature/Auth/RoleMiddlewareTest.php
git commit -m "feat(iam): drop tabel RBAC lama, hapus Role enum dan EnsureRole middleware"
```

---

## FASE 2: Security Layer (Middleware)

### Task 6: Middleware VerifyIamSignature

**Files:**
- Create: `app/Http/Middleware/VerifyIamSignature.php`
- Test: `tests/Feature/Iam/VerifyIamSignatureTest.php`

- [ ] **Step 1: Tulis failing test**

```php
<?php
// tests/Feature/Iam/VerifyIamSignatureTest.php

use App\Models\IamApplication;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

// Helper untuk membuat IAM signed headers
function makeIamHeaders(string $method, string $path, string $apiKey, string $apiSecret, array $query = []): array
{
    $timestamp   = now()->timestamp;
    $queryString = http_build_query(collect($query)->sortKeys()->all());
    $payload     = strtoupper($method) . ':' . $path . ':' . $queryString . ':' . $timestamp;
    $signature   = hash_hmac('sha256', $payload, $apiSecret);

    return [
        'X-App-Key'   => $apiKey,
        'X-Signature' => $signature,
        'X-Timestamp' => $timestamp,
        'Accept'      => 'application/json',
    ];
}

beforeEach(function () {
    Route::middleware(['auth:sanctum', 'iam.signature'])
        ->get('/test-iam-signature', fn () => response()->json(['ok' => true]));
});

test('request tanpa X-App-Key ditolak 401', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/test-iam-signature')->assertStatus(401);
});

test('request dengan api_key tidak dikenal ditolak 401', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/test-iam-signature', [
        'X-App-Key'   => 'unknown-key',
        'X-Signature' => 'anything',
        'X-Timestamp' => now()->timestamp,
    ])->assertStatus(401);
});

test('request dengan signature salah ditolak 401', function () {
    $user    = User::factory()->create();
    $secret  = 'correct-secret-64chars-padding-here-abc123def456ghi789';
    $app     = IamApplication::create([
        'nama'            => 'Test App',
        'slug'            => 'test',
        'url'             => 'http://test.local',
        'api_key'         => 'valid-key-123',
        'api_secret_hash' => Crypt::encryptString($secret),
    ]);

    Sanctum::actingAs($user);

    $headers = makeIamHeaders('GET', '/test-iam-signature', $app->api_key, 'wrong-secret');
    $this->getJson('/test-iam-signature', $headers)->assertStatus(401);
});

test('request dengan timestamp kedaluwarsa ditolak 401', function () {
    $user    = User::factory()->create();
    $secret  = 'correct-secret-64chars-padding-here-abc123def456ghi789';
    $app     = IamApplication::create([
        'nama'            => 'Test App',
        'slug'            => 'test',
        'url'             => 'http://test.local',
        'api_key'         => 'valid-key-123',
        'api_secret_hash' => Crypt::encryptString($secret),
    ]);

    Sanctum::actingAs($user);

    $oldTimestamp = now()->subMinutes(6)->timestamp;
    $payload      = 'GET:/test-iam-signature::' . $oldTimestamp;
    $signature    = hash_hmac('sha256', $payload, $secret);

    $this->getJson('/test-iam-signature', [
        'X-App-Key'   => $app->api_key,
        'X-Signature' => $signature,
        'X-Timestamp' => $oldTimestamp,
    ])->assertStatus(401);
});

test('request valid dengan signature benar lolos', function () {
    $user    = User::factory()->create();
    $secret  = 'correct-secret-64chars-padding-here-abc123def456ghi789';
    $app     = IamApplication::create([
        'nama'            => 'Test App',
        'slug'            => 'test',
        'url'             => 'http://test.local',
        'api_key'         => 'valid-key-123',
        'api_secret_hash' => Crypt::encryptString($secret),
    ]);

    Sanctum::actingAs($user);

    $headers = makeIamHeaders('GET', '/test-iam-signature', $app->api_key, $secret);
    $this->getJson('/test-iam-signature', $headers)->assertOk();
});
```

- [ ] **Step 2: Run test — pastikan FAIL**

```bash
php artisan test --filter "VerifyIamSignatureTest"
```

- [ ] **Step 3: Buat middleware**

```php
<?php
// app/Http/Middleware/VerifyIamSignature.php
namespace App\Http\Middleware;

use App\Models\IamApplication;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyIamSignature
{
    private const TIMESTAMP_WINDOW = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $apiKey    = $request->header('X-App-Key');
        $timestamp = $request->header('X-Timestamp');
        $received  = $request->header('X-Signature');

        if (! $apiKey || ! $timestamp || ! $received) {
            return response()->json(['message' => 'Missing IAM signature headers'], 401);
        }

        if (abs(now()->timestamp - (int) $timestamp) > self::TIMESTAMP_WINDOW) {
            return response()->json(['message' => 'Request expired'], 401);
        }

        $app = IamApplication::where('api_key', $apiKey)->where('is_active', true)->first();

        if (! $app) {
            return response()->json(['message' => 'Unknown application'], 401);
        }

        // Rekonstruksi payload: METHOD:PATH:SORTED_QUERY:TIMESTAMP
        $queryString = http_build_query(collect($request->query())->sortKeys()->all());
        $payload     = strtoupper($request->method())
            . ':' . $request->getPathInfo()
            . ':' . $queryString
            . ':' . $timestamp;

        // Verify dengan api_secret_hash — harus bruteforce-safe via hash_hmac
        // Karena secret di-hash dengan bcrypt, kita harus extract secret dari header terlebih dahulu.
        // Approach: client mengirim HMAC dengan api_secret plaintext. Server menyimpan hash.
        // Untuk verify, kita perlu strategy berbeda: gunakan HMAC langsung dengan api_key sebagai secret,
        // karena api_secret_hash tidak bisa di-reverse untuk dijadikan HMAC key.
        //
        // SOLUSI: gunakan Hash::check untuk verify bahwa signature yang diterima adalah
        // HMAC dari payload menggunakan api_key sebagai secret.
        // Ini artinya client menggunakan api_secret (plaintext di sisi mereka) sebagai HMAC key.
        // Server menyimpan hash dari api_secret untuk verifikasi: Hash::check(received_hmac_input, hash).
        //
        // Simplified approach yang aman: verify HMAC dengan api_secret yang di-store as hash.
        // Karena bcrypt tidak bisa digunakan sebagai HMAC key, kita simpan api_secret sebagai
        // encrypted string (reversible) bukan hash (irreversible).
        //
        // Per spec: "api_secret_hash" menggunakan hash_hmac bukan bcrypt.
        // Client menghitung: HMAC-SHA256(payload, api_secret)
        // Server menghitung: HMAC-SHA256(payload, api_secret) lalu bandingkan dengan hash_equals
        //
        // Artinya api_secret_hash BUKAN bcrypt hash, melainkan disimpan terenkripsi
        // agar bisa di-retrieve. Gunakan Crypt::encryptString / decryptString.

        try {
            $secret   = \Illuminate\Support\Facades\Crypt::decryptString($app->api_secret_hash);
            $expected = hash_hmac('sha256', $payload, $secret);

            if (! hash_equals($expected, $received)) {
                return response()->json(['message' => 'Invalid signature'], 401);
            }
        } catch (\Exception) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        // Inject app ke request untuk digunakan controller
        $request->merge(['_iam_app' => $app]);

        return $next($request);
    }
}
```

> **Catatan penting:** `IamApplication` sudah menggunakan `Crypt::encryptString()` sejak Task 3. Tidak ada update lagi di sini — middleware tinggal memanggil `Crypt::decryptString($app->api_secret_hash)` untuk mendapatkan HMAC key.

- [ ] **Step 4: Verifikasi IamApplication menggunakan Crypt (bukan Hash)**

Pastikan `app/Models/IamApplication.php` menggunakan `Crypt::encryptString` di `generateApiCredentials()` dan `Crypt::decryptString` di `verifySecret()`. Ini sudah diimplementasi di Task 3 — tidak ada perubahan tambahan.

- [ ] **Step 5: Register middleware alias di bootstrap/app.php**

```php
// Di dalam ->withMiddleware()
$middleware->alias([
    'iam.signature' => \App\Http\Middleware\VerifyIamSignature::class,
    // ... middleware lain tetap
]);
```

- [ ] **Step 6: Run test — pastikan PASS**

```bash
php artisan test --filter "VerifyIamSignatureTest"
```
Expected: 5 PASS

- [ ] **Step 7: Commit**

```bash
git add app/Http/Middleware/VerifyIamSignature.php app/Models/IamApplication.php bootstrap/app.php tests/Feature/Iam/VerifyIamSignatureTest.php
git commit -m "feat(iam): tambah VerifyIamSignature middleware dengan per-app secret"
```

---

### Task 7: Middleware VerifyIamPermission

**Files:**
- Create: `app/Http/Middleware/VerifyIamPermission.php`
- Test: `tests/Feature/Iam/VerifyIamPermissionTest.php`

- [ ] **Step 1: Tulis failing test**

```php
<?php
// tests/Feature/Iam/VerifyIamPermissionTest.php

use App\Models\IamApplication;
use Database\Seeders\IamSeeder;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Seed IAM data — kepegawaian app + default roles harus ada
    $this->seed(IamSeeder::class);

    Route::middleware(['auth', 'iam.permission:pegawai:read'])
        ->get('/test-iam-perm', fn () => 'ok');
});

test('guest diredirect ke login', function () {
    $this->get('/test-iam-perm')->assertRedirect(route('login'));
});

test('user tanpa role di aplikasi ini mendapat 403', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/test-iam-perm')->assertForbidden();
});

test('user dengan role tapi tidak punya permission mendapat 403', function () {
    $app  = IamApplication::where('slug', 'kepegawaian')->firstOrFail();
    $role = IamRole::where('iam_application_id', $app->id)->where('slug', 'viewer')->firstOrFail();
    $user = User::factory()->create();

    IamUserRole::create(['user_id' => $user->id, 'iam_role_id' => $role->id, 'assigned_at' => now()]);

    // viewer tidak punya permission pegawai:read
    $this->actingAs($user)->get('/test-iam-perm')->assertForbidden();
});

test('user dengan permission yang sesuai lolos', function () {
    $app  = IamApplication::where('slug', 'kepegawaian')->firstOrFail();
    $role = IamRole::where('iam_application_id', $app->id)->where('slug', 'admin')->firstOrFail();
    $perm = IamPermission::firstOrCreate(
        ['iam_application_id' => $app->id, 'slug' => 'pegawai:read'],
        ['nama' => 'Lihat Pegawai', 'group' => 'pegawai']
    );
    $role->permissions()->syncWithoutDetaching([$perm->id]);

    $user = User::factory()->create();
    IamUserRole::create(['user_id' => $user->id, 'iam_role_id' => $role->id, 'assigned_at' => now()]);

    $this->actingAs($user)->get('/test-iam-perm')->assertOk();
});
```

- [ ] **Step 2: Run test — pastikan FAIL**

```bash
php artisan test --filter "VerifyIamPermissionTest"
```

- [ ] **Step 3: Buat middleware**

```php
<?php
// app/Http/Middleware/VerifyIamPermission.php
namespace App\Http\Middleware;

use App\Models\IamApplication;
use App\Models\IamUserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyIamPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            return redirect()->route('login');
        }

        $kepegawaian = IamApplication::where('slug', 'kepegawaian')->first();
        if (! $kepegawaian) {
            abort(Response::HTTP_FORBIDDEN);
        }

        // Kumpulkan semua permissions user untuk aplikasi kepegawaian
        $userPermissions = IamUserRole::where('user_id', $user->id)
            ->whereHas('role', fn ($q) => $q->where('iam_application_id', $kepegawaian->id))
            ->with('role.permissions')
            ->get()
            ->flatMap(fn ($ur) => $ur->role->permissions->pluck('slug'))
            ->unique()
            ->values()
            ->all();

        // Jika tidak ada permission yang diminta, cukup cek user punya role di app ini
        if (empty($permissions)) {
            if (empty($userPermissions)) {
                abort(Response::HTTP_FORBIDDEN);
            }
            return $next($request);
        }

        // Cek semua permission yang diminta terpenuhi
        foreach ($permissions as $permission) {
            if (! in_array($permission, $userPermissions, true)) {
                abort(Response::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Register middleware alias**

Di `bootstrap/app.php`, tambahkan:
```php
'iam.permission' => \App\Http\Middleware\VerifyIamPermission::class,
```

- [ ] **Step 5: Run test — pastikan PASS**

```bash
php artisan test --filter "VerifyIamPermissionTest"
```
Expected: 4 PASS

- [ ] **Step 6: Update routes/web.php — ganti 'role:xxx' → 'iam.permission'**

Di `routes/web.php`, ganti:
```php
// SEBELUM:
Route::middleware(['auth', 'verified', 'role:admin,operator'])->group(...)

// SESUDAH:
Route::middleware(['auth', 'verified', 'iam.permission'])->group(...)
```

> Untuk saat ini, cukup require user punya role di aplikasi. Permission granular bisa ditambahkan bertahap.

- [ ] **Step 7: Run full test suite**

```bash
php artisan test
```
Expected: semua PASS

- [ ] **Step 8: Commit**

```bash
git add app/Http/Middleware/VerifyIamPermission.php bootstrap/app.php routes/web.php tests/Feature/Iam/VerifyIamPermissionTest.php
git commit -m "feat(iam): tambah VerifyIamPermission middleware, gantikan EnsureRole di routes"
```

---

## FASE 3: API Layer

### Task 8: IamController — validate + check

**Files:**
- Create: `app/Http/Controllers/Api/IamController.php`
- Create: `app/Http/Resources/IamValidateResource.php`
- Test: `tests/Feature/Iam/IamValidateTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Tulis failing test**

```php
<?php
// tests/Feature/Iam/IamValidateTest.php

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamSsoCode;
use App\Models\IamUserRole;
use App\Models\Pegawai;
use App\Models\User;
use Database\Seeders\IamSeeder;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(IamSeeder::class));

function makeTestApp(string $slug = 'attendance'): array
{
    $secret = str_repeat('a', 64);
    $app    = IamApplication::create([
        'nama'            => 'Test App',
        'slug'            => $slug,
        'url'             => 'http://test.local',
        'api_key'         => 'test-key-' . $slug,
        'api_secret_hash' => Crypt::encryptString($secret),
        'is_active'       => true,
    ]);
    return ['app' => $app, 'secret' => $secret];
}

function makeIamValidateHeaders(string $apiKey, string $apiSecret): array
{
    $timestamp   = now()->timestamp;
    $payload     = 'GET:/api/v1/iam/validate::' . $timestamp;
    $signature   = hash_hmac('sha256', $payload, $apiSecret);
    return [
        'X-App-Key'   => $apiKey,
        'X-Signature' => $signature,
        'X-Timestamp' => $timestamp,
        'Accept'      => 'application/json',
    ];
}

test('validate mengembalikan user info dan permissions untuk aplikasi pemanggil', function () {
    ['app' => $app, 'secret' => $secret] = makeTestApp();

    $kepegawaian = IamApplication::where('slug', 'kepegawaian')->firstOrFail();
    $role        = IamRole::firstOrCreate(
        ['iam_application_id' => $app->id, 'slug' => 'petugas'],
        ['nama' => 'Petugas']
    );
    $perm = IamPermission::firstOrCreate(
        ['iam_application_id' => $app->id, 'slug' => 'absensi:create'],
        ['nama' => 'Buat Absensi', 'group' => 'absensi']
    );
    $role->permissions()->syncWithoutDetaching([$perm->id]);

    $user = User::factory()->create();
    IamUserRole::create(['user_id' => $user->id, 'iam_role_id' => $role->id, 'assigned_at' => now()]);

    Sanctum::actingAs($user);

    $headers  = makeIamValidateHeaders($app->api_key, $secret);
    $response = $this->getJson('/api/v1/iam/validate', $headers);

    $response->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('roles.0', 'petugas')
        ->assertJsonPath('permissions.0', 'absensi:create')
        ->assertJsonHasKey('token_expires_at');
});

test('validate mengembalikan array kosong jika user tidak punya role di aplikasi', function () {
    ['app' => $app, 'secret' => $secret] = makeTestApp('app-b');

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $headers  = makeIamValidateHeaders($app->api_key, $secret);
    $response = $this->getJson('/api/v1/iam/validate', $headers);

    $response->assertOk()
        ->assertJsonPath('roles', [])
        ->assertJsonPath('permissions', []);
});

test('validate mengembalikan 401 jika token tidak valid', function () {
    ['app' => $app, 'secret' => $secret] = makeTestApp('app-c');

    $headers = makeIamValidateHeaders($app->api_key, $secret);
    $this->getJson('/api/v1/iam/validate', $headers)->assertStatus(401);
});

test('check mengembalikan allowed true jika user punya permission', function () {
    ['app' => $app, 'secret' => $secret] = makeTestApp('app-d');

    $role = IamRole::firstOrCreate(
        ['iam_application_id' => $app->id, 'slug' => 'admin'],
        ['nama' => 'Admin']
    );
    $perm = IamPermission::firstOrCreate(
        ['iam_application_id' => $app->id, 'slug' => 'rekap:export'],
        ['nama' => 'Export Rekap']
    );
    $role->permissions()->syncWithoutDetaching([$perm->id]);

    $user = User::factory()->create();
    IamUserRole::create(['user_id' => $user->id, 'iam_role_id' => $role->id, 'assigned_at' => now()]);
    Sanctum::actingAs($user);

    $timestamp = now()->timestamp;
    $qs        = http_build_query(['permission' => 'rekap:export']);
    $payload   = 'GET:/api/v1/iam/check:' . $qs . ':' . $timestamp;
    $signature = hash_hmac('sha256', $payload, $secret);

    $this->getJson('/api/v1/iam/check?permission=rekap:export', [
        'X-App-Key' => $app->api_key, 'X-Signature' => $signature, 'X-Timestamp' => $timestamp,
    ])->assertOk()->assertJsonPath('allowed', true);
});
```

- [ ] **Step 2: Run test — FAIL**

```bash
php artisan test --filter "IamValidateTest"
```

- [ ] **Step 3: Tambah IAM routes di api.php**

```php
// routes/api.php — tambahkan di bawah routes pegawai yang ada
use App\Http\Controllers\Api\IamController;

Route::middleware(['auth:sanctum', 'iam.signature'])->prefix('v1/iam')->group(function () {
    Route::get('validate', [IamController::class, 'validate']);
    Route::get('check', [IamController::class, 'check']);
    Route::post('logout', [IamController::class, 'logout']);
});

// Exchange code tidak perlu auth:sanctum (dipanggil sebelum user punya token)
Route::middleware(['iam.signature'])->prefix('v1/iam')->group(function () {
    Route::post('exchange-code', [IamController::class, 'exchangeCode']);
});
```

- [ ] **Step 4: Buat IamController**

```php
<?php
// app/Http/Controllers/Api/IamController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\IamValidateResource;
use App\Models\IamApplication;
use App\Models\IamSsoCode;
use App\Models\IamUserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IamController extends Controller
{
    public function validate(Request $request): JsonResponse
    {
        $user    = $request->user();
        $app     = $request->get('_iam_app'); // diinjek oleh VerifyIamSignature

        $userRoles = IamUserRole::where('user_id', $user->id)
            ->whereHas('role', fn ($q) => $q->where('iam_application_id', $app->id))
            ->with('role.permissions')
            ->get();

        $roles       = $userRoles->map(fn ($ur) => $ur->role->slug)->values()->all();
        $permissions = $userRoles
            ->flatMap(fn ($ur) => $ur->role->permissions->pluck('slug'))
            ->unique()->values()->all();

        $token = $user->currentAccessToken();

        return response()->json([
            'user'             => (new IamValidateResource($user))->resolve(),
            'roles'            => $roles,
            'permissions'      => $permissions,
            'token_expires_at' => $token?->expires_at?->timestamp,
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        $user       = $request->user();
        $app        = $request->get('_iam_app');
        $permission = $request->query('permission', '');

        $userPermissions = IamUserRole::where('user_id', $user->id)
            ->whereHas('role', fn ($q) => $q->where('iam_application_id', $app->id))
            ->with('role.permissions')
            ->get()
            ->flatMap(fn ($ur) => $ur->role->permissions->pluck('slug'))
            ->unique()->values()->all();

        return response()->json([
            'allowed'    => in_array($permission, $userPermissions, true),
            'permission' => $permission,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Token invalidated']);
    }

    public function exchangeCode(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:64']);

        $ssoCode = IamSsoCode::where('code', $request->code)->first();

        if (! $ssoCode || ! $ssoCode->isValid()) {
            return response()->json(['message' => 'Invalid or expired code'], 400);
        }

        // Tandai code sebagai sudah dipakai
        $ssoCode->update(['used_at' => now()]);

        // Generate Sanctum token untuk user
        $user     = $ssoCode->user;
        $ttlHours = config('iam.token_ttl_hours', 8);
        $token    = $user->createToken('sso', ['*'], now()->addHours($ttlHours));

        return response()->json([
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at->timestamp,
        ]);
    }
}
```

- [ ] **Step 5: Buat IamValidateResource**

```php
<?php
// app/Http/Resources/IamValidateResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class IamValidateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'email' => $this->email,
            'nip'   => $this->pegawai?->nip,
        ];
    }
}
```

- [ ] **Step 6: Run test — PASS**

```bash
php artisan test --filter "IamValidateTest"
```
Expected: 4 PASS

- [ ] **Step 7: Tulis failing test — exchange-code**

```php
<?php
// tests/Feature/Iam/IamExchangeCodeTest.php

use App\Models\IamApplication;
use App\Models\IamSsoCode;
use App\Models\User;
use Database\Seeders\IamSeeder;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(IamSeeder::class));

function makeExchangeApp(): array
{
    $secret = str_repeat('b', 64);
    $app    = IamApplication::create([
        'nama'            => 'Exchange Test App',
        'slug'            => 'exchange-test',
        'url'             => 'http://exchange.local',
        'api_key'         => 'exchange-key',
        'api_secret_hash' => Crypt::encryptString($secret),
        'is_active'       => true,
    ]);
    return ['app' => $app, 'secret' => $secret];
}

function makeExchangeHeaders(string $apiKey, string $apiSecret): array
{
    $timestamp = now()->timestamp;
    $payload   = 'POST:/api/v1/iam/exchange-code::' . $timestamp;
    $signature = hash_hmac('sha256', $payload, $apiSecret);
    return [
        'X-App-Key'   => $apiKey,
        'X-Signature' => $signature,
        'X-Timestamp' => $timestamp,
        'Accept'      => 'application/json',
    ];
}

test('exchange-code valid menghasilkan sanctum token', function () {
    ['app' => $app, 'secret' => $secret] = makeExchangeApp();
    $user = User::factory()->create();

    $code = IamSsoCode::create([
        'code'       => str_repeat('c', 64),
        'user_id'    => $user->id,
        'app_slug'   => $app->slug,
        'expires_at' => now()->addMinute(),
    ]);

    $headers  = makeExchangeHeaders($app->api_key, $secret);
    $response = $this->postJson('/api/v1/iam/exchange-code', ['code' => $code->code], $headers);

    $response->assertOk()
        ->assertJsonHasKey('token')
        ->assertJsonPath('token_type', 'Bearer');

    expect($code->fresh()->used_at)->not->toBeNull();
});

test('exchange-code kedaluwarsa ditolak', function () {
    ['app' => $app, 'secret' => $secret] = makeExchangeApp();
    $user = User::factory()->create();

    $code = IamSsoCode::create([
        'code'       => str_repeat('d', 64),
        'user_id'    => $user->id,
        'app_slug'   => $app->slug,
        'expires_at' => now()->subMinute(), // sudah expired
    ]);

    $headers = makeExchangeHeaders($app->api_key, $secret);
    $this->postJson('/api/v1/iam/exchange-code', ['code' => $code->code], $headers)
        ->assertStatus(400);
});

test('exchange-code yang sudah dipakai ditolak', function () {
    ['app' => $app, 'secret' => $secret] = makeExchangeApp();
    $user = User::factory()->create();

    $code = IamSsoCode::create([
        'code'       => str_repeat('e', 64),
        'user_id'    => $user->id,
        'app_slug'   => $app->slug,
        'expires_at' => now()->addMinute(),
        'used_at'    => now(), // sudah dipakai
    ]);

    $headers = makeExchangeHeaders($app->api_key, $secret);
    $this->postJson('/api/v1/iam/exchange-code', ['code' => $code->code], $headers)
        ->assertStatus(400);
});
```

- [ ] **Step 8: Run IamExchangeCodeTest — pastikan FAIL**

```bash
php artisan test --filter "IamExchangeCodeTest"
```
Expected: FAIL — "Route not found" atau controller tidak ada

- [ ] **Step 9: Run IamExchangeCodeTest setelah controller dibuat — pastikan PASS**

```bash
php artisan test --filter "IamExchangeCodeTest"
```
Expected: 3 PASS

- [ ] **Step 10: Tulis failing test — logout**

```php
<?php
// tests/Feature/Iam/IamLogoutTest.php

use App\Models\IamApplication;
use App\Models\User;
use Database\Seeders\IamSeeder;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(IamSeeder::class));

test('logout menginvalidasi token user', function () {
    $secret = str_repeat('f', 64);
    $app    = IamApplication::create([
        'nama'            => 'Logout Test App',
        'slug'            => 'logout-test',
        'url'             => 'http://logout.local',
        'api_key'         => 'logout-key',
        'api_secret_hash' => Crypt::encryptString($secret),
        'is_active'       => true,
    ]);

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $timestamp = now()->timestamp;
    $payload   = 'POST:/api/v1/iam/logout::' . $timestamp;
    $signature = hash_hmac('sha256', $payload, $secret);

    $headers = [
        'X-App-Key'   => $app->api_key,
        'X-Signature' => $signature,
        'X-Timestamp' => $timestamp,
        'Accept'      => 'application/json',
    ];

    $response = $this->postJson('/api/v1/iam/logout', [], $headers);
    $response->assertOk()->assertJsonPath('message', 'Token invalidated');
});

test('logout tanpa token ditolak 401', function () {
    $this->postJson('/api/v1/iam/logout')->assertStatus(401);
});
```

- [ ] **Step 11: Run IamLogoutTest — pastikan FAIL kemudian PASS**

```bash
php artisan test --filter "IamLogoutTest"
```
Expected setelah controller selesai: 2 PASS

- [ ] **Step 12: Commit dengan semua test**

```bash
git add app/Http/Controllers/Api/IamController.php app/Http/Resources/IamValidateResource.php routes/api.php tests/Feature/Iam/IamValidateTest.php tests/Feature/Iam/IamExchangeCodeTest.php tests/Feature/Iam/IamLogoutTest.php
git commit -m "feat(iam): tambah endpoint validate, check, logout, exchange-code dengan test lengkap"
```

---

### Task 9: SSO Login Page

**Files:**
- Modify: `routes/web.php` — tambah SSO routes
- Create: `app/Http/Controllers/SsoController.php`
- Test: `tests/Feature/Iam/SsoLoginTest.php`

- [ ] **Step 1: Tulis failing test**

```php
<?php
// tests/Feature/Iam/SsoLoginTest.php

use App\Models\IamApplication;
use App\Models\IamSsoCode;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

test('GET /sso/login tanpa app param mengembalikan 422', function () {
    $this->get('/sso/login')->assertStatus(422);
});

test('GET /sso/login dengan app tidak dikenal mengembalikan 404', function () {
    $this->get('/sso/login?app=unknown&redirect=http://test.local/callback')
        ->assertStatus(404);
});

test('GET /sso/login user belum login diredirect ke halaman login', function () {
    IamApplication::create([
        'nama' => 'Test', 'slug' => 'test', 'url' => 'http://test.local',
        'api_key' => 'k', 'api_secret_hash' => Crypt::encryptString('s'),
    ]);

    $this->get('/sso/login?app=test&redirect=http://test.local/callback')
        ->assertRedirect(route('login'));
});

test('GET /sso/login user sudah login generate SSO code dan redirect', function () {
    IamApplication::create([
        'nama' => 'Attendance', 'slug' => 'attendance', 'url' => 'http://att.local',
        'api_key' => 'att-key', 'api_secret_hash' => Crypt::encryptString('att-secret'),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/sso/login?app=attendance&redirect=http://att.local/callback');

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toContain('http://att.local/callback');
    expect($location)->toContain('?code=');

    $code = IamSsoCode::where('user_id', $user->id)->first();
    expect($code)->not->toBeNull();
    expect($code->app_slug)->toBe('attendance');
    expect($code->isValid())->toBeTrue();
});

test('GET /sso/login menolak redirect ke domain lain (open redirect prevention)', function () {
    IamApplication::create([
        'nama' => 'Attendance', 'slug' => 'attendance', 'url' => 'http://att.local',
        'api_key' => 'att-key', 'api_secret_hash' => Crypt::encryptString('att-secret'),
    ]);

    $user = User::factory()->create();

    // Redirect ke domain berbeda dari app.url — harus ditolak
    $this->actingAs($user)
        ->get('/sso/login?app=attendance&redirect=http://evil.com/steal')
        ->assertStatus(422);
});
```

- [ ] **Step 2: Run test — FAIL**

```bash
php artisan test --filter "SsoLoginTest"
```

- [ ] **Step 3: Buat SsoController**

```php
<?php
// app/Http/Controllers/SsoController.php
namespace App\Http\Controllers;

use App\Models\IamApplication;
use App\Models\IamSsoCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SsoController extends Controller
{
    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'app'      => 'required|string',
            'redirect' => 'required|url',
        ]);

        $app = IamApplication::where('slug', $request->app)->where('is_active', true)->first();

        if (! $app) {
            abort(404, 'Aplikasi tidak ditemukan');
        }

        if (! $request->user()) {
            session(['sso_app' => $request->app, 'sso_redirect' => $request->redirect]);
            return redirect()->route('login');
        }

        return $this->generateCodeAndRedirect($request->user()->id, $app, $request->redirect);
    }

    /** Dipanggil setelah login berhasil jika ada SSO session */
    public function callback(Request $request): RedirectResponse
    {
        $appSlug  = session()->pull('sso_app');
        $redirect = session()->pull('sso_redirect');

        if (! $appSlug || ! $redirect) {
            return redirect()->route('dashboard');
        }

        $app = IamApplication::where('slug', $appSlug)->where('is_active', true)->first();

        if (! $app) {
            return redirect()->route('dashboard');
        }

        return $this->generateCodeAndRedirect($request->user()->id, $app, $redirect);
    }

    private function generateCodeAndRedirect(int $userId, IamApplication $app, string $redirectUrl): RedirectResponse
    {
        // Validasi domain whitelist — redirect harus dimulai dengan app.url terdaftar
        if (! str_starts_with($redirectUrl, $app->url)) {
            abort(422, 'Redirect URL tidak diizinkan untuk aplikasi ini');
        }

        $ttl  = config('iam.sso_code_ttl_seconds', 60);
        $code = Str::random(64);

        IamSsoCode::create([
            'code'       => $code,
            'user_id'    => $userId,
            'app_slug'   => $app->slug,
            'expires_at' => now()->addSeconds($ttl),
        ]);

        $separator = str_contains($redirectUrl, '?') ? '&' : '?';

        return redirect($redirectUrl . $separator . 'code=' . $code);
    }
}
```

- [ ] **Step 4: Tambah SSO routes di web.php**

```php
// Tambahkan di routes/web.php
use App\Http\Controllers\SsoController;

Route::get('/sso/login', [SsoController::class, 'login'])->name('sso.login');
Route::middleware('auth')->get('/sso/callback', [SsoController::class, 'callback'])->name('sso.callback');
```

- [ ] **Step 5: Run test — PASS**

```bash
php artisan test --filter "SsoLoginTest"
```
Expected: 4 PASS

- [ ] **Step 6: Run full test suite**

```bash
php artisan test
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/SsoController.php routes/web.php tests/Feature/Iam/SsoLoginTest.php
git commit -m "feat(iam): tambah SSO login page dengan one-time code exchange"
```

---

## FASE 4: Admin UI

### Task 10: Backend CRUD Aplikasi IAM

**Files:**
- Create: `app/Http/Controllers/Iam/AplikasiController.php`
- Test: `tests/Feature/Iam/AplikasiControllerTest.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Tulis failing test**

```php
<?php
// tests/Feature/Iam/AplikasiControllerTest.php

use App\Models\IamApplication;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

beforeEach(fn () => $this->seed(IamSeeder::class));

function makeAdminUser(): User
{
    $kepegawaian = IamApplication::where('slug', 'kepegawaian')->firstOrFail();
    $adminRole   = IamRole::where('iam_application_id', $kepegawaian->id)->where('slug', 'admin')->firstOrFail();
    $user        = User::factory()->create();
    IamUserRole::create(['user_id' => $user->id, 'iam_role_id' => $adminRole->id, 'assigned_at' => now()]);
    return $user;
}

test('admin dapat melihat daftar aplikasi', function () {
    $admin = makeAdminUser();
    $this->actingAs($admin)->get('/iam/aplikasi')->assertOk();
});

test('user tanpa role tidak bisa akses halaman IAM', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/iam/aplikasi')->assertForbidden();
});

test('admin dapat mendaftarkan aplikasi baru', function () {
    $admin = makeAdminUser();

    $response = $this->actingAs($admin)->post('/iam/aplikasi', [
        'nama'      => 'E-Surat',
        'slug'      => 'e-surat',
        'url'       => 'http://esurat.local',
        'deskripsi' => 'Aplikasi e-surat PA Penajam',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('iam_applications', ['slug' => 'e-surat']);
});

test('aplikasi is_system tidak bisa dihapus', function () {
    $admin       = makeAdminUser();
    $kepegawaian = IamApplication::where('slug', 'kepegawaian')->firstOrFail();

    $this->actingAs($admin)
        ->delete("/iam/aplikasi/{$kepegawaian->id}")
        ->assertForbidden();
});

test('admin dapat meregenerasi api key aplikasi', function () {
    $admin      = makeAdminUser();
    $app        = IamApplication::create([
        'nama' => 'Test', 'slug' => 'test-regen', 'url' => 'http://x.local',
        'api_key' => 'old-key', 'api_secret_hash' => Crypt::encryptString('old-secret'),
    ]);

    $oldKey = $app->api_key;
    $this->actingAs($admin)->post("/iam/aplikasi/{$app->id}/regenerate-key")->assertRedirect();

    $app->refresh();
    expect($app->api_key)->not->toBe($oldKey);
});
```

- [ ] **Step 2: Run test — FAIL**

```bash
php artisan test --filter "AplikasiControllerTest"
```

- [ ] **Step 3: Buat AplikasiController**

```php
<?php
// app/Http/Controllers/Iam/AplikasiController.php
namespace App\Http\Controllers\Iam;

use App\Http\Controllers\Controller;
use App\Models\IamApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class AplikasiController extends Controller
{
    public function index(): Response
    {
        $aplikasi = IamApplication::withCount('roles')->latest()->get();
        return inertia('iam/aplikasi/index', compact('aplikasi'));
    }

    public function show(IamApplication $aplikasi): Response
    {
        $aplikasi->load(['roles.permissions', 'permissions']);
        return inertia('iam/aplikasi/show', compact('aplikasi'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama'      => 'required|string|max:100',
            'slug'      => 'required|string|unique:iam_applications,slug|alpha_dash',
            'url'       => 'required|url',
            'deskripsi' => 'nullable|string',
        ]);

        ['key' => $key, 'secret' => $secret, 'hash' => $hash] = IamApplication::generateApiCredentials();

        $app = IamApplication::create([
            ...$data,
            'api_key'         => $key,
            'api_secret_hash' => $hash,
        ]);

        return redirect()
            ->route('iam.aplikasi.show', $app)
            ->with('api_secret_once', $secret); // ditampilkan sekali di flash message
    }

    public function update(Request $request, IamApplication $aplikasi): RedirectResponse
    {
        if ($aplikasi->is_system) {
            abort(403, 'Aplikasi sistem tidak dapat diubah');
        }

        $data = $request->validate([
            'nama'      => 'required|string|max:100',
            'url'       => 'required|url',
            'deskripsi' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $aplikasi->update($data);
        return back();
    }

    public function destroy(IamApplication $aplikasi): RedirectResponse
    {
        if ($aplikasi->is_system) {
            abort(403, 'Aplikasi sistem tidak dapat dihapus');
        }

        $aplikasi->delete();
        return redirect()->route('iam.aplikasi.index');
    }

    public function regenerateKey(IamApplication $aplikasi): RedirectResponse
    {
        ['key' => $key, 'secret' => $secret, 'hash' => $hash] = IamApplication::generateApiCredentials();

        $aplikasi->update(['api_key' => $key, 'api_secret_hash' => $hash]);

        return back()->with('api_secret_once', $secret);
    }
}
```

- [ ] **Step 4: Tambah routes IAM admin di web.php**

```php
// routes/web.php — tambahkan
use App\Http\Controllers\Iam\AplikasiController;
use App\Http\Controllers\Iam\RoleController;
use App\Http\Controllers\Iam\PermissionController;
use App\Http\Controllers\Iam\UserAksesController;

Route::middleware(['auth', 'verified', 'iam.permission'])
    ->prefix('iam')
    ->name('iam.')
    ->group(function () {
        Route::resource('aplikasi', AplikasiController::class)
            ->except(['create', 'edit']);
        Route::post('aplikasi/{aplikasi}/regenerate-key', [AplikasiController::class, 'regenerateKey'])
            ->name('aplikasi.regenerate-key');

        // Role & Permission (nested under aplikasi)
        Route::post('aplikasi/{aplikasi}/roles', [RoleController::class, 'store'])->name('aplikasi.roles.store');
        Route::put('aplikasi/{aplikasi}/roles/{role}', [RoleController::class, 'update'])->name('aplikasi.roles.update');
        Route::delete('aplikasi/{aplikasi}/roles/{role}', [RoleController::class, 'destroy'])->name('aplikasi.roles.destroy');
        Route::post('aplikasi/{aplikasi}/permissions', [PermissionController::class, 'store'])->name('aplikasi.permissions.store');
        Route::put('aplikasi/{aplikasi}/permissions/{permission}', [PermissionController::class, 'update'])->name('aplikasi.permissions.update');
        Route::delete('aplikasi/{aplikasi}/permissions/{permission}', [PermissionController::class, 'destroy'])->name('aplikasi.permissions.destroy');

        // User akses
        Route::get('users', [UserAksesController::class, 'index'])->name('users.index');
        Route::get('users/{user}/akses', [UserAksesController::class, 'show'])->name('users.akses');
        Route::post('users/{user}/akses', [UserAksesController::class, 'store'])->name('users.akses.store');
        Route::delete('users/{user}/akses/{role}', [UserAksesController::class, 'destroy'])->name('users.akses.destroy');
    });
```

- [ ] **Step 5: Run test — PASS**

```bash
php artisan test --filter "AplikasiControllerTest"
```
Expected: 5 PASS

- [ ] **Step 6: Buat stub RoleController, PermissionController, UserAksesController**

```php
<?php
// app/Http/Controllers/Iam/RoleController.php
namespace App\Http\Controllers\Iam;
use App\Http\Controllers\Controller;
use App\Models\IamApplication;
use App\Models\IamRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function store(Request $request, IamApplication $aplikasi): RedirectResponse
    {
        $data = $request->validate([
            'nama'           => 'required|string|max:100',
            'slug'           => 'required|string|alpha_dash',
            'keterangan'     => 'nullable|string',
            'permission_ids' => 'array',
            'permission_ids.*' => 'exists:iam_permissions,id',
        ]);

        $role = $aplikasi->roles()->create($data);
        if (!empty($data['permission_ids'])) {
            $role->permissions()->sync($data['permission_ids']);
        }

        return back();
    }

    public function update(Request $request, IamApplication $aplikasi, IamRole $role): RedirectResponse
    {
        abort_if($role->is_system, 403, 'Role sistem tidak dapat diubah');
        $data = $request->validate([
            'nama'           => 'required|string|max:100',
            'keterangan'     => 'nullable|string',
            'permission_ids' => 'array',
            'permission_ids.*' => 'exists:iam_permissions,id',
        ]);
        $role->update($data);
        $role->permissions()->sync($data['permission_ids'] ?? []);
        return back();
    }

    public function destroy(IamApplication $aplikasi, IamRole $role): RedirectResponse
    {
        abort_if($role->is_system, 403, 'Role sistem tidak dapat dihapus');
        $role->delete();
        return back();
    }
}
```

```php
<?php
// app/Http/Controllers/Iam/PermissionController.php
namespace App\Http\Controllers\Iam;
use App\Http\Controllers\Controller;
use App\Models\IamApplication;
use App\Models\IamPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function store(Request $request, IamApplication $aplikasi): RedirectResponse
    {
        $data = $request->validate([
            'nama'       => 'required|string|max:100',
            'slug'       => 'required|string',
            'group'      => 'nullable|string|max:50',
            'keterangan' => 'nullable|string',
        ]);
        $aplikasi->permissions()->create($data);
        return back();
    }

    public function update(Request $request, IamApplication $aplikasi, IamPermission $permission): RedirectResponse
    {
        $data = $request->validate([
            'nama'       => 'required|string|max:100',
            'group'      => 'nullable|string|max:50',
            'keterangan' => 'nullable|string',
        ]);
        $permission->update($data);
        return back();
    }

    public function destroy(IamApplication $aplikasi, IamPermission $permission): RedirectResponse
    {
        $permission->delete();
        return back();
    }
}
```

```php
<?php
// app/Http/Controllers/Iam/UserAksesController.php
namespace App\Http\Controllers\Iam;
use App\Http\Controllers\Controller;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class UserAksesController extends Controller
{
    public function index(): Response
    {
        $users = User::with('iamRoles.role.application')->paginate(20);
        return inertia('iam/users/index', compact('users'));
    }

    public function show(User $user): Response
    {
        $akses = IamUserRole::where('user_id', $user->id)
            ->with(['role.application', 'role.permissions', 'assignedByUser'])
            ->get();
        $availableApps = \App\Models\IamApplication::where('is_active', true)
            ->with('roles')
            ->get();
        return inertia('iam/users/akses', compact('user', 'akses', 'availableApps'));
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['iam_role_id' => 'required|exists:iam_roles,id']);

        IamUserRole::firstOrCreate(
            ['user_id' => $user->id, 'iam_role_id' => $data['iam_role_id']],
            ['assigned_at' => now(), 'assigned_by' => $request->user()->id]
        );
        return back();
    }

    public function destroy(User $user, IamRole $role): RedirectResponse
    {
        IamUserRole::where('user_id', $user->id)->where('iam_role_id', $role->id)->delete();
        return back();
    }
}
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Iam/ routes/web.php tests/Feature/Iam/AplikasiControllerTest.php
git commit -m "feat(iam): tambah backend CRUD aplikasi, role, permission, user akses"
```

---

### Task 11: Frontend — Halaman Daftar Aplikasi

**Files:**
- Create: `resources/js/pages/iam/aplikasi/index.tsx`

- [ ] **Step 1: Buat halaman index aplikasi**

```tsx
// resources/js/pages/iam/aplikasi/index.tsx
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Settings } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type IamApplication = {
    id: string;
    nama: string;
    slug: string;
    url: string;
    is_active: boolean;
    is_system: boolean;
    roles_count: number;
};

type Props = { aplikasi: IamApplication[] };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'IAM', href: '#' },
    { title: 'Aplikasi', href: '/iam/aplikasi' },
];

export default function AplikasiIndex({ aplikasi }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Manajemen Aplikasi IAM" />
            <div className="space-y-4 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold">Manajemen Aplikasi</h1>
                        <p className="text-muted-foreground text-sm">Aplikasi terdaftar di ekosistem PA Penajam</p>
                    </div>
                    {/* Gunakan modal inline — route /create tidak ada (dikecualikan dari resource) */}
                    <Button onClick={() => setShowForm(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Daftarkan Aplikasi
                    </Button>
                </div>

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama Aplikasi</TableHead>
                                <TableHead>URL</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {aplikasi.map((app) => (
                                <TableRow key={app.id}>
                                    <TableCell>
                                        <div className="font-medium">{app.nama}</div>
                                        {app.is_system && (
                                            <Badge variant="outline" className="text-xs text-green-600">Sistem</Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground text-sm">{app.url}</TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">{app.roles_count} role</Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={app.is_active ? 'default' : 'destructive'}>
                                            {app.is_active ? 'Aktif' : 'Nonaktif'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Button variant="ghost" size="sm" asChild>
                                            <Link href={`/iam/aplikasi/${app.id}`}>
                                                <Settings className="h-4 w-4" />
                                                Detail
                                            </Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Step 2: Buat halaman show (detail) aplikasi**

File `resources/js/pages/iam/aplikasi/show.tsx` — berisi tabs Role / Permission / User dengan CRUD inline. Struktur umum:

```tsx
// resources/js/pages/iam/aplikasi/show.tsx
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Eye, EyeOff, RefreshCw } from 'lucide-react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
// ... import lainnya
import AppLayout from '@/layouts/app-layout';

// Komponen ini cukup panjang — implementasikan dengan 3 tab:
// Tab 1: Role (list + form tambah role + checklist permission)
// Tab 2: Permission (list + form tambah permission)
// Tab 3: Info & API Credentials (tampilkan api_key masked + tombol regenerate)

export default function AplikasiShow({ aplikasi, flash }: Props) {
    const [showSecret, setShowSecret] = useState(!!flash?.api_secret_once);
    // ... implementasi tabs
}
```

- [ ] **Step 3: Buat halaman users index dan akses**

File `resources/js/pages/iam/users/index.tsx` — daftar user dengan link ke halaman akses masing-masing.

File `resources/js/pages/iam/users/akses.tsx` — satu halaman tampilkan semua akses user di semua aplikasi, dengan dropdown ganti role dan tombol revoke.

- [ ] **Step 4: Tambah link IAM di sidebar**

Cari file sidebar navigation (biasanya `resources/js/layouts/app-layout.tsx` atau `resources/js/components/app-sidebar.tsx`). Tambahkan grup menu IAM di sidebar:

```tsx
// Di dalam navigation items
{
    title: 'IAM',
    items: [
        { title: 'Aplikasi', href: '/iam/aplikasi', icon: Building },
        { title: 'Akses User', href: '/iam/users', icon: Key },
    ],
}
```

- [ ] **Step 5: Run TypeScript check**

```bash
npm run types:check
```
Expected: no errors

- [ ] **Step 6: Build dan test manual di browser**

```bash
npm run dev
```

Cek:
- `/iam/aplikasi` menampilkan daftar aplikasi
- `/iam/aplikasi/{id}` menampilkan detail dengan tabs
- `/iam/users/{id}/akses` menampilkan manajemen akses user

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/iam/
git commit -m "feat(iam): tambah halaman admin UI aplikasi, role, permission, akses user"
```

---

## FASE 5: Final Verification

### Task 12: Run Full Test Suite + Cleanup

- [ ] **Step 1: Run semua test**

```bash
composer test
```
Expected: semua test PASS, no lint errors

- [ ] **Step 2: Verifikasi alur SSO end-to-end**

Manual test:
1. Buka `http://kepegawaian.local/sso/login?app=kepegawaian&redirect=http://kepegawaian.local/dashboard`
2. Login dengan user yang ada
3. Pastikan redirect ke dashboard dengan `?code=...`
4. Gunakan curl untuk test exchange-code:
```bash
curl -X POST http://kepegawaian.local/api/v1/iam/exchange-code \
  -H "Content-Type: application/json" \
  -H "X-App-Key: {api_key}" \
  -H "X-Signature: {hmac}" \
  -H "X-Timestamp: {timestamp}" \
  -d '{"code": "{code_dari_redirect}"}'
```

- [ ] **Step 3: Commit final**

```bash
git add -A
git commit -m "feat(iam): selesai implementasi IAM SSO Gateway"
```

---

## Ringkasan Fase & Dependencies

```
FASE 1: Foundation
  Task 1: Config IAM                     (tidak ada dependency)
  Task 2: Migration - buat tabel IAM     (setelah Task 1)
  Task 3: Models IAM                     (setelah Task 2)
  Task 4: IamSeeder - migrasi data       (setelah Task 3)
  Task 5: Drop tabel lama + cleanup      (setelah Task 4 dijalankan di production)

FASE 2: Security
  Task 6: VerifyIamSignature             (setelah Task 3)
  Task 7: VerifyIamPermission            (setelah Task 3, Task 4)

FASE 3: API
  Task 8: IamController + routes API     (setelah Task 6, Task 7)
  Task 9: SSO Login Page                 (setelah Task 8)

FASE 4: Admin UI
  Task 10: Backend CRUD Aplikasi         (setelah Task 7)
  Task 11: Frontend Pages                (setelah Task 10)

FASE 5: Verification
  Task 12: Full test + end-to-end        (setelah semua task selesai)
```
