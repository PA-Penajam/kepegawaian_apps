# Fase 2: Kepatuhan & Kualitas Kode — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementasi audit trail (spatie/laravel-activitylog), FormRequest consistency di IAM controllers, dan fix duplikasi query di PegawaiApiController.

**Architecture:** Tiga task independen dikerjakan sekuensial: 2.3 (paling simpel) → 2.2 (FormRequest) → 2.1 (paling kompleks). Setiap task tuntas termasuk test sebelum lanjut ke task berikutnya.

**Tech Stack:** Laravel 12, PHP 8.4, Pest v4, React 19, Inertia.js v2, TypeScript, `spatie/laravel-activitylog` (belum terinstall)

---

## File Map

| Task | Tipe | File |
|---|---|---|
| 2.3 | Modify | `app/Http/Controllers/Api/PegawaiApiController.php` |
| 2.3 | Modify | `tests/Feature/Api/PegawaiApiTest.php` |
| 2.2 | Create | `app/Http/Requests/Iam/StoreAplikasiRequest.php` |
| 2.2 | Create | `app/Http/Requests/Iam/UpdateAplikasiRequest.php` |
| 2.2 | Create | `app/Http/Requests/Iam/StoreUserAksesRequest.php` |
| 2.2 | Modify | `app/Http/Controllers/Iam/AplikasiController.php` |
| 2.2 | Modify | `app/Http/Controllers/Iam/UserAksesController.php` |
| 2.1 | Install | `composer.json` (spatie/laravel-activitylog) |
| 2.1 | Publish | migration + config spatie |
| 2.1 | Modify | 13 Model files (tambah `LogsActivity` trait) |
| 2.1 | Modify | `app/Providers/AppServiceProvider.php` |
| 2.1 | Create | `app/Http/Controllers/ActivityLogController.php` |
| 2.1 | Modify | `routes/web.php` |
| 2.1 | Create | `resources/js/pages/activity-log/index.tsx` |
| 2.1 | Create | `tests/Feature/ActivityLogTest.php` |

---

## Task 1: Fix Duplikasi Query di PegawaiApiController (Item 2.3)

**Files:**
- Modify: `app/Http/Controllers/Api/PegawaiApiController.php`
- Modify: `tests/Feature/Api/PegawaiApiTest.php`

---

- [ ] **Step 1.1: Tulis test yang memverifikasi hanya 1 query untuk search**

Tambahkan test ini di `tests/Feature/Api/PegawaiApiTest.php` (setelah test yang sudah ada):

```php
use Illuminate\Support\Facades\DB;

test('search pegawai hanya menjalankan 1 query bukan 2', function () {
    $user = Pegawai::factory()->create();
    Pegawai::factory()->create([
        'status_pegawai' => StatusPegawai::Aktif,
        'nama_lengkap' => 'Budi Santoso',
    ]);
    Sanctum::actingAs($user, ['*']);

    DB::enableQueryLog();

    $query = ['search' => 'Budi', 'status' => 'aktif'];
    $headers = makeSignedHeaders('GET', '/api/v1/pegawai', $query);
    $this->getJson('/api/v1/pegawai?'.http_build_query($query), $headers)
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Maksimal 2 query: 1 untuk paginate (data + count), 1 untuk eager load relasi
    // Sebelumnya ada 3: get() + count() + eager load
    $pegawaiQueries = collect($queries)->filter(
        fn ($q) => str_contains($q['query'], 'from `pegawai`') ||
                   str_contains($q['query'], 'from "pegawai"')
    );

    // paginate() menjalankan 2 query ke tabel pegawai: SELECT data + SELECT count(*)
    expect($pegawaiQueries)->toHaveCount(2);
});
```

- [ ] **Step 1.2: Jalankan test, pastikan FAIL**

```bash
cd /Volumes/Dev/Projects/kepegawaian_apps
php artisan test tests/Feature/Api/PegawaiApiTest.php --filter="search pegawai hanya menjalankan 1 query" --stop-on-failure
```

