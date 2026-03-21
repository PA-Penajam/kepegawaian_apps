# IAM Security Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memperbaiki seluruh temuan code review (CRITICAL → HIGH → MEDIUM → LOW) pada implementasi IAM SSO Gateway untuk memastikan sistem aman, robust, dan production-ready.

**Architecture:** Perbaikan dibagi 4 fase berurutan. Setiap fase harus selesai dan di-review sebelum lanjut ke fase berikutnya. Fase CRITICAL/HIGH fokus pada keamanan backend. Fase MEDIUM mencakup refactoring DRY, seeder, dan frontend. Fase LOW memperbaiki konsistensi dan accessibility.

**Tech Stack:** Laravel 11, PHP 8.3, Pest (testing), Inertia.js + React (frontend), shadcn/ui

> **Catatan ULID:** `IamApplication` menggunakan `HasUlids`, sehingga `$app->id` bertipe `string` (ULID), bukan `int`. Parameter type hint `string $applicationId` pada service sudah benar.

---

## File Map

### File Baru yang Dibuat

| File | Tanggung Jawab |
|------|---------------|
| `app/Services/IamAuthorizationService.php` | Centralized permission lookup (DRY — mengganti duplikasi di 3 tempat) |
| `tests/Helpers/IamTestHelper.php` | Shared `makeIamHeaders()` helper untuk test |
| `tests/Feature/Iam/PermissionControllerTest.php` | Test IDOR untuk PermissionController |
| `tests/Feature/Iam/RoleControllerTest.php` | Test IDOR untuk RoleController |
| `tests/Feature/Iam/IamCheckTest.php` | Test endpoint `check()` |
| `tests/Feature/Iam/SsoCallbackTest.php` | Test `SsoController::callback()` |
| `resources/js/components/iam/ApiSecretModal.tsx` | Komponen modal API secret yang di-share |

### File yang Dimodifikasi

| File | Perubahan |
|------|-----------|
| `app/Http/Middleware/VerifyIamSignature.php` | `merge()` → `attributes->set()`, pesan error seragam, sertakan body hash |
| `app/Http/Middleware/VerifyIamPermission.php` | Gunakan `IamAuthorizationService`, hardcoded slug → config, cache query |
| `app/Http/Middleware/VerifyHmacSignature.php` | Validasi secret tidak kosong, sertakan body hash |
| `app/Http/Controllers/Api/IamController.php` | Ganti `get()` → `attributes->get()`, validasi app pada exchange-code, DB::transaction, gunakan service |
| `app/Http/Controllers/SsoController.php` | Fix open redirect — parse dan bandingkan host |
| `app/Http/Controllers/Iam/PermissionController.php` | Tambah IDOR check, fix slug validation |
| `app/Http/Controllers/Iam/RoleController.php` | Tambah IDOR check, scope permission_ids |
| `app/Models/IamApplication.php` | Bersihkan `$fillable`, tambah `$hidden`, fix `verifySecret()` → `hash_equals()` |
| `app/Models/IamUserRole.php` | Tambah relasi `user()` |
| `database/seeders/DatabaseSeeder.php` | Hapus `bcrypt()`, wajib env variable (tanpa fallback di production) |
| `database/seeders/IamSeeder.php` | Fix urutan, chunking, tambah permission `iam-manage` |
| `config/iam.php` | Tambah `app_slug` config |
| `config/kepegawaian.php` | Dokumentasi validasi secret tidak boleh kosong |
| `routes/api.php` | Tambah rate limiting |
| `routes/web.php` | Aktifkan permission check untuk IAM routes |
| `resources/js/pages/iam/aplikasi/index.tsx` | Error handling, controlled dialog, hapus `confirm()` |
| `resources/js/pages/iam/aplikasi/show.tsx` | Error handling, controlled dialog, refactor sub-komponen, tidak expose API key |
| `resources/js/pages/iam/users/akses.tsx` | Fix race condition, error handling, fix type assertion |
| `resources/js/pages/iam/users/index.tsx` | Fix label pagination |
| `resources/js/types/iam.ts` | Konsolidasi PaginatedData |
| `tests/Pest.php` | Auto-load IamTestHelper |
| Test files di `tests/Feature/Iam/` | Gunakan shared helper, hapus definisi lokal `makeIamHeaders` |

---

## FASE 1 — CRITICAL Security Fixes

---

### Task 1: Hapus `.env` dari Git Tracking + Dokumentasi Secret Rotation

**Files:**
- Modify: `.gitignore`
- Modify: `.env.example`

> ⚠️ **PENTING:** Setelah task ini, **rotate semua secrets**: generate `APP_KEY` baru (`php artisan key:generate`), ganti password DB, generate `ATTENDANCE_HMAC_SECRET` baru (`php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"`), regenerate semua `api_key` dan `api_secret` aplikasi IAM.

- [ ] **Step 1: Pastikan `.env` sudah ada di `.gitignore`**

```bash
grep -n "^\.env$" .gitignore
```

Jika tidak ada, tambahkan baris `.env` di `.gitignore`.

- [ ] **Step 2: Hapus `.env` dari git tracking tanpa menghapus file**

```bash
git rm --cached .env
```

- [ ] **Step 3: Verifikasi kuat bahwa `.env` tidak lagi ter-track**

```bash
git ls-files --error-unmatch .env 2>&1 || echo "GOOD: .env tidak ter-track"
```

Expected output: `error: pathspec '.env' did not match any file(s) known to git` atau pesan `GOOD`.

```bash
git status
```

Expected: `.env` muncul sebagai "deleted" (dari index), bukan sebagai untracked.

- [ ] **Step 4: Update `.env.example` dengan placeholder IAM config**

Tambahkan di `.env.example`:
```bash
# IAM SSO Gateway Configuration
# Generate dengan: php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
ATTENDANCE_HMAC_SECRET=
IAM_TOKEN_TTL_HOURS=8
IAM_SSO_CODE_TTL=60
IAM_APP_SLUG=kepegawaian

# Seeder credentials (WAJIB diisi di production)
SEEDER_ADMIN_PASSWORD=
SEEDER_OPERATOR_PASSWORD=
```

- [ ] **Step 5: Commit**

```bash
git add .gitignore .env.example
git commit -m "security: hapus .env dari git tracking, update .env.example"
```

---

### Task 2: Konsolidasi Test Helper + Update Middleware HMAC Payload (Atomik — Harus Bersama)

> **Kenapa digabung?** Helper `makeIamHeaders` dan kedua middleware HMAC menggunakan format payload yang sama. Mengupdate helper tanpa middleware (atau sebaliknya) akan menyebabkan semua test IAM break selama 7+ task berikutnya — melanggar prinsip TDD "selalu mulai dari baseline hijau". Keduanya diupdate dalam satu commit atomik.

**Files:**
- Create: `tests/Helpers/IamTestHelper.php`
- Modify: `tests/Pest.php`
- Modify: `app/Http/Middleware/VerifyIamSignature.php`
- Modify: `app/Http/Middleware/VerifyHmacSignature.php`
- Modify: `tests/Feature/Iam/IamExchangeCodeTest.php`
- Modify: `tests/Feature/Iam/IamLogoutTest.php`
- Modify: `tests/Feature/Iam/IamValidateTest.php`
- Modify: `tests/Feature/Iam/VerifyIamSignatureTest.php`

- [ ] **Step 1: Jalankan semua test IAM dulu untuk baseline**

```bash
php artisan test tests/Feature/Iam/ -v
```

Expected: Semua pass (ini baseline sebelum kita ubah apapun)

- [ ] **Step 2: Buat `tests/Helpers/IamTestHelper.php`**

