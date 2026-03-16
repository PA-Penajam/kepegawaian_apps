# Pegawai sebagai User — Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengganti arsitektur autentikasi dari model `User` terpisah menjadi model `Pegawai` sebagai Authenticatable, sehingga pegawai bisa langsung login via NIP + password dan di-assign role via many-to-many pivot.

**Architecture:** Model `Pegawai` menjadi `Authenticatable` (menggantikan `User`). Role di-assign via tabel pivot `pegawai_role` (many-to-many). Login via NIP + password. Menu Pengguna dihapus, diganti fitur assign pegawai di Edit Role dan set password di Edit Pegawai.

**Tech Stack:** Laravel 12, Fortify, Inertia.js, React, TypeScript, MySQL, Pest

**Spec:** `docs/superpowers/specs/2026-03-16-pegawai-as-user-design.md`

---

## Chunk 1: Database & Model Layer

### Task 1: Migration — Tambah kolom auth ke tabel pegawai & buat pivot pegawai_role

**Files:**
- Create: `database/migrations/2026_03_16_060000_convert_pegawai_to_authenticatable.php`

- [ ] **Step 1: Write the failing test — migration berjalan tanpa error**

```php
// tests/Feature/Migrations/PegawaiAuthMigrationTest.php
<?php

use Illuminate\Support\Facades\Schema;

test('pegawai table has auth columns after migration', function () {
    expect(Schema::hasColumn('pegawai', 'password'))->toBeTrue();
    expect(Schema::hasColumn('pegawai', 'remember_token'))->toBeTrue();
    expect(Schema::hasColumn('pegawai', 'two_factor_secret'))->toBeTrue();
    expect(Schema::hasColumn('pegawai', 'two_factor_recovery_codes'))->toBeTrue();
    expect(Schema::hasColumn('pegawai', 'two_factor_confirmed_at'))->toBeTrue();
});

test('pegawai_role pivot table exists', function () {
    expect(Schema::hasTable('pegawai_role'))->toBeTrue();
    expect(Schema::hasColumns('pegawai_role', ['pegawai_id', 'ref_role_id', 'created_at']))->toBeTrue();
});

test('users table no longer exists', function () {
    expect(Schema::hasTable('users'))->toBeFalse();
});

test('sessions table user_id is varchar', function () {
    $column = Schema::getColumnType('sessions', 'user_id');
    expect($column)->toBe('string');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Migrations/PegawaiAuthMigrationTest.php`
Expected: FAIL — pegawai belum punya kolom password, pivot belum ada

- [ ] **Step 3: Write migration**

```php
// database/migrations/2026_03_16_060000_convert_pegawai_to_authenticatable.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom auth ke pegawai
        Schema::table('pegawai', function (Blueprint $table) {
            $table->string('password')->nullable()->after('keterangan');
            $table->rememberToken()->after('password');
            $table->text('two_factor_secret')->nullable()->after('remember_token');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });

        // 2. Buat pivot pegawai_role
        Schema::create('pegawai_role', function (Blueprint $table) {
            $table->char('pegawai_id', 26);
            $table->char('ref_role_id', 26);
            $table->timestamp('created_at')->nullable();

            $table->primary(['pegawai_id', 'ref_role_id']);
            $table->foreign('pegawai_id')->references('id')->on('pegawai')->cascadeOnDelete();
            $table->foreign('ref_role_id')->references('id')->on('ref_roles')->cascadeOnDelete();
        });

        // 3. Migrasi data dari users ke pegawai
        $users = DB::table('users')->get();
        foreach ($users as $user) {
            if ($user->pegawai_id) {
                // User sudah punya pegawai → copy auth data
                DB::table('pegawai')->where('id', $user->pegawai_id)->update([
                    'password' => $user->password,
                    'remember_token' => $user->remember_token,
                    'two_factor_secret' => $user->two_factor_secret,
                    'two_factor_recovery_codes' => $user->two_factor_recovery_codes,
                    'two_factor_confirmed_at' => $user->two_factor_confirmed_at,
                    'email_verified_at' => $user->email_verified_at,
                ]);

                // Copy role ke pivot
                if ($user->ref_role_id) {
                    DB::table('pegawai_role')->insert([
                        'pegawai_id' => $user->pegawai_id,
                        'ref_role_id' => $user->ref_role_id,
                        'created_at' => now(),
                    ]);
                }
            } else {
                // User tanpa pegawai → buat pegawai baru
                $pegawaiId = (string) \Illuminate\Support\Str::ulid();
                DB::table('pegawai')->insert([
                    'id' => $pegawaiId,
                    'nama_lengkap' => $user->name,
                    'tempat_lahir' => 'Tidak Diketahui',
                    'tanggal_lahir' => '1970-01-01',
                    'jenis_kelamin' => 'Laki-Laki',
                    'agama' => 'Islam',
                    'status_perkawinan' => 'Belum_Kawin',
                    'status_kepegawaian' => 'PNS',
                    'status_pegawai' => 'Aktif',
                    'tanggal_masuk' => now()->toDateString(),
                    'email' => $user->email,
                    'nip' => 'TEMP-' . $user->id,
                    'password' => $user->password,
                    'remember_token' => $user->remember_token,
                    'two_factor_secret' => $user->two_factor_secret,
                    'two_factor_recovery_codes' => $user->two_factor_recovery_codes,
                    'two_factor_confirmed_at' => $user->two_factor_confirmed_at,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($user->ref_role_id) {
                    DB::table('pegawai_role')->insert([
                        'pegawai_id' => $pegawaiId,
                        'ref_role_id' => $user->ref_role_id,
                        'created_at' => now(),
                    ]);
                }
            }
        }

        // 4. Update sessions table — ubah user_id ke varchar untuk support ULID
        Schema::table('sessions', function (Blueprint $table) {
            $table->string('user_id', 26)->nullable()->change();
        });

        // 5. Hapus tabel users
        Schema::dropIfExists('users');
    }

    public function down(): void
    {
        // Recreate users table
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('pegawai_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->char('ref_role_id', 26)->nullable();
            $table->timestamps();

            $table->foreign('pegawai_id')->references('id')->on('pegawai')->nullOnDelete();
            $table->foreign('ref_role_id')->references('id')->on('ref_roles')->nullOnDelete();
        });

        Schema::dropIfExists('pegawai_role');

        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropColumn([
                'password', 'remember_token',
                'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
                'email_verified_at',
            ]);
        });
    }
};
```