Expected: FAIL — query count melebihi 1 (ada duplikasi `count()` terpisah)

- [ ] **Step 1.3: Refactor method `search()` di PegawaiApiController**

Ganti method `search()` di `app/Http/Controllers/Api/PegawaiApiController.php`:

```php
private function search(Request $request): JsonResponse
{
    $result = Pegawai::with(['jabatan', 'unitKerja', 'pangkat'])
        ->when($request->input('status') === 'aktif', fn ($q) => $q->aktif())
        ->when(
            $request->input('search'),
            fn ($q, $search) => $q->where('nama_lengkap', 'like', "%{$search}%")
        )
        ->paginate(20);

    return response()->json([
        'data' => PegawaiApiResource::collection($result),
        'meta' => ['total' => $result->total(), 'per_page' => 20],
    ]);
}
```

- [ ] **Step 1.4: Jalankan seluruh test API, pastikan semua PASS**

```bash
php artisan test tests/Feature/Api/PegawaiApiTest.php --stop-on-failure
```

Expected: Semua PASS (14 test existing + 1 test baru = 15 test)

- [ ] **Step 1.5: Commit**

```bash
git add app/Http/Controllers/Api/PegawaiApiController.php \
        tests/Feature/Api/PegawaiApiTest.php
git commit -m "perf: ganti dua query duplikat dengan paginate() di PegawaiApiController::search()"
```

---

## Task 2: FormRequest Consistency di IAM Controllers (Item 2.2)

**Files:**
- Create: `app/Http/Requests/Iam/StoreAplikasiRequest.php`
- Create: `app/Http/Requests/Iam/UpdateAplikasiRequest.php`
- Create: `app/Http/Requests/Iam/StoreUserAksesRequest.php`
- Modify: `app/Http/Controllers/Iam/AplikasiController.php`
- Modify: `app/Http/Controllers/Iam/UserAksesController.php`

---

- [ ] **Step 2.1: Tulis test yang memverifikasi viewer ditolak di AplikasiController::store()**

Tambahkan test ini di `tests/Feature/Iam/AplikasiControllerTest.php`:

```php
it('viewer tidak bisa mendaftarkan aplikasi baru', function () {
    $viewer = Pegawai::factory()->viewer()->create();

    $response = $this->actingAs($viewer)->post('/iam/aplikasi', [
        'nama' => 'Test App',
        'slug' => 'test-app',
        'url'  => 'http://test.local',
    ]);

    $response->assertForbidden();
});

it('viewer tidak bisa mengupdate aplikasi', function () {
    $viewer = Pegawai::factory()->viewer()->create();
    $app = IamApplication::factory()->create(['is_system' => false]);

    $response = $this->actingAs($viewer)->put("/iam/aplikasi/{$app->id}", [
        'nama'      => 'Updated',
        'url'       => 'http://test.local',
        'is_active' => true,
    ]);

    $response->assertForbidden();
});

it('viewer tidak bisa memberikan akses role ke user', function () {
    $viewer = Pegawai::factory()->viewer()->create();
    $targetUser = Pegawai::factory()->create();
    $role = IamRole::factory()->create(['iam_application_id' => $this->kepegawaianApp->id]);

    $response = $this->actingAs($viewer)
        ->post("/iam/users/{$targetUser->id}/akses", [
            'iam_role_id' => $role->id,
        ]);

    $response->assertForbidden();
});
```

- [ ] **Step 2.2: Jalankan test, pastikan FAIL**

```bash
php artisan test tests/Feature/Iam/AplikasiControllerTest.php --filter="viewer tidak bisa" --stop-on-failure
```

Expected: FAIL — saat ini tidak ada authorization di level FormRequest, viewer bisa lolos middleware `iam.permission:iam-manage` jika punya permission tersebut

> **Catatan:** Jika test PASS karena middleware route sudah menolak viewer, itu valid — FormRequest `authorize()` menjadi defense-in-depth. Lanjutkan ke step berikutnya.