```php
<?php

use App\Models\IamApplication;
use Illuminate\Support\Facades\Crypt;

if (! function_exists('makeIamHeaders')) {
    /**
     * Generate valid IAM signature headers untuk testing.
     * Format payload: METHOD:PATH:SORTED_QUERY:BODY_SHA256:TIMESTAMP
     *
     * @param  array<string, mixed>  $body  Request body
     * @param  array<string, mixed>  $query Query params
     * @return array{0: IamApplication, 1: array<string, string>}
     */
    function makeIamHeaders(string $method, string $path, array $body = [], array $query = []): array
    {
        $app      = IamApplication::factory()->create(['is_active' => true]);
        $secret   = Crypt::decryptString($app->api_secret_hash);
        $ts       = now()->timestamp;
        $qs       = http_build_query(collect($query)->sortKeys()->all());
        $bodyHash = hash('sha256', $body ? json_encode($body) : '');
        $payload  = strtoupper($method) . ':' . $path . ':' . $qs . ':' . $bodyHash . ':' . $ts;
        $sig      = hash_hmac('sha256', $payload, $secret);

        return [$app, [
            'X-App-Key'   => $app->api_key,
            'X-Timestamp' => $ts,
            'X-Signature' => $sig,
        ]];
    }
}
```

- [ ] **Step 3: Tambahkan auto-load di `tests/Pest.php`**

Tambahkan di baris paling awal `tests/Pest.php` (sebelum `uses()`):

```php
require_once __DIR__ . '/Helpers/IamTestHelper.php';
```

- [ ] **Step 4: Hapus definisi lokal `makeIamHeaders` dari 4 file test**

Dari masing-masing file berikut, hapus seluruh blok:
```php
if (! function_exists('makeIamHeaders')) {
    function makeIamHeaders(...) { ... }
}
```

File yang perlu diedit:
- `tests/Feature/Iam/IamExchangeCodeTest.php`
- `tests/Feature/Iam/IamLogoutTest.php`
- `tests/Feature/Iam/IamValidateTest.php`
- `tests/Feature/Iam/VerifyIamSignatureTest.php`

- [ ] **Step 5: Jalankan semua test IAM untuk konfirmasi tidak ada yang break**

```bash
php artisan test tests/Feature/Iam/ -v
```

- [ ] **Step 5: Update KEDUA middleware HMAC agar payload format sesuai dengan helper yang baru**

Update `app/Http/Middleware/VerifyIamSignature.php` — ganti blok payload (baris 34–39):

```php
// Rekonstruksi payload: METHOD:PATH:SORTED_QUERY:BODY_SHA256:TIMESTAMP
$queryString = http_build_query(collect($request->query())->sortKeys()->all());
$bodyHash    = hash('sha256', $request->getContent());
$payload     = strtoupper($request->method())
    . ':' . $request->getPathInfo()
    . ':' . $queryString
    . ':' . $bodyHash
    . ':' . $timestamp;
```

Update `app/Http/Middleware/VerifyHmacSignature.php` — tambah validasi secret kosong + body hash:

```php
$secret = config('kepegawaian.secret_key');
if (empty($secret)) {
    \Illuminate\Support\Facades\Log::critical('ATTENDANCE_HMAC_SECRET tidak dikonfigurasi');
    return response()->json(['message' => 'Service configuration error'], 500);
}

// Payload menyertakan body hash — mencegah tampering body
$queryString = http_build_query(collect($request->query())->sortKeys()->all());
$bodyHash    = hash('sha256', $request->getContent());
$payload     = strtoupper($request->method())
    . ':' . $request->getPathInfo()
    . ':' . $queryString
    . ':' . $bodyHash
    . ':' . $timestamp;
```

> ⚠️ **BREAKING CHANGE untuk klien:** Format payload HMAC berubah. Klien `VerifyHmacSignature` (attendance-qr-system) harus memperbarui kalkulasi signature untuk menyertakan `body_sha256`.

- [ ] **Step 6: Jalankan semua test IAM untuk konfirmasi semuanya pass**

```bash
php artisan test tests/Feature/Iam/ -v
```

Expected: Semua pass (helper dan middleware sekarang menggunakan format payload yang sama)

- [ ] **Step 7: Commit — helper + kedua middleware dalam satu commit atomik**

```bash
git add tests/Helpers/IamTestHelper.php tests/Pest.php tests/Feature/Iam/ app/Http/Middleware/VerifyIamSignature.php app/Http/Middleware/VerifyHmacSignature.php
git commit -m "security: konsolidasi makeIamHeaders, sertakan body hash HMAC payload, validasi secret tidak kosong"
```

---

### Task 3: Fix Request Injection — `merge()` → `attributes->set()`

**Files:**
- Modify: `app/Http/Middleware/VerifyIamSignature.php`
- Modify: `app/Http/Controllers/Api/IamController.php`
- Modify: `tests/Feature/Iam/VerifyIamSignatureTest.php`

- [ ] **Step 1: Tulis failing test yang membuktikan injeksi via query string dicegah**

Tambahkan di `tests/Feature/Iam/VerifyIamSignatureTest.php`:

```php
it('controller tidak menggunakan nilai _iam_app yang di-inject user via query string', function () {
    // Arrange: siapkan IAM signature yang valid untuk app A
    [$appA, $headers] = makeIamHeaders('GET', '/api/v1/iam/validate');
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    // Act: kirim request dengan app A yang valid, TAPI juga inject _iam_app via query
    // Jika controller menggunakan $request->get('_iam_app'), ini bisa di-spoof
    // Setelah fix, controller menggunakan $request->attributes->get('iam_app') yang aman
    $response = $this->getJson('/api/v1/iam/validate?_iam_app=injected_value', array_merge(
        $headers,
        ['Authorization' => "Bearer {$token}"]
    ));

    // Assert: response harus success dengan app A (bukan crash karena 'injected_value')
    // Sebelum fix: 'injected_value' string di-pass ke query builder, mungkin error 500
    // Setelah fix: attributes->get() mengabaikan query params, menggunakan app A dari middleware
    $response->assertStatus(200);
    $response->assertJsonStructure(['user', 'roles', 'permissions']);
});
```

- [ ] **Step 2: Jalankan untuk pastikan test berjalan (bisa pass atau fail tergantung behavior saat ini)**

```bash
php artisan test tests/Feature/Iam/VerifyIamSignatureTest.php --filter="inject user via query" -v
```

- [ ] **Step 3: Update `VerifyIamSignature.php` — ganti `merge()` dengan `attributes->set()`**

Ganti baris 53 di `app/Http/Middleware/VerifyIamSignature.php`:

```php
// SEBELUM (rentan — user bisa inject _iam_app via query string):
$request->merge(['_iam_app' => $app]);

// SESUDAH (aman — attributes tidak bisa dimanipulasi user input):
$request->attributes->set('iam_app', $app);
```

Juga seragamkan pesan error di baris 31:
```php
// SEBELUM (info leakage — membedakan "app tidak ada" vs "signature salah"):
return response()->json(['message' => 'Unknown application'], 401);

// SESUDAH (seragam):
return response()->json(['message' => 'Invalid credentials'], 401);
```

- [ ] **Step 4: Update `IamController.php` — ganti `$request->get()` dengan `$request->attributes->get()`**

Ganti dua kemunculan di `app/Http/Controllers/Api/IamController.php`:

```php
// Baris 18 (method validate):
$app = $request->get('_iam_app');
// GANTI dengan:
$app = $request->attributes->get('iam_app');

// Baris 43 (method check):
$app = $request->get('_iam_app');
// GANTI dengan:
$app = $request->attributes->get('iam_app');
```

- [ ] **Step 5: Jalankan seluruh test IAM**

```bash
php artisan test tests/Feature/Iam/ -v
```