- [ ] **Step 4: Run migration and verify test passes**

Run: `php artisan migrate && php artisan test tests/Feature/Migrations/PegawaiAuthMigrationTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_03_16_060000_convert_pegawai_to_authenticatable.php tests/Feature/Migrations/PegawaiAuthMigrationTest.php
git commit -m "feat: add migration to convert pegawai to authenticatable"
```

---

### Task 2: Update Model Pegawai — Authenticatable + roles relationship

**Files:**
- Modify: `app/Models/Pegawai.php`
- Modify: `database/factories/PegawaiFactory.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Models/PegawaiAuthTest.php
<?php

use App\Models\Pegawai;
use App\Models\RefRole;
use Illuminate\Foundation\Auth\User as Authenticatable;

test('pegawai is authenticatable', function () {
    expect(new Pegawai())->toBeInstanceOf(Authenticatable::class);
});

test('pegawai has roles many-to-many relationship', function () {
    $pegawai = Pegawai::factory()->create();
    $role = RefRole::query()->where('nama', 'Admin')->first();

    $pegawai->roles()->attach($role->id);

    expect($pegawai->roles)->toHaveCount(1);
    expect($pegawai->roles->first()->nama)->toBe('Admin');
});

test('pegawai can have multiple roles', function () {
    $pegawai = Pegawai::factory()->create();
    $admin = RefRole::query()->where('nama', 'Admin')->first();
    $operator = RefRole::query()->where('nama', 'Operator')->first();

    $pegawai->roles()->attach([$admin->id, $operator->id]);

    expect($pegawai->roles)->toHaveCount(2);
});

test('pegawai hasPermission checks all roles', function () {
    $pegawai = Pegawai::factory()->create();
    $admin = RefRole::query()->where('nama', 'Admin')->first();
    $pegawai->roles()->attach($admin->id);

    // Admin biasanya punya permission 'pegawai.view'
    expect($pegawai->hasPermission('pegawai.view'))->toBeTrue();
    expect($pegawai->hasPermission('nonexistent.permission'))->toBeFalse();
});

test('pegawai hasAnyPermission checks across all roles', function () {
    $pegawai = Pegawai::factory()->create();
    $admin = RefRole::query()->where('nama', 'Admin')->first();
    $pegawai->roles()->attach($admin->id);

    expect($pegawai->hasAnyPermission('pegawai.view', 'nonexistent'))->toBeTrue();
    expect($pegawai->hasAnyPermission('nonexistent1', 'nonexistent2'))->toBeFalse();
});

test('pegawai isAdmin checks roles', function () {
    $pegawai = Pegawai::factory()->create();
    expect($pegawai->isAdmin())->toBeFalse();

    $admin = RefRole::query()->where('nama', 'Admin')->first();
    $pegawai->roles()->attach($admin->id);
    $pegawai->unsetRelation('roles');

    expect($pegawai->isAdmin())->toBeTrue();
});

test('pegawai password is hidden', function () {
    $pegawai = Pegawai::factory()->create(['password' => 'secret']);
    $array = $pegawai->toArray();

    expect($array)->not->toHaveKey('password');
    expect($array)->not->toHaveKey('remember_token');
});

test('pegawai can authenticate with nip and password', function () {
    $pegawai = Pegawai::factory()->create([
        'nip' => '198501012010011001',
        'password' => bcrypt('password123'),
    ]);

    expect(auth()->attempt(['nip' => '198501012010011001', 'password' => 'password123']))->toBeTrue();
    expect(auth()->user()->id)->toBe($pegawai->id);
});

test('pegawai with null password cannot authenticate', function () {
    Pegawai::factory()->create([
        'nip' => '198501012010011002',
        'password' => null,
    ]);

    expect(auth()->attempt(['nip' => '198501012010011002', 'password' => 'anything']))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Models/PegawaiAuthTest.php`
Expected: FAIL — Pegawai belum extends Authenticatable

- [ ] **Step 3: Update Pegawai model**

File: `app/Models/Pegawai.php` — Ubah parent class dan tambah relasi/method:

```php
<?php

namespace App\Models;

use App\Enums\Agama;
use App\Enums\GolonganDarah;
use App\Enums\JenisKelamin;
use App\Enums\StatusKepegawaian;
use App\Enums\StatusPegawai;
use App\Enums\StatusPerkawinan;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class Pegawai extends Authenticatable
{
    use Filterable, HasFactory, HasUlids, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected $table = 'pegawai';

    protected $fillable = [
        'nip', 'nip_lama', 'nama_lengkap', 'tempat_lahir', 'tanggal_lahir',
        'jenis_kelamin', 'agama', 'status_perkawinan', 'golongan_darah',
        'alamat', 'no_telepon', 'email', 'status_kepegawaian', 'status_pegawai',
        'tmt_cpns', 'tmt_pns', 'pendidikan_terakhir', 'tanggal_masuk',
        'tanggal_pensiun_bup', 'ref_pangkat_id', 'ref_jabatan_id', 'ref_unit_kerja_id',
        'no_karpeg', 'no_karis_karsu', 'npwp', 'no_bpjs_kesehatan',
        'no_bpjs_ketenagakerjaan', 'no_taspen', 'foto', 'keterangan',
        'password',
    ];

    protected $hidden = [
        'password', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'jenis_kelamin' => JenisKelamin::class,
            'agama' => Agama::class,
            'status_perkawinan' => StatusPerkawinan::class,
            'golongan_darah' => GolonganDarah::class,
            'status_kepegawaian' => StatusKepegawaian::class,
            'status_pegawai' => StatusPegawai::class,
            'tmt_cpns' => 'date',
            'tmt_pns' => 'date',
            'tanggal_masuk' => 'date',
            'tanggal_pensiun_bup' => 'date',
            'deleted_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }

    // === Relasi referensi ===

    public function pangkat(): BelongsTo
    {
        return $this->belongsTo(RefPangkat::class, 'ref_pangkat_id');
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(RefJabatan::class, 'ref_jabatan_id');
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(RefUnitKerja::class, 'ref_unit_kerja_id');
    }

    // === Relasi RBAC (many-to-many) ===

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(RefRole::class, 'pegawai_role', 'pegawai_id', 'ref_role_id')
            ->withPivot('created_at');
    }

    // === Relasi riwayat ===

    public function riwayatJabatan(): HasMany
    {
        return $this->hasMany(RiwayatJabatan::class, 'pegawai_id');
    }

    public function riwayatDiklat(): HasMany
    {
        return $this->hasMany('App\\Models\\RiwayatDiklat', 'pegawai_id');
    }

    public function riwayatPendidikan(): HasMany
    {
        return $this->hasMany('App\\Models\\RiwayatPendidikan', 'pegawai_id');
    }

    public function riwayatPangkat(): HasMany
    {
        return $this->hasMany('App\\Models\\RiwayatPangkat', 'pegawai_id');
    }

    public function dokumenPegawai(): HasMany
    {
        return $this->hasMany(DokumenPegawai::class, 'pegawai_id');
    }

    public function keluarga(): HasMany
    {
        return $this->hasMany(Keluarga::class, 'pegawai_id');
    }

    public function penghargaan(): HasMany
    {
        return $this->hasMany(Penghargaan::class, 'pegawai_id');
    }

    public function hukumanDisiplin(): HasMany
    {
        return $this->hasMany(HukumanDisiplin::class, 'pegawai_id');
    }

    // === Permission methods (multi-role aware) ===

    public function hasPermission(string $permission): bool
    {
        return $this->roles()->whereHas('permissions', function (Builder $q) use ($permission) {
            $q->where('nama', $permission);
        })->exists();
    }

    public function hasAnyPermission(string ...$permissions): bool
    {
        return $this->roles()->whereHas('permissions', function (Builder $q) use ($permissions) {
            $q->whereIn('nama', $permissions);
        })->exists();
    }

    public function isAdmin(): bool
    {
        return $this->roles()->where('nama', 'Admin')->exists();
    }

    public function isOperator(): bool
    {
        return $this->roles()->where('nama', 'Operator')->exists();
    }

    // === Notifikasi ===

    public function routeNotificationForMail(): ?string
    {
        return $this->email;
    }

    // === Scopes ===

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status_pegawai', StatusPegawai::Aktif->value);
    }

    public function scopeByUnitKerja(Builder $query, string $id): Builder
    {
        return $query->where('ref_unit_kerja_id', $id);
    }

    public function scopeByGolongan(Builder $query, string $golongan): Builder
    {
        return $query->whereHas('pangkat', function (Builder $pangkatQuery) use ($golongan): void {
            $pangkatQuery
                ->where('kode', $golongan)
                ->orWhere('golongan', $golongan);
        });
    }

    // === Accessors ===

    public function getNamaPangkatLengkapAttribute(): string
    {
        if ($this->pangkat === null) {
            return '';
        }

        return sprintf('%s - %s', $this->pangkat->nama, $this->pangkat->kode);
    }
}
```

- [ ] **Step 4: Update PegawaiFactory — tambah auth fields**

File: `database/factories/PegawaiFactory.php` — Tambah `password`, `nip` auto-generate, dan state methods:

Tambahkan ke method `definition()`:
```php
'password' => static::$password ??= Hash::make('password'),
'nip' => fake()->unique()->numerify('##################'),
'email_verified_at' => now(),
```

Tambahkan property dan state methods:
```php
protected static ?string $password;

public function admin(): static
{
    return $this->afterCreating(function (Pegawai $pegawai) {
        $role = RefRole::query()->where('nama', 'Admin')->first();
        if ($role) $pegawai->roles()->syncWithoutDetaching([$role->id]);
    });
}

public function operator(): static
{
    return $this->afterCreating(function (Pegawai $pegawai) {
        $role = RefRole::query()->where('nama', 'Operator')->first();
        if ($role) $pegawai->roles()->syncWithoutDetaching([$role->id]);
    });
}

public function viewer(): static
{
    return $this->afterCreating(function (Pegawai $pegawai) {
        $role = RefRole::query()->where('nama', 'Viewer')->first();
        if ($role) $pegawai->roles()->syncWithoutDetaching([$role->id]);
    });
}

public function withTwoFactor(): static
{
    return $this->state(fn () => [
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
        'two_factor_confirmed_at' => now(),
    ]);
}
```

- [ ] **Step 5: Update RefRole model — ubah users() ke pegawai()**

File: `app/Models/RefRole.php`:
```php
// Hapus method users(): HasMany
// Ganti dengan:
public function pegawai(): BelongsToMany
{
    return $this->belongsToMany(Pegawai::class, 'pegawai_role', 'ref_role_id', 'pegawai_id')
        ->withPivot('created_at');
}
```