- [ ] **Step 2.3: Buat `StoreAplikasiRequest`**

Buat file `app/Http/Requests/Iam/StoreAplikasiRequest.php`:

```php
<?php

namespace App\Http\Requests\Iam;

use Illuminate\Foundation\Http\FormRequest;

class StoreAplikasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('iam-manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'nama'      => ['required', 'string', 'max:100'],
            'slug'      => ['required', 'string', 'alpha_dash', 'unique:iam_applications,slug'],
            'url'       => ['required', 'url'],
            'deskripsi' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required'   => 'Nama aplikasi wajib diisi.',
            'slug.required'   => 'Slug aplikasi wajib diisi.',
            'slug.alpha_dash' => 'Slug hanya boleh mengandung huruf, angka, strip, dan garis bawah.',
            'slug.unique'     => 'Slug ini sudah digunakan oleh aplikasi lain.',
            'url.required'    => 'URL aplikasi wajib diisi.',
            'url.url'         => 'URL aplikasi harus berupa URL yang valid.',
        ];
    }
}
```

- [ ] **Step 2.4: Buat `UpdateAplikasiRequest`**

Buat file `app/Http/Requests/Iam/UpdateAplikasiRequest.php`:

```php
<?php

namespace App\Http\Requests\Iam;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAplikasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('iam-manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'nama'      => ['required', 'string', 'max:100'],
            'url'       => ['required', 'url'],
            'deskripsi' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama aplikasi wajib diisi.',
            'url.required'  => 'URL aplikasi wajib diisi.',
            'url.url'       => 'URL aplikasi harus berupa URL yang valid.',
        ];
    }
}
```

- [ ] **Step 2.5: Buat `StoreUserAksesRequest`**

Buat file `app/Http/Requests/Iam/StoreUserAksesRequest.php`:

```php
<?php

namespace App\Http\Requests\Iam;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserAksesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('iam-manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'iam_role_id' => ['required', 'exists:iam_roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'iam_role_id.required' => 'Role wajib dipilih.',
            'iam_role_id.exists'   => 'Role yang dipilih tidak valid.',
        ];
    }
}
```

- [ ] **Step 2.6: Update `AplikasiController` — gunakan FormRequest**

Ganti seluruh isi `app/Http/Controllers/Iam/AplikasiController.php`:

```php
<?php

namespace App\Http\Controllers\Iam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iam\StoreAplikasiRequest;
use App\Http\Requests\Iam\UpdateAplikasiRequest;
use App\Models\IamApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Response;

class AplikasiController extends Controller
{
    public function index(): Response
    {
        $aplikasi = IamApplication::withCount('roles')
            ->latest()
            ->get()
            ->map(function ($app) {
                $app->api_key_display = $this->maskApiKey($app->api_key);
                unset($app->api_key);
                return $app;
            });

        return inertia('iam/aplikasi/index', compact('aplikasi'));
    }

    public function show(IamApplication $aplikasi): Response
    {
        $aplikasi->load(['roles.permissions', 'permissions']);

        $aplikasiArray = $aplikasi->toArray();
        $aplikasiArray['api_key_display'] = $this->maskApiKey($aplikasi->api_key);
        unset($aplikasiArray['api_key']);

        return inertia('iam/aplikasi/show', [
            'aplikasi' => $aplikasiArray,
        ]);
    }

    public function store(StoreAplikasiRequest $request): RedirectResponse
    {
        ['key' => $key, 'secret' => $secret, 'hash' => $hash] = IamApplication::generateApiCredentials();

        $app = IamApplication::create([
            ...$request->validated(),
            'api_key'         => $key,
            'api_secret_hash' => $hash,
        ]);

        return redirect()
            ->route('iam.aplikasi.show', $app)
            ->with('api_secret_once', $secret);
    }

    public function update(UpdateAplikasiRequest $request, IamApplication $aplikasi): RedirectResponse
    {
        abort_if($aplikasi->is_system, 403, 'Aplikasi sistem tidak dapat diubah');

        $aplikasi->update($request->validated());

        Cache::forget("iam_app:{$aplikasi->slug}");

        return back();
    }

    public function destroy(IamApplication $aplikasi): RedirectResponse
    {
        abort_if($aplikasi->is_system, 403, 'Aplikasi sistem tidak dapat dihapus');

        Cache::forget("iam_app:{$aplikasi->slug}");
        $aplikasi->delete();

        return redirect()->route('iam.aplikasi.index');
    }

    public function regenerateKey(IamApplication $aplikasi): RedirectResponse
    {
        ['key' => $key, 'secret' => $secret, 'hash' => $hash] = IamApplication::generateApiCredentials();

        $aplikasi->api_key         = $key;
        $aplikasi->api_secret_hash = $hash;
        $aplikasi->save();

        return back()->with('api_secret_once', $secret);
    }

    private function maskApiKey(string $apiKey): string
    {
        $length = strlen($apiKey);

        if ($length <= 12) {
            return str_repeat('*', $length);
        }

        $prefix        = substr($apiKey, 0, 4);
        $suffix        = substr($apiKey, -8);
        $maskedLength  = $length - 12;

        return $prefix.str_repeat('*', $maskedLength).$suffix;
    }
}
```