Expected: Semua pass

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/VerifyIamSignature.php app/Http/Controllers/Api/IamController.php tests/Feature/Iam/VerifyIamSignatureTest.php
git commit -m "security: ganti request->merge ke attributes->set, seragamkan pesan error auth"
```

---

### Task 4: Fix Race Condition SSO Code Exchange (Atomic + DB Transaction)

**Files:**
- Modify: `app/Http/Controllers/Api/IamController.php`
- Modify: `tests/Feature/Iam/IamExchangeCodeTest.php`

- [ ] **Step 1: Tulis failing tests untuk expired dan used code**

Tambahkan di `tests/Feature/Iam/IamExchangeCodeTest.php`:

```php
it('menolak code yang sudah expired', function () {
    [$app, $headers] = makeIamHeaders('POST', '/api/v1/iam/exchange-code');
    $user = User::factory()->create();
    $code = Str::random(64);

    IamSsoCode::create([
        'code'       => $code,
        'user_id'    => $user->id,
        'app_slug'   => $app->slug,
        'expires_at' => now()->subMinutes(2), // sudah expired
    ]);

    $response = $this->postJson('/api/v1/iam/exchange-code', ['code' => $code], $headers);
    $response->assertStatus(400);
});

it('menolak code yang sudah digunakan (used_at tidak null)', function () {
    [$app, $headers] = makeIamHeaders('POST', '/api/v1/iam/exchange-code');
    $user = User::factory()->create();
    $code = Str::random(64);

    IamSsoCode::create([
        'code'       => $code,
        'user_id'    => $user->id,
        'app_slug'   => $app->slug,
        'expires_at' => now()->addSeconds(60),
        'used_at'    => now(), // sudah digunakan
    ]);

    $response = $this->postJson('/api/v1/iam/exchange-code', ['code' => $code], $headers);
    $response->assertStatus(400);
});
```

- [ ] **Step 2: Jalankan untuk pastikan fail**

```bash
php artisan test tests/Feature/Iam/IamExchangeCodeTest.php --filter="menolak code" -v
```

Expected: FAIL

- [ ] **Step 3: Update `IamController::exchangeCode()` — atomic update + DB::transaction**

```php
use Illuminate\Support\Facades\DB;

public function exchangeCode(Request $request): JsonResponse
{
    $request->validate(['code' => 'required|string|size:64']);

    $app = $request->attributes->get('iam_app');

    return DB::transaction(function () use ($request, $app): JsonResponse {
        // Atomic: update hanya jika code valid, milik app yang benar, belum dipakai, belum expired
        $affected = IamSsoCode::where('code', $request->code)
            ->where('app_slug', $app->slug)        // cegah cross-app token theft
            ->whereNull('used_at')                 // belum digunakan
            ->where('expires_at', '>', now())      // belum expired
            ->update(['used_at' => now()]);

        if ($affected === 0) {
            return response()->json(['message' => 'Invalid or expired code'], 400);
        }

        // Ambil ssoCode setelah atomic update (dalam transaksi yang sama)
        $ssoCode  = IamSsoCode::where('code', $request->code)->first();
        $user     = $ssoCode->user;
        $ttlHours = (int) config('iam.token_ttl_hours', 8);

        // Scope token per aplikasi — bukan ['*'] yang terlalu luas
        $token = $user->createToken(
            "sso:{$app->slug}",
            ["app:{$app->slug}"],
            now()->addHours($ttlHours)
        );

        return response()->json([
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at->timestamp,
        ]);
    });
}
```

- [ ] **Step 4: Jalankan semua IamExchangeCodeTest**

```bash
php artisan test tests/Feature/Iam/IamExchangeCodeTest.php -v
```

Expected: Semua pass

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/IamController.php tests/Feature/Iam/IamExchangeCodeTest.php
git commit -m "security: atomic SSO code exchange dengan DB::transaction, fix race condition dan cross-app theft"
```

---

### Task 5: Fix Open Redirect — Validasi Host URL

**Files:**
- Modify: `app/Http/Controllers/SsoController.php`
- Modify: `tests/Feature/Iam/SsoLoginTest.php`

- [ ] **Step 1: Tulis failing tests untuk bypass vectors**

Tambahkan di `tests/Feature/Iam/SsoLoginTest.php`:

```php
it('menolak open redirect via subdomain spoofing', function () {
    $app  = IamApplication::factory()->create(['slug' => 'att-app', 'url' => 'http://att.local', 'is_active' => true]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/sso/login?app=att-app&redirect=http://att.local.evil.com/steal');
    $response->assertStatus(422);
});

it('menolak open redirect via URL authority confusion', function () {
    $app  = IamApplication::factory()->create(['slug' => 'att-app', 'url' => 'http://att.local', 'is_active' => true]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/sso/login?app=att-app&redirect=http://att.local@evil.com/steal');
    $response->assertStatus(422);
});

it('mengizinkan redirect ke subdirectory host yang sama', function () {
    $app  = IamApplication::factory()->create(['slug' => 'att-app', 'url' => 'http://att.local', 'is_active' => true]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/sso/login?app=att-app&redirect=http://att.local/callback');

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    $this->assertStringStartsWith('http://att.local/callback?code=', $location);
});
```

- [ ] **Step 2: Jalankan untuk pastikan fail**

```bash
php artisan test tests/Feature/Iam/SsoLoginTest.php --filter="menolak open redirect" -v
```

Expected: FAIL

- [ ] **Step 3: Update `generateCodeAndRedirect()` di `SsoController.php`**

Ganti method (baris 59–79):

```php
private function generateCodeAndRedirect(int $userId, IamApplication $app, string $redirectUrl): RedirectResponse
{
    // Validasi host: redirect harus ke domain yang sama persis dengan app terdaftar
    $appHost      = parse_url($app->url, PHP_URL_HOST);
    $redirectHost = parse_url($redirectUrl, PHP_URL_HOST);

    if (! $appHost || ! $redirectHost || $appHost !== $redirectHost) {
        abort(422, 'Redirect URL tidak diizinkan untuk aplikasi ini');
    }

    $ttl  = (int) config('iam.sso_code_ttl_seconds', 60);
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
```

- [ ] **Step 4: Jalankan semua SsoLoginTest**

```bash
php artisan test tests/Feature/Iam/SsoLoginTest.php -v
```

Expected: Semua pass

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/SsoController.php tests/Feature/Iam/SsoLoginTest.php
git commit -m "security: fix open redirect - parse_url host comparison, tolak subdomain spoofing"
```

---

### Task 6: Tambah Rate Limiting pada API Routes

**Files:**
- Modify: `routes/api.php`
- Modify: `tests/Feature/Iam/IamExchangeCodeTest.php`

- [ ] **Step 1: Tulis failing test untuk rate limiting**

Tambahkan di `tests/Feature/Iam/IamExchangeCodeTest.php`:

```php
it('menolak request ke-11 pada exchange-code dengan HTTP 429', function () {
    // Kirim 10 request valid (semua akan gagal dengan 400 karena code tidak ada, tapi tidak ter-throttle)
    for ($i = 0; $i < 10; $i++) {
        [$app, $headers] = makeIamHeaders('POST', '/api/v1/iam/exchange-code');
        $this->postJson('/api/v1/iam/exchange-code', ['code' => str_repeat('a', 64)], $headers);
    }

    // Request ke-11 harus mendapat 429 Too Many Requests
    [$app, $headers] = makeIamHeaders('POST', '/api/v1/iam/exchange-code');
    $response = $this->postJson('/api/v1/iam/exchange-code', ['code' => str_repeat('a', 64)], $headers);
    $response->assertStatus(429);
});
```

- [ ] **Step 2: Jalankan untuk pastikan fail**

```bash
php artisan test tests/Feature/Iam/IamExchangeCodeTest.php --filter="429" -v
```

Expected: FAIL (saat ini tidak ada throttle)

- [ ] **Step 3: Update `routes/api.php`**

```php
<?php