- [ ] **Step 6: Update config/auth.php — model ke Pegawai**

File: `config/auth.php`:
```php
// Baris 2: ganti use statement
use App\Models\Pegawai;

// Baris 67: ganti model
'model' => Pegawai::class,
```

- [ ] **Step 7: Run tests and verify**

Run: `php artisan test tests/Feature/Models/PegawaiAuthTest.php`
Expected: ALL PASS

- [ ] **Step 8: Commit**

```bash
git add app/Models/Pegawai.php app/Models/RefRole.php database/factories/PegawaiFactory.php config/auth.php tests/Feature/Models/PegawaiAuthTest.php
git commit -m "feat: convert Pegawai to Authenticatable with multi-role support"
```

---

## Chunk 2: Authentication Layer

### Task 3: Update Fortify — login via NIP

**Files:**
- Modify: `config/fortify.php`
- Modify: `app/Providers/FortifyServiceProvider.php`
- Modify: `resources/js/pages/auth/login.tsx`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Auth/NipAuthenticationTest.php
<?php

use App\Models\Pegawai;

test('pegawai can login with nip and password', function () {
    $pegawai = Pegawai::factory()->create([
        'nip' => '198501012010011001',
        'password' => bcrypt('password'),
    ]);

    $response = $this->post('/login', [
        'nip' => '198501012010011001',
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    expect(auth()->user()->id)->toBe($pegawai->id);
});

test('pegawai cannot login with wrong password', function () {
    Pegawai::factory()->create([
        'nip' => '198501012010011001',
        'password' => bcrypt('password'),
    ]);

    $this->post('/login', [
        'nip' => '198501012010011001',
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('pegawai cannot login with null password', function () {
    Pegawai::factory()->create([
        'nip' => '198501012010011002',
        'password' => null,
    ]);

    $this->post('/login', [
        'nip' => '198501012010011002',
        'password' => 'anything',
    ]);

    $this->assertGuest();
});

test('login page shows nip field', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Auth/NipAuthenticationTest.php`
Expected: FAIL — Fortify masih pakai email

- [ ] **Step 3: Update config/fortify.php**

Cari baris `'username' => 'email'` dan ganti:
```php
'username' => 'nip',
```

- [ ] **Step 4: Update FortifyServiceProvider — tambah custom authenticateUsing**

File: `app/Providers/FortifyServiceProvider.php` — Tambah di method `configureActions()`:

```php
use App\Models\Pegawai;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Fortify;

// Di configureActions():
Fortify::authenticateUsing(function ($request) {
    $pegawai = Pegawai::where('nip', $request->nip)->first();

    if ($pegawai && $pegawai->password && Hash::check($request->password, $pegawai->password)) {
        return $pegawai;
    }

    return null;
});
```

- [ ] **Step 5: Update login.tsx — email → nip**

File: `resources/js/pages/auth/login.tsx`:
- Label: "Email address" → "NIP"
- Input type: `email` → `text`
- Input name: `email` → `nip`
- Placeholder: `email@example.com` → `Masukkan NIP`
- Error key: `errors.email` → `errors.nip`
- Title/description: Update ke Bahasa Indonesia

- [ ] **Step 6: Run test and verify**

Run: `php artisan test tests/Feature/Auth/NipAuthenticationTest.php`
Expected: ALL PASS

- [ ] **Step 7: Commit**

```bash
git add config/fortify.php app/Providers/FortifyServiceProvider.php resources/js/pages/auth/login.tsx tests/Feature/Auth/NipAuthenticationTest.php
git commit -m "feat: configure Fortify login via NIP + password"
```

---

### Task 4: Update middleware — multi-role EnsureRole, HandleInertiaRequests, hapus EnsurePegawaiLinked

**Files:**
- Modify: `app/Http/Middleware/EnsureRole.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `bootstrap/app.php`
- Delete: `app/Http/Middleware/EnsurePegawaiLinked.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Auth/MultiRoleMiddlewareTest.php
<?php

use App\Models\Pegawai;
use App\Models\RefRole;

test('pegawai with matching role can access route', function () {
    $pegawai = Pegawai::factory()->admin()->create();

    $this->actingAs($pegawai)
        ->get('/dashboard')
        ->assertStatus(200);
});

test('pegawai with any matching role from multiple assigned roles passes', function () {
    $pegawai = Pegawai::factory()->create();
    $admin = RefRole::query()->where('nama', 'Admin')->first();
    $operator = RefRole::query()->where('nama', 'Operator')->first();
    $pegawai->roles()->attach([$admin->id, $operator->id]);

    $this->actingAs($pegawai)
        ->get('/dashboard')
        ->assertStatus(200);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Auth/MultiRoleMiddlewareTest.php`
Expected: FAIL — EnsureRole masih pakai `roleName()` single-role

- [ ] **Step 3: Update EnsureRole middleware**

File: `app/Http/Middleware/EnsureRole.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            if ($request->expectsJson()) {
                abort(Response::HTTP_UNAUTHORIZED);
            }

            return redirect()->route('login');
        }

        $allowedRoles = collect($roles)
            ->flatMap(fn (string $role) => explode(',', $role))
            ->map(fn (string $role) => trim($role))
            ->filter()
            ->values()
            ->all();

        // Multi-role: cek apakah pegawai punya salah satu role yang diizinkan
        $hasRole = $user->roles()
            ->whereIn('nama', array_map('strtolower', $allowedRoles))
            ->orWhereIn('nama', $allowedRoles)
            ->exists();

        if (! $hasRole) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Update HandleInertiaRequests — shared auth data**

File: `app/Http/Middleware/HandleInertiaRequests.php`:
```php
public function share(Request $request): array
{
    $user = $request->user();

    return [
        ...parent::share($request),
        'name' => config('app.name'),
        'auth' => [
            'user' => $user ? [
                'id' => $user->id,
                'nama_lengkap' => $user->nama_lengkap,
                'nip' => $user->nip,
                'email' => $user->email,
                'foto' => $user->foto ?? null,
                'email_verified_at' => $user->email_verified_at,
                'two_factor_enabled' => ! is_null($user->two_factor_confirmed_at),
                'roles' => $user->roles->pluck('nama')->toArray(),
                'permissions' => $user->roles->flatMap(
                    fn ($role) => $role->permissions->pluck('nama')
                )->unique()->values()->toArray(),
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ] : null,
        ],
        'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
    ];
}
```

- [ ] **Step 5: Update bootstrap/app.php — hapus EnsurePegawaiLinked, hapus alias**

Hapus import `EnsurePegawaiLinked` dan aliasnya dari middleware. Hapus juga alias `pegawai.linked`.

- [ ] **Step 6: Delete EnsurePegawaiLinked middleware**

Delete file: `app/Http/Middleware/EnsurePegawaiLinked.php`

- [ ] **Step 7: Run test and verify**

Run: `php artisan test tests/Feature/Auth/MultiRoleMiddlewareTest.php`
Expected: ALL PASS

- [ ] **Step 8: Commit**

```bash
git add app/Http/Middleware/EnsureRole.php app/Http/Middleware/HandleInertiaRequests.php bootstrap/app.php tests/Feature/Auth/MultiRoleMiddlewareTest.php
git rm app/Http/Middleware/EnsurePegawaiLinked.php
git commit -m "feat: update middleware for multi-role Pegawai auth"
```

---

## Chunk 3: Frontend Type System & Cleanup

### Task 5: Update TypeScript types & frontend auth references

**Files:**
- Modify: `resources/js/types/auth.ts`
- Modify: `resources/js/types/index.ts` (jika ada reference User)
- Modify: `resources/js/components/app-sidebar.tsx`
- Modify: `resources/js/components/nav-user.tsx` (jika ada)

- [ ] **Step 1: Update auth.ts types**

File: `resources/js/types/auth.ts`:
```typescript
export type User = {
    id: string;
    nama_lengkap: string;
    nip: string;
    email: string | null;
    foto: string | null;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    roles: string[];
    permissions: string[];
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
```

- [ ] **Step 2: Find and update all frontend references to `user.name`**

Run: `grep -rn "user\.name\b" resources/js/ --include="*.tsx" --include="*.ts"` dan ganti semua `user.name` → `user.nama_lengkap`

Run: `grep -rn "user\.avatar" resources/js/ --include="*.tsx" --include="*.ts"` dan ganti semua `user.avatar` → `user.foto`

Run: `grep -rn "user\.role_name\|user\.role\b" resources/js/ --include="*.tsx" --include="*.ts"` dan ganti ke `user.roles` (array).

- [ ] **Step 3: Update sidebar — hapus menu Pengguna**

File: `resources/js/components/app-sidebar.tsx` — Cari dan hapus nav item "Pengguna" / "Manajemen Pengguna" dari daftar menu.

- [ ] **Step 4: Verify frontend compiles**

Run: `npm run build`
Expected: No TypeScript errors

- [ ] **Step 5: Commit**

```bash
git add resources/js/
git commit -m "feat: update frontend types and references for Pegawai auth"
```

---

### Task 6: Delete User model, UserController, dan semua file terkait

**Files:**
- Delete: `app/Models/User.php`
- Delete: `app/Http/Controllers/UserController.php`
- Delete: `app/Http/Requests/StoreUserRequest.php`
- Delete: `app/Http/Requests/UpdateUserRequest.php`
- Delete: `app/Policies/UserPolicy.php`
- Delete: `database/factories/UserFactory.php`
- Delete: `resources/js/pages/pengguna/index.tsx`
- Delete: `resources/js/pages/pengguna/create.tsx`
- Delete: `resources/js/pages/pengguna/edit.tsx`
- Modify: `routes/web.php` — hapus route pengguna
- Modify: `routes/web.php` — hapus middleware pegawai.linked dari self-service

- [ ] **Step 1: Hapus route pengguna dari web.php dan middleware pegawai.linked**

File: `routes/web.php`:
- Hapus `use App\Http\Controllers\UserController;`
- Hapus `Route::resource('pengguna', UserController::class)->except(['show']);`
- Di self-service routes: hapus `Route::middleware('pegawai.linked')` wrapper, buat route langsung accessible

- [ ] **Step 2: Delete semua file terkait User**

```bash
git rm app/Models/User.php
git rm app/Http/Controllers/UserController.php
git rm app/Http/Requests/StoreUserRequest.php
git rm app/Http/Requests/UpdateUserRequest.php
git rm app/Policies/UserPolicy.php
git rm database/factories/UserFactory.php
git rm resources/js/pages/pengguna/index.tsx
git rm resources/js/pages/pengguna/create.tsx
git rm resources/js/pages/pengguna/edit.tsx
```

- [ ] **Step 3: Update SelfServiceController — pegawai = auth user langsung**

File: `app/Http/Controllers/Kepegawaian/SelfServiceController.php`:
```php
// Ganti method currentPegawai:
private function currentPegawai(Request $request, array $relations): Pegawai
{
    // Sekarang $request->user() sudah return Pegawai
    return $request->user()->load($relations);
}
```

Hapus juga `'user'` dari `detailRelations()` (tidak perlu lagi load user relation).

- [ ] **Step 4: Update ProfileController — ganti field references**

File: `app/Http/Controllers/Settings/ProfileController.php`:
- `isDirty('email')` tetap valid (pegawai punya field email)
- Tidak perlu banyak perubahan, hanya pastikan `$request->user()` masih bekerja

- [ ] **Step 5: Verify routes work**

Run: `php artisan route:list`
Expected: Tidak ada error, route pengguna sudah hilang

- [ ] **Step 6: Commit**

```bash
git add routes/web.php app/Http/Controllers/Kepegawaian/SelfServiceController.php app/Http/Controllers/Settings/ProfileController.php
git commit -m "feat: remove User model and related files, update self-service"
```

---

## Chunk 4: Role Assignment Feature

### Task 7: Backend — Assign pegawai ke role di RefRoleController

**Files:**
- Modify: `app/Http/Controllers/Referensi/RefRoleController.php`
- Modify: `app/Http/Requests/Referensi/UpdateRefRoleRequest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Referensi/RoleAssignPegawaiTest.php
<?php

use App\Models\Pegawai;
use App\Models\RefRole;

test('edit role page includes pegawai list and assigned ids', function () {
    $admin = Pegawai::factory()->admin()->create();
    $role = RefRole::query()->where('nama', 'Operator')->first();

    $response = $this->actingAs($admin)
        ->get(route('referensi.roles.edit', $role));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->has('pegawaiList.data')
        ->has('assignedPegawaiIds')
    );
});

test('admin can assign pegawai to role', function () {
    $admin = Pegawai::factory()->admin()->create();
    $role = RefRole::query()->where('nama', 'Operator')->first();
    $pegawai1 = Pegawai::factory()->create();
    $pegawai2 = Pegawai::factory()->create();

    $response = $this->actingAs($admin)
        ->put(route('referensi.roles.update', $role), [
            'nama' => $role->nama,
            'keterangan' => $role->keterangan,
            'permissions' => $role->permissions->pluck('id')->toArray(),
            'pegawai_ids' => [$pegawai1->id, $pegawai2->id],
        ]);

    $response->assertRedirect(route('referensi.roles.index'));
    expect($role->fresh()->pegawai)->toHaveCount(2);
});

test('admin can unassign pegawai from role', function () {
    $admin = Pegawai::factory()->admin()->create();
    $role = RefRole::query()->where('nama', 'Operator')->first();
    $pegawai = Pegawai::factory()->create();
    $role->pegawai()->attach($pegawai->id);

    $this->actingAs($admin)
        ->put(route('referensi.roles.update', $role), [
            'nama' => $role->nama,
            'keterangan' => $role->keterangan,
            'permissions' => $role->permissions->pluck('id')->toArray(),
            'pegawai_ids' => [], // kosong = unassign semua
        ]);

    expect($role->fresh()->pegawai)->toHaveCount(0);
});

test('cannot delete role that has pegawai assigned', function () {
    $admin = Pegawai::factory()->admin()->create();
    $role = RefRole::factory()->create();
    $pegawai = Pegawai::factory()->create();
    $role->pegawai()->attach($pegawai->id);

    $this->actingAs($admin)
        ->delete(route('referensi.roles.destroy', $role))
        ->assertRedirect()
        ->assertSessionHas('error');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Referensi/RoleAssignPegawaiTest.php`
Expected: FAIL — edit belum return pegawaiList

- [ ] **Step 3: Update RefRoleController**

File: `app/Http/Controllers/Referensi/RefRoleController.php`:

**edit() method** — tambah data pegawai:
```php
public function edit(RefRole $role): Response
{
    $this->authorize('update', $role);

    $role->load('permissions');

    $permissions = RefPermission::query()
        ->orderBy('group')
        ->orderBy('nama')
        ->get(['id', 'nama', 'group', 'keterangan']);

    return Inertia::render('referensi/roles/edit', [
        'role' => $role,
        'permissions' => $permissions,
        'pegawaiList' => Pegawai::query()
            ->select('id', 'nama_lengkap', 'nip')
            ->when(request('search'), fn ($q, $s) => $q
                ->where('nama_lengkap', 'like', "%{$s}%")
                ->orWhere('nip', 'like', "%{$s}%"))
            ->orderBy('nama_lengkap')
            ->paginate(15)
            ->withQueryString(),
        'assignedPegawaiIds' => $role->pegawai()->pluck('pegawai.id'),
    ]);
}
```

**update() method** — sync pegawai:
```php
public function update(UpdateRefRoleRequest $request, RefRole $role): RedirectResponse
{
    $role->update($request->safe()->only(['nama', 'keterangan']));

    if ($request->has('permissions')) {
        $role->permissions()->sync($request->input('permissions', []));
    }

    if ($request->has('pegawai_ids')) {
        $role->pegawai()->sync($request->input('pegawai_ids', []));
    }

    return redirect()
        ->route('referensi.roles.index')
        ->with('success', 'Role berhasil diperbarui.');
}
```

**destroy() method** — ganti `users()` ke `pegawai()`:
```php
if ($role->pegawai()->exists()) {
    return redirect()
        ->route('referensi.roles.index')
        ->with('error', 'Role masih memiliki pegawai yang di-assign. Pindahkan pegawai ke role lain terlebih dahulu.');
}
```

Tambah import: `use App\Models\Pegawai;`

- [ ] **Step 4: Update UpdateRefRoleRequest — tambah validasi pegawai_ids**

File: `app/Http/Requests/Referensi/UpdateRefRoleRequest.php` — tambah rule:
```php
'pegawai_ids' => ['nullable', 'array'],
'pegawai_ids.*' => ['exists:pegawai,id'],
```

- [ ] **Step 5: Run test and verify**

Run: `php artisan test tests/Feature/Referensi/RoleAssignPegawaiTest.php`
Expected: ALL PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Referensi/RefRoleController.php app/Http/Requests/Referensi/UpdateRefRoleRequest.php tests/Feature/Referensi/RoleAssignPegawaiTest.php
git commit -m "feat: add pegawai assignment to role edit page"
```

---

### Task 8: Frontend — Checklist pegawai di Edit Role page

**Files:**
- Modify: `resources/js/pages/referensi/roles/edit.tsx`

- [ ] **Step 1: Update Edit Role page — tambah section assign pegawai**

File: `resources/js/pages/referensi/roles/edit.tsx`:

Tambah ke Props type:
```typescript
pegawaiList: {
    data: Array<{ id: string; nama_lengkap: string; nip: string | null }>;
    links: any;
    current_page: number;
    last_page: number;
};
assignedPegawaiIds: string[];
```

Tambah `pegawai_ids` ke useForm data:
```typescript
pegawai_ids: assignedPegawaiIds ?? [],
```

Tambah section setelah permissions checklist:
```tsx
{/* Section Assign Pegawai */}
<div className="space-y-4">
    <Label>Assign Pegawai</Label>
    <Input
        placeholder="Cari nama atau NIP..."
        onChange={(e) => {
            router.get(route('referensi.roles.edit', role.id),
                { search: e.target.value },
                { preserveState: true, preserveScroll: true }
            );
        }}
    />
    <div className="max-h-64 space-y-1 overflow-y-auto rounded-md border p-3">
        {pegawaiList.data.map((pegawai) => (
            <div key={pegawai.id} className="flex items-center gap-2">
                <Checkbox
                    id={`pegawai-${pegawai.id}`}
                    checked={data.pegawai_ids.includes(pegawai.id)}
                    onCheckedChange={() => togglePegawai(pegawai.id)}
                />
                <Label htmlFor={`pegawai-${pegawai.id}`} className="text-sm font-normal">
                    {pegawai.nama_lengkap}
                    {pegawai.nip && (
                        <span className="ml-1 text-muted-foreground">
                            (NIP: {pegawai.nip})
                        </span>
                    )}
                </Label>
            </div>
        ))}
    </div>
    {/* Pagination sederhana */}
    {pegawaiList.last_page > 1 && (
        <div className="flex gap-2 text-sm">
            Halaman {pegawaiList.current_page} dari {pegawaiList.last_page}
        </div>
    )}
</div>
```

Tambah helper function:
```typescript
const togglePegawai = (pegawaiId: string) => {
    const current = data.pegawai_ids;
    if (current.includes(pegawaiId)) {
        setData('pegawai_ids', current.filter((id) => id !== pegawaiId));
    } else {
        setData('pegawai_ids', [...current, pegawaiId]);
    }
};
```

- [ ] **Step 2: Verify frontend compiles**

Run: `npm run build`
Expected: No errors

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/referensi/roles/edit.tsx
git commit -m "feat: add pegawai checklist to role edit page"
```

---

## Chunk 5: Password Management & Final Cleanup

### Task 9: Tambah field password di Edit Pegawai

**Files:**
- Modify: `app/Http/Controllers/Kepegawaian/PegawaiController.php` (method update)
- Modify: Form request terkait pegawai update
- Modify: Frontend edit pegawai page

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/Kepegawaian/PegawaiPasswordTest.php
<?php

use App\Models\Pegawai;
use Illuminate\Support\Facades\Hash;

test('admin can set password for pegawai', function () {
    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create(['password' => null]);

    $this->actingAs($admin)
        ->put(route('kepegawaian.pegawai.update', $pegawai), [
            ...validPegawaiData($pegawai),
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

    expect(Hash::check('newpassword123', $pegawai->fresh()->password))->toBeTrue();
});

test('password not changed when field is empty', function () {
    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create(['password' => bcrypt('oldpassword')]);
    $oldHash = $pegawai->password;

    $this->actingAs($admin)
        ->put(route('kepegawaian.pegawai.update', $pegawai), [
            ...validPegawaiData($pegawai),
            // password tidak dikirim
        ]);

    expect($pegawai->fresh()->password)->toBe($oldHash);
});

// Helper function
function validPegawaiData(Pegawai $pegawai): array
{
    return [
        'nama_lengkap' => $pegawai->nama_lengkap,
        'tempat_lahir' => $pegawai->tempat_lahir,
        'tanggal_lahir' => $pegawai->tanggal_lahir->format('Y-m-d'),
        'jenis_kelamin' => $pegawai->jenis_kelamin->value,
        'agama' => $pegawai->agama->value,
        'status_perkawinan' => $pegawai->status_perkawinan->value,
        'status_kepegawaian' => $pegawai->status_kepegawaian->value,
        'status_pegawai' => $pegawai->status_pegawai->value,
        'tanggal_masuk' => $pegawai->tanggal_masuk->format('Y-m-d'),
    ];
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Kepegawaian/PegawaiPasswordTest.php`

- [ ] **Step 3: Update PegawaiController@update — handle password**

Tambahkan di method update, setelah `$pegawai->update(...)`:
```php
if ($request->filled('password')) {
    $pegawai->update(['password' => $request->validated('password')]);
}
```

Tambahkan ke form request rules:
```php
'password' => ['nullable', 'string', 'min:8', 'confirmed'],
```

- [ ] **Step 4: Update edit pegawai frontend — tambah section Akun Login**

Tambahkan setelah field existing di form edit pegawai:
```tsx
{/* Section Akun Login */}
<div className="space-y-4 border-t pt-4">
    <h3 className="text-lg font-medium">Akun Login</h3>
    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="space-y-2">
            <Label htmlFor="password">Password Baru</Label>
            <Input
                id="password"
                type="password"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                placeholder="Kosongkan jika tidak ingin mengubah"
            />
            {errors.password && <p className="text-sm text-destructive">{errors.password}</p>}
        </div>
        <div className="space-y-2">
            <Label htmlFor="password_confirmation">Konfirmasi Password</Label>
            <Input
                id="password_confirmation"
                type="password"
                value={data.password_confirmation}
                onChange={(e) => setData('password_confirmation', e.target.value)}
                placeholder="Ulangi password baru"
            />
        </div>
    </div>
    {/* Info role (readonly) */}
    {pegawai.roles && pegawai.roles.length > 0 && (
        <div className="space-y-2">
            <Label>Role yang Di-assign</Label>
            <div className="flex gap-2">
                {pegawai.roles.map((role) => (
                    <span key={role.id} className="rounded-md bg-muted px-2 py-1 text-sm">
                        {role.nama}
                    </span>
                ))}
            </div>
        </div>
    )}
</div>
```

Tambah `password: ''` dan `password_confirmation: ''` ke useForm data.

- [ ] **Step 5: Run test and verify**

Run: `php artisan test tests/Feature/Kepegawaian/PegawaiPasswordTest.php`
Expected: ALL PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Kepegawaian/PegawaiController.php resources/js/pages/kepegawaian/pegawai/edit.tsx tests/Feature/Kepegawaian/PegawaiPasswordTest.php
git commit -m "feat: add password management to pegawai edit page"
```

---

### Task 10: Update semua existing tests — ganti User::factory() → Pegawai::factory()

**Files:**
- Modify: Semua 29 test files yang pakai `User::factory()`

- [ ] **Step 1: Bulk find & replace di semua test files**

Lakukan penggantian di semua file test:

| Find | Replace |
|------|---------|
| `use App\Models\User;` | `use App\Models\Pegawai;` |
| `User::factory()` | `Pegawai::factory()` |
| `->admin()` | `->admin()` (tetap, factory method sudah ada) |
| `->operator()` | `->operator()` (tetap) |
| `->viewer()` | `->viewer()` (tetap) |

File-file yang perlu diupdate:
```
tests/Feature/Auth/AuthenticationTest.php
tests/Feature/Auth/EmailVerificationTest.php
tests/Feature/Auth/PasswordConfirmationTest.php
tests/Feature/Auth/PasswordResetTest.php
tests/Feature/Auth/RoleMiddlewareTest.php
tests/Feature/Auth/TwoFactorChallengeTest.php
tests/Feature/Auth/VerificationNotificationTest.php
tests/Feature/DashboardTest.php
tests/Feature/Kepegawaian/DokumenPegawaiTest.php
tests/Feature/Kepegawaian/HukumanDisiplinTest.php
tests/Feature/Kepegawaian/KeluargaTest.php
tests/Feature/Kepegawaian/PegawaiControllerTest.php
tests/Feature/Kepegawaian/PegawaiCreateTest.php
tests/Feature/Kepegawaian/PegawaiSearchFilterTest.php
tests/Feature/Kepegawaian/PegawaiUpdateTest.php
tests/Feature/Kepegawaian/PenghargaanTest.php
tests/Feature/Kepegawaian/RiwayatDiklatTest.php
tests/Feature/Kepegawaian/RiwayatJabatanTest.php
tests/Feature/Kepegawaian/RiwayatPangkatTest.php
tests/Feature/Kepegawaian/RiwayatPendidikanTest.php
tests/Feature/Models/PegawaiTest.php
tests/Feature/Monitoring/KenaikanPangkatMonitoringTest.php
tests/Feature/Monitoring/KgbMonitoringTest.php
tests/Feature/SelfService/SelfServiceAccessTest.php
tests/Feature/Settings/ProfileUpdateTest.php
tests/Feature/Settings/SecurityTest.php
tests/Unit/Kepegawaian/FormRequestAuthorizationTest.php
```

Perhatian khusus:
- `AuthenticationTest.php` — ganti `email` → `nip` di login post data
- `PasswordResetTest.php` — password reset masih pakai email (tetap valid karena pegawai punya email)
- `SelfServiceAccessTest.php` — hapus reference ke `pegawai.linked` middleware, hapus test unlinked scenario
- `RoleMiddlewareTest.php` — update untuk multi-role

- [ ] **Step 2: Run full test suite**

Run: `php artisan test`
Expected: ALL PASS (atau sebagian besar pass — fix any remaining issues)

- [ ] **Step 3: Commit**

```bash
git add tests/
git commit -m "test: update all tests to use Pegawai::factory() instead of User::factory()"
```

---

### Task 11: Update DatabaseSeeder

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Update seeder — ganti User::factory ke Pegawai::factory**

Ganti semua `User::factory()` dengan `Pegawai::factory()` dan pastikan role di-assign via pivot (bukan `ref_role_id`).

- [ ] **Step 2: Run seeder test**

Run: `php artisan migrate:fresh --seed`
Expected: No errors

- [ ] **Step 3: Commit**

```bash
git add database/seeders/DatabaseSeeder.php
git commit -m "feat: update seeder to use Pegawai as authenticatable"
```

---

### Task 12: Final verification — full test suite + manual smoke test

- [ ] **Step 1: Run full test suite**

Run: `php artisan test`
Expected: ALL PASS

- [ ] **Step 2: Run frontend build**

Run: `npm run build`
Expected: No errors

- [ ] **Step 3: Smoke test — verify key flows**

1. Login via NIP + password
2. Dashboard loads
3. Edit role → checklist pegawai visible
4. Edit pegawai → password section visible
5. Self-service → data pegawai accessible

- [ ] **Step 4: Final commit jika ada fixes**

```bash
git add -A
git commit -m "fix: final cleanup for pegawai-as-user migration"
```