- [ ] **Step 2.7: Update `UserAksesController` — gunakan FormRequest**

Ganti seluruh isi `app/Http/Controllers/Iam/UserAksesController.php`:

```php
<?php

namespace App\Http\Controllers\Iam;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iam\StoreUserAksesRequest;
use App\Models\IamApplication;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\Pegawai;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Response;

class UserAksesController extends Controller
{
    public function index(): Response
    {
        $users = Pegawai::with('iamUserRoles.role.application')->paginate(20);
        return inertia('iam/users/index', compact('users'));
    }

    public function show(Pegawai $user): Response
    {
        $akses = IamUserRole::where('user_id', $user->id)
            ->with(['role.application', 'role.permissions', 'assignedByUser'])
            ->get();
        $availableApps = IamApplication::where('is_active', true)
            ->with('roles')
            ->get();
        return inertia('iam/users/akses', compact('user', 'akses', 'availableApps'));
    }

    public function store(StoreUserAksesRequest $request, Pegawai $user): RedirectResponse
    {
        IamUserRole::firstOrCreate(
            ['user_id' => $user->id, 'iam_role_id' => $request->validated('iam_role_id')],
            ['assigned_at' => now(), 'assigned_by' => $request->user()->id]
        );

        Cache::flush();

        return back();
    }

    public function destroy(Pegawai $user, IamRole $role): RedirectResponse
    {
        IamUserRole::where('user_id', $user->id)->where('iam_role_id', $role->id)->delete();

        Cache::flush();

        return back();
    }
}
```

- [ ] **Step 2.8: Jalankan seluruh test IAM, pastikan semua PASS**

```bash
php artisan test tests/Feature/Iam/ --stop-on-failure
```

Expected: Semua PASS

- [ ] **Step 2.9: Commit**

```bash
git add app/Http/Requests/Iam/ \
        app/Http/Controllers/Iam/AplikasiController.php \
        app/Http/Controllers/Iam/UserAksesController.php \
        tests/Feature/Iam/AplikasiControllerTest.php
git commit -m "refactor: ekstrak inline validation IAM ke FormRequest classes"
```

---

## Task 3: Audit Trail + Slow Query Logger (Item 2.1)

**Files:**
- Install: `spatie/laravel-activitylog` via composer
- Publish: migration + config
- Modify: 13 Model files
- Modify: `app/Providers/AppServiceProvider.php`
- Create: `app/Http/Controllers/ActivityLogController.php`
- Modify: `routes/web.php`
- Create: `resources/js/pages/activity-log/index.tsx`
- Create: `tests/Feature/ActivityLogTest.php`

---

### Sub-task 3A: Install & Setup Spatie

- [ ] **Step 3.1: Install spatie/laravel-activitylog**

```bash
composer require spatie/laravel-activitylog
```