use App\Http\Controllers\Api\IamController;
use App\Http\Controllers\Api\PegawaiApiController;
use Illuminate\Support\Facades\Route;

// Pegawai API — throttle 60 req/menit
Route::middleware(['auth:sanctum', 'verify.hmac', 'throttle:60,1'])
    ->prefix('v1')
    ->group(function () {
        Route::get('pegawai/{nip}', [PegawaiApiController::class, 'show'])
            ->where('nip', '^\d{18}$');
        Route::get('pegawai', [PegawaiApiController::class, 'index']);
    });

// IAM validate/check/logout — throttle 120 req/menit
Route::middleware(['auth:sanctum', 'iam.signature', 'throttle:120,1'])
    ->prefix('v1/iam')
    ->group(function () {
        Route::get('validate', [IamController::class, 'validate']);
        Route::get('check', [IamController::class, 'check']);
        Route::post('logout', [IamController::class, 'logout']);
    });

// Exchange code — throttle ketat 10 req/menit (endpoint sensitif SSO)
Route::middleware(['iam.signature', 'throttle:10,1'])
    ->prefix('v1/iam')
    ->group(function () {
        Route::post('exchange-code', [IamController::class, 'exchangeCode']);
    });
```

- [ ] **Step 4: Jalankan semua test**

```bash
php artisan test tests/Feature/Iam/ tests/Feature/Api/ -v
```

Expected: Semua pass termasuk test 429

- [ ] **Step 5: Commit**

```bash
git add routes/api.php tests/Feature/Iam/IamExchangeCodeTest.php
git commit -m "security: tambah rate limiting throttle pada semua API routes (10/1min untuk exchange-code)"
```

---

### Task 7: Fix DatabaseSeeder — Hapus Double Hashing Password

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `.env.example`

- [ ] **Step 1: Update `DatabaseSeeder.php`**

```php
use Illuminate\Support\Facades\App;

// Di awal method run(), tambahkan guard:
if (App::isProduction() && (empty(env('SEEDER_ADMIN_PASSWORD')) || empty(env('SEEDER_OPERATOR_PASSWORD')))) {
    throw new \RuntimeException('SEEDER_ADMIN_PASSWORD dan SEEDER_OPERATOR_PASSWORD wajib diset di environment production');
}

// Create admin user — TANPA bcrypt() karena User model sudah punya cast 'hashed'
$admin = User::query()->updateOrCreate([
    'email' => 'admin@pa-penajam.go.id',
], [
    'name'              => 'Administrator',
    'password'          => env('SEEDER_ADMIN_PASSWORD') ?: 'Admin@PA2026!', // fallback HANYA untuk local
    'email_verified_at' => now(),
]);

// Create operator user
$operator = User::query()->updateOrCreate([
    'email' => 'operator@pa-penajam.go.id',
], [
    'name'              => 'Operator',
    'password'          => env('SEEDER_OPERATOR_PASSWORD') ?: 'Operator@PA2026!', // fallback HANYA untuk local
    'email_verified_at' => now(),
]);
```

> **Catatan penting:** Model `User` memiliki cast `'password' => 'hashed'`. Cukup assign string plain — Laravel akan otomatis hash saat save. Menambahkan `bcrypt()` menyebabkan double-hash (hash dari hash), sehingga login selalu gagal.

- [ ] **Step 2: Test bahwa password bisa digunakan login**

```bash
php artisan migrate:fresh --seed
```

Expected: Tidak ada error.

```bash
php artisan tinker --execute="
\$user = \App\Models\User::where('email', 'admin@pa-penajam.go.id')->first();
echo \Illuminate\Support\Facades\Hash::check('Admin@PA2026!', \$user->password) ? 'LOGIN OK' : 'LOGIN FAIL';
"
```

Expected: `LOGIN OK`

- [ ] **Step 3: Commit**

```bash
git add database/seeders/DatabaseSeeder.php .env.example
git commit -m "fix: hapus double hashing di seeder, tambah guard production untuk password env"
```

---

### Task 8: Jangan Expose Full API Key ke Frontend (Dipindah dari MEDIUM ke CRITICAL)

**Files:**
- Modify: `app/Http/Controllers/Iam/AplikasiController.php`
- Create: `resources/js/components/iam/ApiSecretModal.tsx`
- Modify: `resources/js/pages/iam/aplikasi/show.tsx`
- Modify: `resources/js/pages/iam/aplikasi/index.tsx`

- [ ] **Step 1: Update controller — masked api_key dalam Inertia props**

Di `app/Http/Controllers/Iam/AplikasiController.php`, method `show()`:

```php
return Inertia::render('iam/aplikasi/show', [
    'aplikasi' => array_merge($aplikasi->toArray(), [
        // Mask api_key — tampilkan 4 karakter pertama dan 8 terakhir saja
        'api_key_display' => substr($aplikasi->api_key, 0, 4) . str_repeat('*', 24) . substr($aplikasi->api_key, -8),
        'api_key' => null, // Hapus full api_key dari props
    ]),
    // ... relasi lainnya
]);
```

- [ ] **Step 2: Buat `ApiSecretModal.tsx` sebagai komponen shared**

Buat file `resources/js/components/iam/ApiSecretModal.tsx`:

```typescript
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useState } from 'react';

interface ApiSecretModalProps {
    apiSecret?: string;
    open: boolean;
    onClose: () => void;
}