Expected output: Package installed, `composer.json` dan `composer.lock` terupdate.

- [ ] **Step 3.2: Publish migration dan config**

```bash
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"
```

Expected: File `database/migrations/YYYY_MM_DD_create_activity_log_table.php` dan `config/activitylog.php` dibuat.

- [ ] **Step 3.3: Jalankan migration**

```bash
php artisan migrate
```

Expected: Tabel `activity_log` dibuat di database.

- [ ] **Step 3.4: Verifikasi tabel ada**

```bash
php artisan tinker --execute="Schema::hasTable('activity_log') ? 'OK' : 'MISSING'"
```

Expected output: `OK`

---

### Sub-task 3B: Tambahkan LogsActivity ke 13 Model

- [ ] **Step 3.5: Tulis test yang memverifikasi activity log ter-create saat Pegawai diupdate**

Buat file `tests/Feature/ActivityLogTest.php`:

```php
<?php

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\Pegawai;
use App\Models\RiwayatPangkat;
use Spatie\Activitylog\Models\Activity;

test('activity log ter-create saat pegawai diupdate', function () {
    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create(['nama_lengkap' => 'Sebelum Update']);

    $this->actingAs($admin);

    $pegawai->update(['nama_lengkap' => 'Sesudah Update']);

    $log = Activity::latest()->first();

    expect($log)->not->toBeNull()
        ->and($log->subject_type)->toBe(Pegawai::class)
        ->and($log->subject_id)->toBe($pegawai->id)
        ->and($log->description)->toBe('updated')
        ->and($log->properties['old']['nama_lengkap'])->toBe('Sebelum Update')
        ->and($log->properties['new']['nama_lengkap'])->toBe('Sesudah Update');
});

test('activity log tidak ter-create jika tidak ada field yang berubah', function () {
    $pegawai = Pegawai::factory()->create(['nama_lengkap' => 'Tidak Berubah']);
    $countBefore = Activity::count();

    $pegawai->update(['nama_lengkap' => 'Tidak Berubah']); // sama persis

    expect(Activity::count())->toBe($countBefore);
});

test('activity log ter-create saat riwayat pangkat dibuat', function () {
    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create();

    $this->actingAs($admin);

    $riwayat = RiwayatPangkat::factory()->create(['pegawai_id' => $pegawai->id]);

    $log = Activity::where('subject_type', RiwayatPangkat::class)
        ->where('description', 'created')
        ->latest()->first();

    expect($log)->not->toBeNull()
        ->and($log->subject_id)->toBe($riwayat->id);
});

test('activity log ter-create saat iam role diupdate', function () {
    $admin = Pegawai::factory()->admin()->create();
    $app = IamApplication::factory()->create();
    $role = IamRole::factory()->create([
        'iam_application_id' => $app->id,
        'nama' => 'Role Lama',
    ]);

    $this->actingAs($admin);

    $role->update(['nama' => 'Role Baru']);

    $log = Activity::where('subject_type', IamRole::class)
        ->where('description', 'updated')
        ->latest()->first();

    expect($log)->not->toBeNull()
        ->and($log->properties['old']['nama'])->toBe('Role Lama')
        ->and($log->properties['new']['nama'])->toBe('Role Baru');
});

test('halaman activity log hanya bisa diakses admin', function () {
    $admin = Pegawai::factory()->admin()->create();
    $operator = Pegawai::factory()->operator()->create();
    $viewer = Pegawai::factory()->viewer()->create();

    $this->actingAs($admin)->get('/activity-log')->assertOk();
    $this->actingAs($operator)->get('/activity-log')->assertForbidden();
    $this->actingAs($viewer)->get('/activity-log')->assertForbidden();
});

test('halaman activity log menampilkan daftar aktivitas', function () {
    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create(['nama_lengkap' => 'Target Log']);

    $this->actingAs($admin);
    $pegawai->update(['nama_lengkap' => 'Setelah Update']);

    $this->actingAs($admin)
        ->get('/activity-log')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-log/index')
            ->has('activities.data')
        );
});
```

- [ ] **Step 3.6: Jalankan test, pastikan FAIL**

```bash
php artisan test tests/Feature/ActivityLogTest.php --stop-on-failure
```

Expected: FAIL — `LogsActivity` belum ditambahkan ke model, route belum ada

- [ ] **Step 3.7: Tambahkan `LogsActivity` ke model Pegawai**

Tambahkan import dan trait di `app/Models/Pegawai.php`:

```php
// Tambahkan setelah use statements yang sudah ada
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
```

Tambahkan `LogsActivity` di `use` clause class:
```php
use Filterable, HasApiTokens, HasFactory, HasUlids, LogsActivity, Notifiable, SoftDeletes, TwoFactorAuthenticatable;
```

Tambahkan method di dalam class (sebelum closing `}`):
```php
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logOnlyDirty()
        ->dontSubmitEmptyLogs()
        ->logFillable()
        ->setDescriptionForEvent(fn (string $eventName) => $eventName);
}
```

- [ ] **Step 3.8: Tambahkan `LogsActivity` ke 8 model data kepegawaian lainnya**

Lakukan hal yang sama (tambahkan import, trait, dan method `getActivitylogOptions()`) ke file-file berikut. Setiap model menggunakan konfigurasi yang **identik** dengan Step 3.7 di atas:

**`app/Models/RiwayatPangkat.php`:**
```php
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
```
Tambahkan `LogsActivity` ke `use` clause, tambahkan method:
```php
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logOnlyDirty()
        ->dontSubmitEmptyLogs()
        ->logFillable()
        ->setDescriptionForEvent(fn (string $eventName) => $eventName);
}
```

Ulangi langkah di atas untuk ketujuh model berikut dengan konten **identis**:
- `app/Models/RiwayatJabatan.php`
- `app/Models/RiwayatPendidikan.php`
- `app/Models/RiwayatDiklat.php`
- `app/Models/Keluarga.php`
- `app/Models/DokumenPegawai.php`
- `app/Models/HukumanDisiplin.php`
- `app/Models/Penghargaan.php`

- [ ] **Step 3.9: Tambahkan `LogsActivity` ke 4 model IAM**

Ulangi untuk keempat model IAM dengan konfigurasi **identis**:
- `app/Models/IamApplication.php`
- `app/Models/IamRole.php`
- `app/Models/IamPermission.php`
- `app/Models/IamUserRole.php`

---

### Sub-task 3C: Slow Query Logger

- [ ] **Step 3.10: Tambahkan `APP_LOG_SLOW_QUERIES` ke `.env.example`**

Tambahkan baris ini di `.env.example` (setelah blok `LOG_CHANNEL`):

```
APP_LOG_SLOW_QUERIES=false
```

- [ ] **Step 3.11: Tambahkan slow query listener di AppServiceProvider**

Tambahkan method `registerSlowQueryLogger()` ke `app/Providers/AppServiceProvider.php` dan panggil dari `boot()`:

```php
public function boot(): void
{
    Gate::policy(Pegawai::class, PegawaiPolicy::class);

    $this->configureDefaults();
    $this->registerSlowQueryLogger();
}

private function registerSlowQueryLogger(): void
{
    if (! config('app.log_slow_queries', false)) {
        return;
    }

    $threshold = config('app.slow_query_threshold_ms', 500);

    DB::listen(function ($query) use ($threshold): void {
        if ($query->time >= $threshold) {
            logger()->warning('[SLOW QUERY] '.$query->time.'ms | '.$query->sql, [
                'bindings' => $query->bindings,
                'time_ms'  => $query->time,
            ]);
        }
    });
}
```

Tambahkan juga ke `config/app.php` (di bagian bawah array, sebelum `];`):

```php
'log_slow_queries'        => env('APP_LOG_SLOW_QUERIES', false),
'slow_query_threshold_ms' => env('APP_SLOW_QUERY_THRESHOLD_MS', 500),
```

---