export function ApiSecretModal({ apiSecret, open, onClose }: ApiSecretModalProps) {
    const [copied, setCopied] = useState(false);

    if (!apiSecret) return null;

    const handleCopy = async () => {
        await navigator.clipboard.writeText(apiSecret);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>API Secret Baru</DialogTitle>
                    <DialogDescription>
                        Simpan secret ini sekarang. Secret tidak akan ditampilkan lagi setelah halaman ini ditutup.
                    </DialogDescription>
                </DialogHeader>
                <div className="flex items-start gap-2 rounded border bg-muted p-3">
                    <code className="flex-1 break-all text-sm">{apiSecret}</code>
                    <Button variant="outline" size="sm" onClick={handleCopy}>
                        {copied ? 'Tersalin!' : 'Salin'}
                    </Button>
                </div>
                <Button onClick={onClose} className="w-full">Tutup & Saya Sudah Menyimpan Secret</Button>
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 3: Update `show.tsx` dan `index.tsx`**

Di kedua file:
- Import `ApiSecretModal` dari `@/components/iam/ApiSecretModal`
- Ganti implementasi modal yang duplikat dengan komponen baru
- Gunakan `api_key_display` (masked) bukan `api_key` lengkap untuk display
- Tombol copy menyalin `api_key_display` (bukan full key)

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Iam/AplikasiController.php resources/js/components/iam/ApiSecretModal.tsx resources/js/pages/iam/aplikasi/
git commit -m "security: jangan expose full api_key ke frontend, ekstrak ApiSecretModal, tampilkan masked key"
```

---

## FASE 2 — HIGH Security Fixes

---

### Task 9: Fix IDOR — Validasi Kepemilikan Permission dan Role

**Files:**
- Create: `tests/Feature/Iam/PermissionControllerTest.php`
- Create: `tests/Feature/Iam/RoleControllerTest.php`
- Modify: `app/Http/Controllers/Iam/PermissionController.php`
- Modify: `app/Http/Controllers/Iam/RoleController.php`

- [ ] **Step 1: Buat `tests/Feature/Iam/PermissionControllerTest.php`**

```php
<?php

use App\Models\IamApplication;
use App\Models\IamUserRole;
use App\Models\User;

beforeEach(function () {
    $cred = IamApplication::generateApiCredentials();
    $this->kepegawaian = IamApplication::create([
        'nama' => 'Kepegawaian', 'slug' => 'kepegawaian', 'url' => 'http://test.local',
        'api_key' => $cred['key'], 'api_secret_hash' => $cred['hash'], 'is_active' => true,
    ]);
    $this->adminRole = $this->kepegawaian->roles()->create(['nama' => 'Admin', 'slug' => 'admin']);
    $this->admin = User::factory()->create();
    IamUserRole::create(['user_id' => $this->admin->id, 'iam_role_id' => $this->adminRole->id, 'assigned_at' => now()]);
});

it('menolak update permission milik aplikasi lain (IDOR)', function () {
    $cred2 = IamApplication::generateApiCredentials();
    $app2  = IamApplication::create([
        'nama' => 'App 2', 'slug' => 'app-2', 'url' => 'https://app2.test',
        'api_key' => $cred2['key'], 'api_secret_hash' => $cred2['hash'], 'is_active' => true,
    ]);
    $permApp2 = $app2->permissions()->create(['nama' => 'Read', 'slug' => 'read']);

    $response = $this->actingAs($this->admin)
        ->put("/iam/aplikasi/{$this->kepegawaian->id}/permissions/{$permApp2->id}", [
            'nama' => 'Hacked', 'slug' => 'hacked',
        ]);

    $response->assertStatus(404);
    // Pastikan permission tidak berubah
    $this->assertDatabaseHas('iam_permissions', ['id' => $permApp2->id, 'nama' => 'Read']);
});

it('menolak hapus permission milik aplikasi lain (IDOR)', function () {
    $cred2 = IamApplication::generateApiCredentials();
    $app2  = IamApplication::create([
        'nama' => 'App 2', 'slug' => 'app-2', 'url' => 'https://app2.test',
        'api_key' => $cred2['key'], 'api_secret_hash' => $cred2['hash'], 'is_active' => true,
    ]);
    $permApp2 = $app2->permissions()->create(['nama' => 'Delete', 'slug' => 'delete']);

    $response = $this->actingAs($this->admin)
        ->delete("/iam/aplikasi/{$this->kepegawaian->id}/permissions/{$permApp2->id}");

    $response->assertStatus(404);
    $this->assertDatabaseHas('iam_permissions', ['id' => $permApp2->id]);
});

it('menolak slug permission duplikat dalam satu aplikasi', function () {
    $this->kepegawaian->permissions()->create(['nama' => 'Read', 'slug' => 'read']);

    $response = $this->actingAs($this->admin)
        ->post("/iam/aplikasi/{$this->kepegawaian->id}/permissions", [
            'nama' => 'Read Duplicate', 'slug' => 'read', // slug duplikat
        ]);

    $response->assertStatus(422);
});
```

- [ ] **Step 2: Jalankan untuk pastikan fail**

```bash
php artisan test tests/Feature/Iam/PermissionControllerTest.php -v
```

Expected: FAIL

- [ ] **Step 3: Update `PermissionController.php`**

```php
<?php

namespace App\Http\Controllers\Iam;

use App\Http\Controllers\Controller;
use App\Models\IamApplication;
use App\Models\IamPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    public function store(Request $request, IamApplication $aplikasi): RedirectResponse
    {
        $data = $request->validate([
            'nama'       => 'required|string|max:100',
            'slug'       => [
                'required', 'string', 'alpha_dash', 'max:100',
                Rule::unique('iam_permissions', 'slug')->where('iam_application_id', $aplikasi->id),
            ],
            'group'      => 'nullable|string|max:50',
            'keterangan' => 'nullable|string',
        ]);
        $aplikasi->permissions()->create($data);
        return back();
    }

    public function update(Request $request, IamApplication $aplikasi, IamPermission $permission): RedirectResponse
    {
        // IDOR check: permission harus milik aplikasi yang diminta
        abort_unless($permission->iam_application_id === $aplikasi->id, 404);

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
        // IDOR check: permission harus milik aplikasi yang diminta
        abort_unless($permission->iam_application_id === $aplikasi->id, 404);
        $permission->delete();
        return back();
    }
}
```

- [ ] **Step 4: Update `RoleController.php`**

```php
use Illuminate\Validation\Rule;

public function store(Request $request, IamApplication $aplikasi): RedirectResponse
{
    $data = $request->validate([
        'nama'             => 'required|string|max:100',
        'slug'             => [
            'required', 'string', 'alpha_dash',
            Rule::unique('iam_roles', 'slug')->where('iam_application_id', $aplikasi->id),
        ],
        'keterangan'       => 'nullable|string',
        'permission_ids'   => 'array',
        // Scope: permission_ids harus milik aplikasi yang sama
        'permission_ids.*' => [
            'exists:iam_permissions,id',
            Rule::exists('iam_permissions', 'id')->where('iam_application_id', $aplikasi->id),
        ],
    ]);

    $role = $aplikasi->roles()->create($data);
    if (! empty($data['permission_ids'])) {
        $role->permissions()->sync($data['permission_ids']);
    }
    return back();
}

public function update(Request $request, IamApplication $aplikasi, IamRole $role): RedirectResponse
{
    // IDOR check
    abort_unless($role->iam_application_id === $aplikasi->id, 404);
    abort_if($role->is_system, 403, 'Role sistem tidak dapat diubah');

    $data = $request->validate([
        'nama'             => 'required|string|max:100',
        'keterangan'       => 'nullable|string',
        'permission_ids'   => 'array',
        'permission_ids.*' => [
            'exists:iam_permissions,id',
            Rule::exists('iam_permissions', 'id')->where('iam_application_id', $aplikasi->id),
        ],
    ]);
    $role->update($data);
    $role->permissions()->sync($data['permission_ids'] ?? []);
    return back();
}

public function destroy(IamApplication $aplikasi, IamRole $role): RedirectResponse
{
    abort_unless($role->iam_application_id === $aplikasi->id, 404);
    abort_if($role->is_system, 403, 'Role sistem tidak dapat dihapus');
    $role->delete();
    return back();
}
```

- [ ] **Step 5: Jalankan test**

```bash
php artisan test tests/Feature/Iam/PermissionControllerTest.php tests/Feature/Iam/RoleControllerTest.php -v
```

Expected: Semua pass

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Iam/PermissionController.php app/Http/Controllers/Iam/RoleController.php tests/Feature/Iam/PermissionControllerTest.php tests/Feature/Iam/RoleControllerTest.php
git commit -m "security: fix IDOR pada Permission/Role, scope permission_ids ke aplikasi, validasi slug unique"
```

---

### Task 10: Fix IamApplication — Mass Assignment & `$hidden` & `verifySecret()`

**Files:**
- Modify: `app/Models/IamApplication.php`

- [ ] **Step 1: Update `IamApplication.php`**

```php
// Hanya field yang aman untuk mass-assign
protected $fillable = [
    'nama', 'slug', 'url', 'deskripsi', 'is_active',
    // api_key, api_secret_hash, is_system TIDAK boleh mass-assignable
];

// Field sensitif tidak boleh muncul di JSON response/serialisasi
protected $hidden = [
    'api_secret_hash',
];

/**
 * Verifikasi API secret. api_secret_hash menyimpan Crypt::encryptString($plainSecret).
 * Decrypt untuk mendapatkan plaintext, lalu bandingkan timing-safe dengan hash_equals.
 */
public function verifySecret(string $secret): bool
{
    try {
        $storedSecret = Crypt::decryptString($this->api_secret_hash);
        // hash_equals memastikan perbandingan constant-time (anti timing attack)
        return hash_equals($storedSecret, $secret);
    } catch (\Exception) {
        return false;
    }
}
```

- [ ] **Step 2: Jalankan semua test IAM**

```bash
php artisan test tests/Feature/Iam/ -v
```

Expected: Semua pass

- [ ] **Step 3: Commit**

```bash
git add app/Models/IamApplication.php
git commit -m "security: bersihkan fillable IamApplication, tambah hidden, fix verifySecret timing attack"
```

---

### Task 11: Validasi Input `nip[]` pada PegawaiApiController

**Files:**
- Modify: `app/Http/Controllers/Api/PegawaiApiController.php`
- Modify: `tests/Feature/Api/PegawaiApiTest.php`

- [ ] **Step 1: Tulis failing test**

Tambahkan di `tests/Feature/Api/PegawaiApiTest.php`:

```php
it('menolak nip dengan format tidak valid dalam batch request', function () {
    // Setup auth ... (gunakan helper yang sudah ada di file ini)
    $response = $this->getJson('/api/v1/pegawai?nip[]=BUKAN18DIGIT&nip[]=12345', /* headers */);
    $response->assertStatus(422);
});

it('menolak batch request lebih dari 50 nip', function () {
    $nips = array_fill(0, 51, str_repeat('1', 18)); // 51 NIP
    $queryString = http_build_query(['nip' => $nips]);
    $response = $this->getJson('/api/v1/pegawai?' . $queryString, /* headers */);
    $response->assertStatus(422);
});
```

- [ ] **Step 2: Update `PegawaiApiController::index()` — tambah validasi**

```php
// Ganti blok nip saat ini dengan:
if ($request->has('nip')) {
    $validated = $request->validate([
        'nip'   => 'required|array|max:50',
        'nip.*' => 'required|string|digits:18',
    ]);
    $nips = $validated['nip'];
    // ... lanjutkan logika batch lookup
}
```

- [ ] **Step 3: Jalankan test**

```bash
php artisan test tests/Feature/Api/PegawaiApiTest.php -v
```

Expected: Semua pass

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Api/PegawaiApiController.php tests/Feature/Api/PegawaiApiTest.php
git commit -m "security: validasi format dan panjang NIP pada batch endpoint"
```

---

### Task 12: Ekstrak `IamAuthorizationService` (DRY)

**Files:**
- Create: `app/Services/IamAuthorizationService.php`
- Modify: `app/Http/Controllers/Api/IamController.php`
- Modify: `app/Http/Middleware/VerifyIamPermission.php`
- Modify: `config/iam.php`

- [ ] **Step 1: Buat `app/Services/IamAuthorizationService.php`**

```php
<?php

namespace App\Services;

use App\Models\IamUserRole;

class IamAuthorizationService
{
    /**
     * Ambil semua permission slug untuk user pada aplikasi tertentu.
     * Mengganti duplikasi logika yang sama di IamController (2x) dan VerifyIamPermission.
     *
     * @param  string  $applicationId  ULID dari IamApplication
     * @return string[]
     */
    public function getUserPermissions(int $userId, string $applicationId): array
    {
        return IamUserRole::where('user_id', $userId)
            ->whereHas('role', fn ($q) => $q->where('iam_application_id', $applicationId))
            ->with('role.permissions')
            ->get()
            ->flatMap(fn ($ur) => $ur->role->permissions->pluck('slug'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Ambil semua role slug untuk user pada aplikasi tertentu.
     *
     * @param  string  $applicationId  ULID dari IamApplication
     * @return string[]
     */
    public function getUserRoles(int $userId, string $applicationId): array
    {
        return IamUserRole::where('user_id', $userId)
            ->whereHas('role', fn ($q) => $q->where('iam_application_id', $applicationId))
            ->with('role')
            ->get()
            ->map(fn ($ur) => $ur->role->slug)
            ->values()
            ->all();
    }
}
```

- [ ] **Step 2: Inject service ke `IamController.php`**

```php
use App\Services\IamAuthorizationService;

class IamController extends Controller
{
    public function __construct(private readonly IamAuthorizationService $iamAuth) {}

    public function validate(Request $request): JsonResponse
    {
        $user  = $request->user();
        $app   = $request->attributes->get('iam_app');
        $token = $user->currentAccessToken();

        return response()->json([
            'user'             => (new IamValidateResource($user))->resolve(),
            'roles'            => $this->iamAuth->getUserRoles($user->id, $app->id),
            'permissions'      => $this->iamAuth->getUserPermissions($user->id, $app->id),
            'token_expires_at' => $token?->expires_at?->timestamp,
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        $user       = $request->user();
        $app        = $request->attributes->get('iam_app');
        $permission = $request->query('permission', '');

        $allowed = in_array(
            $permission,
            $this->iamAuth->getUserPermissions($user->id, $app->id),
            true
        );

        return response()->json(['allowed' => $allowed, 'permission' => $permission]);
    }
}
```

- [ ] **Step 3: Inject service + cache ke `VerifyIamPermission.php`**

```php
use App\Models\IamApplication;
use App\Services\IamAuthorizationService;
use Illuminate\Support\Facades\Cache;

class VerifyIamPermission
{
    public function __construct(private readonly IamAuthorizationService $iamAuth) {}

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        if ($user === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthenticated'], 401)
                : redirect()->route('login');
        }

        $appSlug     = config('iam.app_slug', 'kepegawaian');
        // Cache query IAM app (hasil statis, TTL 1 jam)
        $kepegawaian = Cache::remember("iam_app:{$appSlug}", 3600,
            fn () => IamApplication::where('slug', $appSlug)->first()
        );

        if (! $kepegawaian) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $userPermissions = $this->iamAuth->getUserPermissions($user->id, $kepegawaian->id);

        if (empty($permissions)) {
            abort_if(empty($userPermissions), Response::HTTP_FORBIDDEN);
            return $next($request);
        }

        foreach ($permissions as $permission) {
            abort_unless(in_array($permission, $userPermissions, true), Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Tambah `app_slug` ke `config/iam.php`**

```php
return [
    'token_ttl_hours'     => env('IAM_TOKEN_TTL_HOURS', 8),
    'sso_code_ttl_seconds' => env('IAM_SSO_CODE_TTL', 60),
    'app_slug'            => env('IAM_APP_SLUG', 'kepegawaian'),
];
```

- [ ] **Step 5: Jalankan semua test**

```bash
php artisan test -v
```

Expected: Semua pass

- [ ] **Step 6: Commit**

```bash
git add app/Services/IamAuthorizationService.php app/Http/Controllers/Api/IamController.php app/Http/Middleware/VerifyIamPermission.php config/iam.php
git commit -m "refactor: ekstrak IamAuthorizationService, cache IAM app query, pindah slug ke config"
```

---

### Task 13: Tambah Missing Tests — `check()`, `callback()`, `IamUserRole::user()`

**Files:**
- Create: `tests/Feature/Iam/IamCheckTest.php`
- Create: `tests/Feature/Iam/SsoCallbackTest.php`
- Modify: `app/Models/IamUserRole.php`

- [ ] **Step 1: Tambah relasi `user()` ke `IamUserRole.php`**

```php
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}
```

- [ ] **Step 2: Buat `tests/Feature/Iam/IamCheckTest.php`**

```php
<?php

use App\Models\IamUserRole;
use App\Models\User;

it('mengembalikan allowed:true untuk user dengan permission yang diminta', function () {
    $user = User::factory()->create();
    // Token dengan scope terbatas (sesuai implementasi production di Task 4)
    [$app, $headers] = makeIamHeaders('GET', '/api/v1/iam/check', [], ['permission' => 'manage-pegawai']);

    $permission = $app->permissions()->create(['nama' => 'Manage Pegawai', 'slug' => 'manage-pegawai']);
    $role = $app->roles()->create(['nama' => 'Admin', 'slug' => 'admin-check-test']);
    $role->permissions()->attach($permission);
    IamUserRole::create(['user_id' => $user->id, 'iam_role_id' => $role->id, 'assigned_at' => now()]);

    // Buat token dengan scope app yang sesuai
    $token = $user->createToken("sso:{$app->slug}", ["app:{$app->slug}"])->plainTextToken;

    $response = $this->getJson(
        '/api/v1/iam/check?permission=manage-pegawai',
        array_merge($headers, ['Authorization' => "Bearer {$token}"])
    );

    $response->assertStatus(200)->assertJson(['allowed' => true, 'permission' => 'manage-pegawai']);
});

it('mengembalikan allowed:false untuk user tanpa permission', function () {
    $user  = User::factory()->create();
    [$app, $headers] = makeIamHeaders('GET', '/api/v1/iam/check', [], ['permission' => 'admin-only']);
    $token = $user->createToken("sso:{$app->slug}", ["app:{$app->slug}"])->plainTextToken;

    $response = $this->getJson(
        '/api/v1/iam/check?permission=admin-only',
        array_merge($headers, ['Authorization' => "Bearer {$token}"])
    );

    $response->assertStatus(200)->assertJson(['allowed' => false]);
});

it('menolak request tanpa token auth (401)', function () {
    [$app, $headers] = makeIamHeaders('GET', '/api/v1/iam/check', [], ['permission' => 'any']);

    $response = $this->getJson('/api/v1/iam/check?permission=any', $headers);
    $response->assertStatus(401);
});
```

- [ ] **Step 3: Buat `tests/Feature/Iam/SsoCallbackTest.php`**

```php
<?php

use App\Models\IamApplication;
use App\Models\User;

it('callback redirect ke dashboard jika tidak ada SSO session', function () {
    $user     = User::factory()->create();
    $response = $this->actingAs($user)->get('/sso/callback');
    $response->assertRedirect(route('dashboard'));
});

it('callback generate SSO code dan redirect ke URL yang valid', function () {
    $app  = IamApplication::factory()->create(['is_active' => true, 'url' => 'http://att.local']);
    $user = User::factory()->create();

    session(['sso_app' => $app->slug, 'sso_redirect' => 'http://att.local/callback']);

    $response = $this->actingAs($user)->get('/sso/callback');

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    $this->assertStringStartsWith('http://att.local/callback?code=', $location);
    // Pastikan SSO code disimpan di database
    $this->assertDatabaseCount('iam_sso_codes', 1);
});

it('callback redirect ke dashboard jika aplikasi tidak aktif', function () {
    $app  = IamApplication::factory()->create(['is_active' => false, 'url' => 'http://att.local']);
    $user = User::factory()->create();

    session(['sso_app' => $app->slug, 'sso_redirect' => 'http://att.local/callback']);

    $response = $this->actingAs($user)->get('/sso/callback');
    $response->assertRedirect(route('dashboard'));
});

it('callback membersihkan session setelah digunakan', function () {
    $app  = IamApplication::factory()->create(['is_active' => true, 'url' => 'http://att.local']);
    $user = User::factory()->create();

    session(['sso_app' => $app->slug, 'sso_redirect' => 'http://att.local/callback']);
    $this->actingAs($user)->get('/sso/callback');

    // Session harus sudah di-pull (kosong setelah callback)
    $this->assertNull(session('sso_app'));
    $this->assertNull(session('sso_redirect'));
});
```

- [ ] **Step 4: Jalankan test baru**

```bash
php artisan test tests/Feature/Iam/IamCheckTest.php tests/Feature/Iam/SsoCallbackTest.php -v
```

Expected: Semua pass

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Iam/IamCheckTest.php tests/Feature/Iam/SsoCallbackTest.php app/Models/IamUserRole.php
git commit -m "test: tambah coverage untuk check(), callback(), tambah relasi user() di IamUserRole"
```

---

## FASE 3 — MEDIUM Fixes

---

### Task 14: Aktifkan Authorization di Web Routes + Tambah Permission `iam-manage`

**Files:**
- Modify: `routes/web.php`
- Modify: `database/seeders/IamSeeder.php`

- [ ] **Step 1: Update `routes/web.php`**

```php
// Ganti:
Route::middleware(['auth', 'verified'/* , 'role:admin,operator' */])->group(function () {

// Dengan:
Route::middleware(['auth', 'verified', 'iam.permission'])->group(function () {
```

```php
// Ganti IAM routes:
Route::middleware(['auth', 'verified', 'iam.permission'])
    ->prefix('iam')

// Dengan (hanya admin IAM yang bisa akses):
Route::middleware(['auth', 'verified', 'iam.permission:iam-manage'])
    ->prefix('iam')
```

- [ ] **Step 2: Tambah permission `iam-manage` di IamSeeder**

Di `database/seeders/IamSeeder.php`, di blok pembuatan permissions untuk role admin:

```php
// Tambahkan permission iam-manage
$iamManage = $kepegawaian->permissions()->firstOrCreate(
    ['slug' => 'iam-manage'],
    ['nama' => 'Kelola IAM', 'group' => 'iam', 'keterangan' => 'Akses penuh ke manajemen IAM']
);
// Assign ke role admin
$adminRole->permissions()->syncWithoutDetaching([$iamManage->id]);
```

- [ ] **Step 3: Jalankan seeder dan semua test**

```bash
php artisan migrate:fresh --seed
php artisan test -v
```

Expected: Semua pass

- [ ] **Step 4: Commit**

```bash
git add routes/web.php database/seeders/IamSeeder.php
git commit -m "security: aktifkan iam.permission di web routes, IAM admin butuh permission iam-manage"
```

---

### Task 15: Frontend — Error Handling, Controlled Dialogs, Hapus `confirm()`

**Files:**
- Modify: `resources/js/pages/iam/aplikasi/index.tsx`
- Modify: `resources/js/pages/iam/aplikasi/show.tsx`
- Modify: `resources/js/pages/iam/users/akses.tsx`
- Modify: `resources/js/pages/iam/users/index.tsx`

- [ ] **Step 1: Update `index.tsx` — gunakan `useForm` + controlled dialog + AlertDialog**

```typescript
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';

// Ganti form manual dengan useForm:
const { data, setData, post, processing, errors, reset } = useForm({
    nama: '', slug: '', url: '', deskripsi: '',
});
const [openCreate, setOpenCreate] = useState(false);

const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/iam/aplikasi', {
        onSuccess: () => { setOpenCreate(false); reset(); },
    });
};

// Ganti confirm() dengan AlertDialog untuk delete
```

- [ ] **Step 2: Update `show.tsx` — controlled dialogs + error handling**

```typescript
const [openAddRole, setOpenAddRole] = useState(false);
const { data: roleData, setData: setRoleData, post: postRole, processing: processingRole, errors: roleErrors, reset: resetRole } = useForm({
    nama: '', slug: '', keterangan: '', permission_ids: [] as string[],
});

const handleAddRole = (e: React.FormEvent) => {
    e.preventDefault();
    postRole(`/iam/aplikasi/${aplikasi.id}/roles`, {
        onSuccess: () => { setOpenAddRole(false); resetRole(); },
    });
};
```

- [ ] **Step 3: Update `akses.tsx` — fix race condition + error handling**

```typescript
const [processing, setProcessing] = useState(false);

const handleAddRole = useCallback(() => {
    if (!selectedAppId || !selectedRoleId || processing) return;

    setProcessing(true);
    router.post(`/iam/users/${user.id}/akses`, { iam_role_id: selectedRoleId }, {
        onSuccess: () => { setSelectedAppId(''); setSelectedRoleId(''); },
        onFinish:  () => setProcessing(false),
    });
}, [user.id, selectedAppId, selectedRoleId, processing]);
```

- [ ] **Step 4: Fix label pagination di `users/index.tsx`**

```typescript
// Ganti:
<Link href={users.links.prev ?? '#'}>Previous</Link>
<Link href={users.links.next ?? '#'}>Next</Link>

// Dengan:
<Link href={users.links.prev ?? '#'}>Sebelumnya</Link>
<Link href={users.links.next ?? '#'}>Selanjutnya</Link>
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/iam/
git commit -m "fix: useForm Inertia, controlled dialogs, ganti confirm() dengan AlertDialog, fix race condition, label pagination"
```

---

### Task 16: Fix Type Safety Frontend

**Files:**
- Modify: `resources/js/types/iam.ts`
- Modify: `resources/js/pages/iam/users/akses.tsx`

- [ ] **Step 1: Konsolidasi `PaginatedData` di `iam.ts`**

Pastikan hanya ada satu definisi di seluruh codebase. Tambahkan ke `resources/js/types/iam.ts`:

```typescript
export interface PaginatedData<T> {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
    };
}
```

- [ ] **Step 2: Fix unsafe type assertion di `akses.tsx`**

```typescript
// Ganti:
app: a.role.application as unknown as IamAvailableApp,

// Dengan mapping eksplisit:
app: {
    id:    a.role.application?.id ?? '',
    nama:  a.role.application?.nama ?? '',
    slug:  a.role.application?.slug ?? '',
    roles: [],
} satisfies IamAvailableApp,
```

- [ ] **Step 3: Jalankan TypeScript check**

```bash
npx tsc --noEmit
```

Expected: Tidak ada error

- [ ] **Step 4: Commit**

```bash
git add resources/js/types/ resources/js/pages/iam/users/akses.tsx
git commit -m "fix: konsolidasi PaginatedData, hapus unsafe type assertion (as unknown as)"
```

---

### Task 17: Seeder Fixes — Chunking + Urutan Role Default

**Files:**
- Modify: `database/seeders/IamSeeder.php`

- [ ] **Step 1: Perbaiki urutan dan chunking di `IamSeeder.php`**

Pastikan role default (`admin`, `operator`, `viewer`) dibuat di **langkah awal** sebelum migrasi users.
Ganti `User::all()` dengan `User::chunk()`:

```php
// Langkah 1 (AWAL): buat role default dulu
$adminRole   = $kepegawaian->roles()->firstOrCreate(['slug' => 'admin'], [...]);
$operatorRole = $kepegawaian->roles()->firstOrCreate(['slug' => 'operator'], [...]);
$viewerRole  = $kepegawaian->roles()->firstOrCreate(['slug' => 'viewer'], [...]);

// Langkah 2: baru migrasi user dengan role default tersedia
User::chunk(100, function ($users) use ($viewerRole) {
    foreach ($users as $user) {
        // ... logika migrasi role ke IAM
    }
});
```

- [ ] **Step 2: Jalankan seeder**

```bash
php artisan migrate:fresh --seed
```

Expected: Tidak ada error, semua user memiliki role di IAM

- [ ] **Step 3: Commit**

```bash
git add database/seeders/IamSeeder.php
git commit -m "fix: perbaiki urutan seeder (role default sebelum migrasi user), chunking 100"
```

---

## FASE 4 — LOW Fixes

---

### Task 18: Tambah Index `app_slug` + Prunable untuk SSO Codes

**Files:**
- Create: `database/migrations/2026_03_21_000005_add_index_to_iam_sso_codes.php`
- Modify: `app/Models/IamSsoCode.php`

- [ ] **Step 1: Buat migration baru**

```bash
php artisan make:migration add_index_to_iam_sso_codes
```

```php
public function up(): void
{
    Schema::table('iam_sso_codes', function (Blueprint $table) {
        $table->index('app_slug');
    });
}

public function down(): void
{
    Schema::table('iam_sso_codes', function (Blueprint $table) {
        $table->dropIndex(['app_slug']);
    });
}
```

- [ ] **Step 2: Tambah `Prunable` ke `IamSsoCode`**

```php
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Builder;

class IamSsoCode extends Model
{
    use Prunable;

    // Prune records yang expired lebih dari 24 jam yang lalu
    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now()->subDay());
    }
}
```

- [ ] **Step 3: Jadwalkan pruning**

Di `bootstrap/app.php` atau `routes/console.php`:

```php
Schedule::command('model:prune', ['--model' => \App\Models\IamSsoCode::class])->hourly();
```

- [ ] **Step 4: Jalankan migration**

```bash
php artisan migrate
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/ app/Models/IamSsoCode.php bootstrap/app.php
git commit -m "fix: tambah index app_slug iam_sso_codes, Prunable untuk cleanup SSO codes expired"
```

---

### Task 19: Misc Low Fixes — Accessibility, serializeDate, `api_secret_hash` naming

**Files:**
- Modify: Resources frontend untuk `aria-label`
- Modify: `app/Models/Model.php` (jika ada base Model)

- [ ] **Step 1: Tambahkan `aria-label` pada tombol icon di semua IAM pages**

```typescript
// Ganti setiap tombol icon tanpa teks:
<Button variant="ghost" size="icon" asChild>
    <Link href={`/iam/aplikasi/${app.id}`}>
        <Eye className="h-4 w-4" />
    </Link>
</Button>

// Dengan:
<Button variant="ghost" size="icon" asChild aria-label={`Lihat detail ${app.nama}`}>
    <Link href={`/iam/aplikasi/${app.id}`}>
        <Eye className="h-4 w-4" aria-hidden="true" />
    </Link>
</Button>
```

- [ ] **Step 2: Fix `serializeDate` di base Model jika ada**

Jika ada file `app/Models/Model.php` yang mengoverride `serializeDate` ke format `Y-m-d`, ubah format ke `Y-m-d H:i:s` untuk menyertakan waktu (kritis untuk `IamSsoCode.expires_at`).

- [ ] **Step 3: Hapus unused import di `akses.tsx`**

```typescript
// Hapus baris ini karena tidak digunakan:
import { Separator } from '@/components/ui/separator';
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/iam/ app/Models/
git commit -m "fix: tambah aria-label tombol icon, fix serializeDate, hapus unused import"
```

---

## Ringkasan Task

| Fase | Task | Severity Diatasi |
|------|------|-----------------|
| 1 — CRITICAL | Task 1–8 | 8x CRITICAL (termasuk .env, HMAC, race condition, open redirect, rate limit, seeder, API key) |
| 2 — HIGH | Task 9–13 | 14x HIGH (IDOR, mass assignment, nip validation, DRY service, missing tests) |
| 3 — MEDIUM | Task 14–17 | 18x MEDIUM (routes auth, frontend fixes, type safety, seeder) |
| 4 — LOW | Task 18–19 | Sisa LOW (index, prunable, accessibility) |

**Total:** 19 tasks atomic, masing-masing dengan TDD steps lengkap.

---

## Checklist Verifikasi Final

Sebelum membuat PR, pastikan:

- [ ] `php artisan test --coverage` minimal 80% untuk area IAM
- [ ] `npx tsc --noEmit` tidak ada error
- [ ] `php artisan migrate:fresh --seed` berjalan tanpa error
- [ ] Login dengan `admin@pa-penajam.go.id` dan password dari env berhasil
- [ ] Semua secrets sudah di-rotate (APP_KEY, DB password, HMAC secret, IAM api keys)

---

> **Setelah selesai:** Jalankan `superpowers:finishing-a-development-branch` untuk merge/PR.