### Sub-task 3D: Controller & Route

- [ ] **Step 3.12: Buat `ActivityLogController`**

Buat file `app/Http/Controllers/ActivityLogController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $activities = Activity::with('causer', 'subject')
            ->when($request->input('subject_type'), fn ($q, $type) => $q->where('subject_type', $type))
            ->when($request->input('causer_id'), fn ($q, $id) => $q->where('causer_id', $id))
            ->when($request->input('date_from'), fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->input('date_to'), fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(20)
            ->through(fn (Activity $activity): array => [
                'id'           => $activity->id,
                'waktu'        => $activity->created_at->format('d M Y H:i'),
                'oleh'         => $activity->causer?->nama_lengkap ?? 'Sistem',
                'aksi'         => $activity->description,
                'model'        => class_basename($activity->subject_type ?? ''),
                'subject_id'   => $activity->subject_id,
                'old'          => $activity->properties['old'] ?? [],
                'new'          => $activity->properties['new'] ?? [],
            ]);

        $subjectTypes = Activity::distinct()
            ->whereNotNull('subject_type')
            ->pluck('subject_type')
            ->map(fn ($type) => class_basename($type))
            ->values();

        return inertia('activity-log/index', compact('activities', 'subjectTypes'));
    }
}
```

- [ ] **Step 3.13: Tambahkan route activity-log di `routes/web.php`**

Tambahkan import dan route baru. Di bagian atas `routes/web.php`, tambahkan import:

```php
use App\Http\Controllers\ActivityLogController;
```

Tambahkan route baru setelah route dashboard (setelah baris `Route::get('dashboard', ...)`):

```php
Route::middleware(['auth', 'verified', 'iam.permission:iam-manage'])
    ->get('activity-log', ActivityLogController::class)
    ->name('activity-log.index');
```

---

### Sub-task 3E: Frontend

- [ ] **Step 3.14: Buat halaman `activity-log/index.tsx`**

Buat file `resources/js/pages/activity-log/index.tsx`:

```tsx
import { PaginationWrapper } from '@/components/pagination-wrapper';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { IamPaginatedData } from '@/types';
import { router } from '@inertiajs/react';
import { useCallback, useState } from 'react';

type ActivityItem = {
    id: number;
    waktu: string;
    oleh: string;
    aksi: 'created' | 'updated' | 'deleted';
    model: string;
    subject_id: string;
    old: Record<string, unknown>;
    new: Record<string, unknown>;
};

type Props = {
    activities: IamPaginatedData<ActivityItem>;
    subjectTypes: string[];
};

const aksiBadgeVariant = {
    created: 'default',
    updated: 'secondary',
    deleted: 'destructive',
} as const;

export default function ActivityLogIndex({ activities, subjectTypes }: Props) {
    const [filters, setFilters] = useState({
        subject_type: '',
        date_from: '',
        date_to: '',
    });

    const applyFilters = useCallback(() => {
        router.get(
            route('activity-log.index'),
            Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '')),
            { preserveState: true, replace: true },
        );
    }, [filters]);

    return (
        <AppLayout breadcrumbs={[{ title: 'Activity Log', href: route('activity-log.index') }]}>
            <div className="space-y-4 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Filter</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-3">
                        <Select
                            value={filters.subject_type}
                            onValueChange={(v) => setFilters((f) => ({ ...f, subject_type: v }))}
                        >
                            <SelectTrigger className="w-48">
                                <SelectValue placeholder="Semua model" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">Semua model</SelectItem>
                                {subjectTypes.map((t) => (
                                    <SelectItem key={t} value={t}>
                                        {t}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Input
                            type="date"
                            className="w-44"
                            value={filters.date_from}
                            onChange={(e) => setFilters((f) => ({ ...f, date_from: e.target.value }))}
                            placeholder="Dari tanggal"
                        />
                        <Input
                            type="date"
                            className="w-44"
                            value={filters.date_to}
                            onChange={(e) => setFilters((f) => ({ ...f, date_to: e.target.value }))}
                            placeholder="Sampai tanggal"
                        />
                        <button
                            onClick={applyFilters}
                            className="rounded bg-primary px-4 py-2 text-sm text-primary-foreground hover:bg-primary/90"
                        >
                            Terapkan
                        </button>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-40">Waktu</TableHead>
                                    <TableHead>Oleh</TableHead>
                                    <TableHead className="w-24">Aksi</TableHead>
                                    <TableHead>Model</TableHead>
                                    <TableHead>Perubahan</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {activities.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={5} className="text-center text-muted-foreground py-8">
                                            Tidak ada aktivitas
                                        </TableCell>
                                    </TableRow>
                                )}
                                {activities.data.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell className="text-sm text-muted-foreground">{item.waktu}</TableCell>
                                        <TableCell>{item.oleh}</TableCell>
                                        <TableCell>
                                            <Badge variant={aksiBadgeVariant[item.aksi] ?? 'outline'}>
                                                {item.aksi}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <span className="font-mono text-sm">{item.model}</span>
                                            <span className="ml-1 text-xs text-muted-foreground">#{item.subject_id.slice(0, 8)}</span>
                                        </TableCell>
                                        <TableCell>
                                            {item.aksi === 'updated' && Object.keys(item.new).length > 0 && (
                                                <div className="space-y-1 text-xs">
                                                    {Object.entries(item.new).map(([key, newVal]) => (
                                                        <div key={key} className="flex gap-1">
                                                            <span className="font-mono text-muted-foreground">{key}:</span>
                                                            <span className="line-through text-red-500">{String(item.old[key] ?? '')}</span>
                                                            <span>→</span>
                                                            <span className="text-green-600">{String(newVal)}</span>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                            {item.aksi === 'created' && (
                                                <span className="text-xs text-muted-foreground">Record baru dibuat</span>
                                            )}
                                            {item.aksi === 'deleted' && (
                                                <span className="text-xs text-muted-foreground">Record dihapus</span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <PaginationWrapper meta={activities.meta} />
            </div>
        </AppLayout>
    );
}
```

---

### Sub-task 3F: Verifikasi

- [ ] **Step 3.15: Jalankan test ActivityLog, pastikan semua PASS**

```bash
php artisan test tests/Feature/ActivityLogTest.php --stop-on-failure
```

Expected: Semua PASS (6 test)

- [ ] **Step 3.16: Jalankan seluruh test suite, pastikan tidak ada regresi**

```bash
php artisan test --stop-on-failure
```

Expected: Semua PASS

- [ ] **Step 3.17: Commit**

```bash
git add app/Models/ \
        app/Providers/AppServiceProvider.php \
        app/Http/Controllers/ActivityLogController.php \
        resources/js/pages/activity-log/ \
        routes/web.php \
        config/app.php \
        .env.example \
        tests/Feature/ActivityLogTest.php \
        composer.json \
        composer.lock \
        database/migrations/
git commit -m "feat: implementasi audit trail (spatie/laravel-activitylog) dan slow query logger"
```

---

## Task 4: Verifikasi Fase 2 Selesai

- [ ] **Step 4.1: Jalankan seluruh test suite**

```bash
php artisan test --stop-on-failure
```

Expected: Semua PASS

- [ ] **Step 4.2: Verifikasi commit history**

```bash
git log --oneline -5
```

Expected: 3 commit baru (2.3, 2.2, 2.1) terlihat di log

---

## Ringkasan Perubahan Fase 2

| Task | Before | After |
|---|---|---|
| 2.3 Duplikasi Query | 2 query identis di `search()` | 1 query `paginate()` |
| 2.2 FormRequest IAM | Inline `$request->validate()` di 2 controller | 3 FormRequest class di `app/Http/Requests/Iam/` |
| 2.1 Audit Trail | Tidak ada pencatatan perubahan | Activity log otomatis di 13 model + halaman admin |
| 2.1 Slow Query | Tidak ada monitoring | `DB::listen()` log query >500ms ke Laravel log |
