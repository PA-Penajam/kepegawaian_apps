# IAM Hub Secret Display & Recovery — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memperbaiki bug critical `api_secret_once` tidak ter-expose ke Inertia (modal tidak pernah tampil) + tambah recovery cache 15 menit, audit trail lengkap, dan rate limit regenerate-key.

**Architecture:** Service-class baru `IamSecretService` jadi single source of truth lifecycle secret. Plaintext secret di-cache 15 menit (key `iam:secret:recovery:{app_id}`) untuk recovery. Endpoint baru `POST /recover-secret` & `POST /acknowledge-secret`. Audit via Spatie Activitylog v5 dengan `log_name='iam_audit'`. Rate limit named limiter `iam-regenerate` 5x/jam per user. Backward compatible 100%: HMAC verification tetap pakai `Crypt::decryptString` dari DB.

**Tech Stack:** Laravel 12, Inertia.js, Spatie Activitylog v5, Pest 4.4, React 19/TypeScript, ULID app IDs, cache driver `database`.

**Spec Reference:** `docs/superpowers/specs/2026-05-17-iam-hub-secret-display-design.md`

---

## File Structure

| File | Action | Responsibility |
|---|---|---|
| `app/Services/Iam/IamSecretService.php` | **NEW** | Lifecycle plaintext secret (generate/regenerate/recover/invalidate) + audit logging |
| `app/Http/Controllers/Iam/AplikasiController.php` | Modify | Tipiskan `store()` & `regenerateKey()` — delegate ke service. Tambah props recovery ke `show()` & `index()` |
| `app/Http/Controllers/Iam/IamSecretRecoveryController.php` | **NEW** | Endpoint `show()` recovery, `acknowledge()` invalidate cache |
| `app/Http/Middleware/HandleInertiaRequests.php` | Modify | Tambah `api_secret_once` ke flash share — fix utama |
| `app/Http/Middleware/VerifyIamSignature.php` | Modify | Tambah audit log `hmac.verification_failed` |
| `app/Providers/AppServiceProvider.php` | Modify | Register named rate limiter `iam-regenerate` |
| `routes/web.php` | Modify | Tambah 2 route + throttle middleware di regenerate-key |
| `resources/js/components/iam/ApiSecretModal.tsx` | Modify | Tombol "Saya sudah simpan" + countdown timer |
| `resources/js/pages/iam/aplikasi/show.tsx` | Modify | Block recovery UI + handler ack/recover |
| `resources/js/pages/iam/aplikasi/index.tsx` | Modify | Badge `🟡 Recoverable` di kolom Status |
| `resources/js/types/iam.ts` | Modify | Tambah type `recovery_status`, field `secret_recoverable` |
| `tests/Unit/Services/Iam/IamSecretServiceTest.php` | **NEW** | Unit test service (10 case) |
| `tests/Feature/Iam/IamSecretRecoveryTest.php` | **NEW** | Integration test endpoint recovery + rate limit + audit |
| `tests/Feature/Iam/HandleInertiaRequestsFlashTest.php` | **NEW** | Regression test flash share |
| `docs/iam/secret-management.md` | **NEW** | Dokumentasi lifecycle, recovery flow, audit query examples |

---

## PHASE 1 — Foundation: IamSecretService

### Task 1: Setup test scaffold untuk IamSecretService

**Files:**
- Create: `tests/Unit/Services/Iam/IamSecretServiceTest.php`

- [ ] **Step 1: Buat folder unit test Service Iam (jika belum ada)**

```bash
mkdir -p tests/Unit/Services/Iam
```

- [ ] **Step 2: Tulis test file dengan beforeEach + test pertama (RED)**

Buat `tests/Unit/Services/Iam/IamSecretServiceTest.php`:

```php
<?php

use App\Models\IamApplication;
use App\Services\Iam\IamSecretService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

beforeEach(function () {
    $this->service = app(IamSecretService::class);

    // Buat aplikasi test (api_key & api_secret_hash tidak fillable)
    $this->app = new IamApplication([
        'nama' => 'Test App',
        'slug' => 'test-secret-svc',
        'url' => 'http://test.local',
        'is_active' => true,
    ]);
    $this->app->api_key = 'iam_initial_key_for_test';
    $this->app->api_secret_hash = Crypt::encryptString('initial-secret');
    $this->app->is_system = false;
    $this->app->save();
});

test('generateAndStore creates new credentials, persists hash, caches plaintext', function () {
    $plaintext = $this->service->generateAndStore($this->app);

    $this->app->refresh();

    // Plaintext returned harus 64 char
    expect($plaintext)->toBeString()->toHaveLength(64);

    // api_key di DB harus berubah (bukan initial key)
    expect($this->app->api_key)->not->toBe('iam_initial_key_for_test');
    expect($this->app->api_key)->toStartWith('iam_');

    // Plaintext bisa di-decrypt dari hash dan sama dengan plaintext
    $decrypted = Crypt::decryptString($this->app->api_secret_hash);
    expect($decrypted)->toBe($plaintext);

    // Cache berisi plaintext yang sama
    $cached = Cache::get("iam:secret:recovery:{$this->app->id}");
    expect($cached)->toBe($plaintext);
});
```

- [ ] **Step 3: Run test untuk verifikasi RED**

```bash
./vendor/bin/pest tests/Unit/Services/Iam/IamSecretServiceTest.php
```

Expected: FAIL dengan error class `App\Services\Iam\IamSecretService` tidak ditemukan.

- [ ] **Step 4: Commit test failing**

```bash
git add tests/Unit/Services/Iam/IamSecretServiceTest.php
git commit -m "test(iam): tambah test scaffold IamSecretService (RED)"
```

---

### Task 2: Implementasi IamSecretService minimal (GREEN)

**Files:**
- Create: `app/Services/Iam/IamSecretService.php`

- [ ] **Step 1: Buat file service dengan semua method (signature only + implementasi `generateAndStore`)**

Buat `app/Services/Iam/IamSecretService.php`:

```php
<?php

namespace App\Services\Iam;

use App\Models\IamApplication;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class IamSecretService
{
    private const CACHE_KEY_PREFIX = 'iam:secret:recovery:';
    private const CACHE_TTL_MINUTES = 15;
    private const ACTIVITY_LOG_NAME = 'iam_audit';

    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    /**
     * Generate kredensial baru untuk aplikasi yang baru dibuat.
     * Mengembalikan plaintext secret untuk ditampilkan ke user satu kali.
     */
    public function generateAndStore(IamApplication $app, ?Request $request = null): string
    {
        ['key' => $key, 'secret' => $secret, 'hash' => $hash] = IamApplication::generateApiCredentials();

        $app->api_key = $key;
        $app->api_secret_hash = $hash;
        $app->save();

        $this->putRecoveryCache($app, $secret);

        $this->logAudit('secret.created', $app, $request, [
            'app_slug' => $app->slug,
        ]);

        return $secret;
    }

    public function regenerate(IamApplication $app, ?Request $request = null): string
    {
        $previousKey = $app->api_key ?? '';
        $previousKeyPrefix = substr($previousKey, 0, 8);

        ['key' => $key, 'secret' => $secret, 'hash' => $hash] = IamApplication::generateApiCredentials();

        $app->api_key = $key;
        $app->api_secret_hash = $hash;
        $app->save();

        $this->putRecoveryCache($app, $secret);

        $this->logAudit('secret.regenerated', $app, $request, [
            'app_slug' => $app->slug,
            'previous_key_prefix' => $previousKeyPrefix,
        ]);

        return $secret;
    }

    public function recoverFromCache(IamApplication $app, ?Request $request = null): ?string
    {
        $plaintext = $this->cache->get($this->cacheKey($app));

        if ($plaintext === null) {
            return null;
        }

        $this->logAudit('secret.recovery_viewed', $app, $request, [
            'app_slug' => $app->slug,
            'ttl_remaining_seconds' => $this->getRecoveryTtlSeconds($app),
        ]);

        return $plaintext;
    }

    public function invalidateRecovery(IamApplication $app, ?Request $request = null): void
    {
        $this->cache->forget($this->cacheKey($app));

        $this->logAudit('secret.recovery_acknowledged', $app, $request, [
            'app_slug' => $app->slug,
        ]);
    }

    public function hasRecoverableSecret(IamApplication $app): bool
    {
        return $this->cache->has($this->cacheKey($app));
    }

    public function getRecoveryTtlSeconds(IamApplication $app): int
    {
        // Cache driver database menyimpan expires_at (unix timestamp)
        $expiresAt = \DB::table('cache')
            ->where('key', config('cache.prefix').$this->cacheKey($app))
            ->value('expiration');

        if ($expiresAt === null) {
            return 0;
        }

        $remaining = (int) $expiresAt - time();

        return max(0, $remaining);
    }

    private function cacheKey(IamApplication $app): string
    {
        return self::CACHE_KEY_PREFIX.$app->id;
    }

    private function putRecoveryCache(IamApplication $app, string $secret): void
    {
        try {
            $this->cache->put(
                $this->cacheKey($app),
                $secret,
                now()->addMinutes(self::CACHE_TTL_MINUTES),
            );
        } catch (\Throwable $e) {
            // Cache failure tidak boleh block flow utama (flash tetap jalan)
            logger()->warning('IAM secret recovery cache write failed', [
                'app_id' => $app->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function logAudit(string $event, IamApplication $app, ?Request $request, array $extraProps = []): void
    {
        $baseProps = [
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ];

        activity(self::ACTIVITY_LOG_NAME)
            ->performedOn($app)
            ->causedBy($request?->user())
            ->event($event)
            ->withProperties(array_merge($baseProps, $extraProps))
            ->log($event);
    }
}
```

- [ ] **Step 2: Run test pertama untuk verifikasi GREEN**

```bash
./vendor/bin/pest tests/Unit/Services/Iam/IamSecretServiceTest.php
```

Expected: PASS — 1 test passed.

- [ ] **Step 3: Commit implementasi minimal**

```bash
git add app/Services/Iam/IamSecretService.php
git commit -m "feat(iam): tambah IamSecretService dengan generateAndStore"
```

---

### Task 3: Test regenerate + previous_key_prefix logging

**Files:**
- Modify: `tests/Unit/Services/Iam/IamSecretServiceTest.php`

- [ ] **Step 1: Tambah test untuk regenerate (RED)**

Tambahkan di akhir file `tests/Unit/Services/Iam/IamSecretServiceTest.php`:

```php
test('regenerate overwrites existing credentials and old cache key', function () {
    // Seed cache lama dengan secret berbeda
    Cache::put("iam:secret:recovery:{$this->app->id}", 'OLD_PLAINTEXT', now()->addMinutes(15));

    $oldKey = $this->app->api_key;
    $newPlaintext = $this->service->regenerate($this->app);

    $this->app->refresh();

    // Key berubah
    expect($this->app->api_key)->not->toBe($oldKey);

    // Cache ter-overwrite dengan plaintext baru
    $cached = Cache::get("iam:secret:recovery:{$this->app->id}");
    expect($cached)->toBe($newPlaintext);
    expect($cached)->not->toBe('OLD_PLAINTEXT');
});

test('regenerate logs activity with previous_key_prefix', function () {
    $oldKey = $this->app->api_key;
    $expectedPrefix = substr($oldKey, 0, 8);

    $this->service->regenerate($this->app);

    $activity = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'iam_audit')
        ->where('event', 'secret.regenerated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['previous_key_prefix'])->toBe($expectedPrefix);
    expect($activity->properties['app_slug'])->toBe($this->app->slug);
});
```

- [ ] **Step 2: Run tests untuk verifikasi PASS**

```bash
./vendor/bin/pest tests/Unit/Services/Iam/IamSecretServiceTest.php
```

Expected: PASS — 3 tests passed (1 lama + 2 baru).

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/Services/Iam/IamSecretServiceTest.php
git commit -m "test(iam): tambah test regenerate + previous_key_prefix logging"
```

---

### Task 4: Test recoverFromCache (hit, miss, log event)

**Files:**
- Modify: `tests/Unit/Services/Iam/IamSecretServiceTest.php`

- [ ] **Step 1: Tambah 3 test untuk recoverFromCache**

Tambahkan di akhir file `tests/Unit/Services/Iam/IamSecretServiceTest.php`:

```php
test('recoverFromCache returns plaintext when cache hit and logs viewed event', function () {
    Cache::put("iam:secret:recovery:{$this->app->id}", 'CACHED_SECRET_PLAINTEXT', now()->addMinutes(15));

    $result = $this->service->recoverFromCache($this->app);

    expect($result)->toBe('CACHED_SECRET_PLAINTEXT');

    $activity = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'iam_audit')
        ->where('event', 'secret.recovery_viewed')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['app_slug'])->toBe($this->app->slug);
});

test('recoverFromCache returns null when cache miss and does not log', function () {
    Cache::forget("iam:secret:recovery:{$this->app->id}");

    $result = $this->service->recoverFromCache($this->app);

    expect($result)->toBeNull();

    $activityCount = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'iam_audit')
        ->where('event', 'secret.recovery_viewed')
        ->count();

    expect($activityCount)->toBe(0);
});

test('recoverFromCache idempotent: cache tidak hilang setelah view (masih bisa di-view lagi)', function () {
    Cache::put("iam:secret:recovery:{$this->app->id}", 'STAYS_VISIBLE', now()->addMinutes(15));

    $first = $this->service->recoverFromCache($this->app);
    $second = $this->service->recoverFromCache($this->app);

    expect($first)->toBe('STAYS_VISIBLE');
    expect($second)->toBe('STAYS_VISIBLE');
});
```

- [ ] **Step 2: Run tests**

```bash
./vendor/bin/pest tests/Unit/Services/Iam/IamSecretServiceTest.php
```

Expected: PASS — 6 tests passed.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/Services/Iam/IamSecretServiceTest.php
git commit -m "test(iam): tambah test recoverFromCache (hit/miss/idempotent)"
```

---

### Task 5: Test invalidateRecovery + hasRecoverableSecret + getRecoveryTtlSeconds

**Files:**
- Modify: `tests/Unit/Services/Iam/IamSecretServiceTest.php`

- [ ] **Step 1: Tambah test untuk 3 method sisanya**

Tambahkan di akhir file `tests/Unit/Services/Iam/IamSecretServiceTest.php`:

```php
test('invalidateRecovery removes cache and logs acknowledged event', function () {
    Cache::put("iam:secret:recovery:{$this->app->id}", 'TO_BE_REMOVED', now()->addMinutes(15));

    $this->service->invalidateRecovery($this->app);

    expect(Cache::has("iam:secret:recovery:{$this->app->id}"))->toBeFalse();

    $activity = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'iam_audit')
        ->where('event', 'secret.recovery_acknowledged')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
});

test('invalidateRecovery is idempotent when cache already empty', function () {
    Cache::forget("iam:secret:recovery:{$this->app->id}");

    // Tidak boleh throw
    $this->service->invalidateRecovery($this->app);

    expect(Cache::has("iam:secret:recovery:{$this->app->id}"))->toBeFalse();
});

test('hasRecoverableSecret returns true when cache exists, false when empty', function () {
    Cache::forget("iam:secret:recovery:{$this->app->id}");
    expect($this->service->hasRecoverableSecret($this->app))->toBeFalse();

    Cache::put("iam:secret:recovery:{$this->app->id}", 'X', now()->addMinutes(15));
    expect($this->service->hasRecoverableSecret($this->app))->toBeTrue();
});

test('getRecoveryTtlSeconds returns positive int when cache exists, 0 when miss', function () {
    Cache::forget("iam:secret:recovery:{$this->app->id}");
    expect($this->service->getRecoveryTtlSeconds($this->app))->toBe(0);

    Cache::put("iam:secret:recovery:{$this->app->id}", 'X', now()->addMinutes(15));
    $ttl = $this->service->getRecoveryTtlSeconds($this->app);

    expect($ttl)->toBeGreaterThan(0);
    expect($ttl)->toBeLessThanOrEqual(900); // 15 min = 900 detik
});
```

- [ ] **Step 2: Run tests**

```bash
./vendor/bin/pest tests/Unit/Services/Iam/IamSecretServiceTest.php
```

Expected: PASS — 10 tests passed.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/Services/Iam/IamSecretServiceTest.php
git commit -m "test(iam): tambah test invalidate/has/ttl untuk IamSecretService"
```

✓ **Checkpoint 1**: Service standalone teruji penuh (10 unit test pass). Belum ada controller/route change.

---

## PHASE 2 — Wiring Inertia Flash & AplikasiController

### Task 6: Test flash `api_secret_once` ter-share via Inertia

**Files:**
- Create: `tests/Feature/Iam/HandleInertiaRequestsFlashTest.php`

- [ ] **Step 1: Tulis test untuk flash share (RED)**

Buat `tests/Feature/Iam/HandleInertiaRequestsFlashTest.php`:

```php
<?php

use App\Models\Pegawai;

test('flash.api_secret_once dishare ke Inertia props', function () {
    $admin = Pegawai::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->withSession(['api_secret_once' => 'PLAIN_SECRET_TEST_64_CHARS_DEMO'])
        ->get('/iam/aplikasi');

    $response->assertOk();

    // Inertia response punya page object di header X-Inertia atau body
    $page = $response->viewData('page');
    expect($page['props']['flash']['api_secret_once'])->toBe('PLAIN_SECRET_TEST_64_CHARS_DEMO');
});

test('flash.success dan flash.error tetap di-share (regression)', function () {
    $admin = Pegawai::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->withSession([
            'success' => 'Berhasil disimpan',
            'error' => 'Ada error',
        ])
        ->get('/iam/aplikasi');

    $response->assertOk();

    $page = $response->viewData('page');
    expect($page['props']['flash']['success'])->toBe('Berhasil disimpan');
    expect($page['props']['flash']['error'])->toBe('Ada error');
});
```

- [ ] **Step 2: Run test untuk verifikasi RED**

```bash
./vendor/bin/pest tests/Feature/Iam/HandleInertiaRequestsFlashTest.php
```

Expected: FAIL — test pertama gagal karena `api_secret_once` belum ada di flash array.

- [ ] **Step 3: Commit test failing**

```bash
git add tests/Feature/Iam/HandleInertiaRequestsFlashTest.php
git commit -m "test(iam): tambah test flash api_secret_once share (RED)"
```

---

### Task 7: Tambah `api_secret_once` di flash share (GREEN — MINIMAL FIX)

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php:55-58`

- [ ] **Step 1: Edit `share()` untuk tambah `api_secret_once`**

Edit `app/Http/Middleware/HandleInertiaRequests.php` ganti block flash (line 55-58):

```php
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'api_secret_once' => $request->session()->get('api_secret_once'),
            ],
```

- [ ] **Step 2: Run test untuk verifikasi GREEN**

```bash
./vendor/bin/pest tests/Feature/Iam/HandleInertiaRequestsFlashTest.php
```

Expected: PASS — 2 tests passed.

- [ ] **Step 3: Run existing tests untuk pastikan tidak regression**

```bash
./vendor/bin/pest tests/Feature/Iam/AplikasiControllerTest.php
```

Expected: PASS — semua test existing tetap lulus.

- [ ] **Step 4: Commit fix utama**

```bash
git add app/Http/Middleware/HandleInertiaRequests.php
git commit -m "fix(iam): expose api_secret_once via Inertia flash share

Bug critical: modal ApiSecretModal tidak pernah tampil karena
flash.api_secret_once hilang di middleware share. Fix ini
adalah minimum coherent fix — bisa ship standalone."
```

> 🚀 **Minimum coherent fix sampai sini.** Kalau urgent, deploy step 7 sudah cukup untuk fix modal tidak tampil. Tambahan dari Task 8 dst adalah enhancement (recovery, audit, rate limit).

---

### Task 8: Test AplikasiController.store delegasi ke IamSecretService

**Files:**
- Create: `tests/Feature/Iam/IamSecretRecoveryTest.php` (akan dipakai untuk semua feature test Phase 2-4)

- [ ] **Step 1: Tulis test file scaffold dengan test store**

Buat `tests/Feature/Iam/IamSecretRecoveryTest.php`:

```php
<?php

use App\Models\IamApplication;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->admin = Pegawai::factory()->admin()->create();
});

test('POST /iam/aplikasi creates app, flashes api_secret_once, caches plaintext, logs audit', function () {
    $this->actingAs($this->admin)
        ->post('/iam/aplikasi', [
            'nama' => 'New Test App',
            'slug' => 'new-test-app',
            'url' => 'http://newapp.local',
            'deskripsi' => 'Test creation',
        ])
        ->assertRedirect()
        ->assertSessionHas('api_secret_once');

    $app = IamApplication::where('slug', 'new-test-app')->firstOrFail();

    // Cache plaintext exists
    $cached = Cache::get("iam:secret:recovery:{$app->id}");
    expect($cached)->toBeString()->toHaveLength(64);

    // Cache plaintext == decrypted hash
    $decrypted = Crypt::decryptString($app->api_secret_hash);
    expect($cached)->toBe($decrypted);

    // Audit log 'secret.created' ada
    $activity = Activity::query()
        ->where('log_name', 'iam_audit')
        ->where('event', 'secret.created')
        ->where('subject_id', $app->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['app_slug'])->toBe('new-test-app');
    expect($activity->properties['ip'])->not->toBeNull();
});
```

- [ ] **Step 2: Run test (akan PASS karena store sudah jalan — tapi tidak pakai service)**

```bash
./vendor/bin/pest tests/Feature/Iam/IamSecretRecoveryTest.php
```

Expected: FAIL — `api_secret_once` di session ada (sudah jalan), tapi:
1. Cache tidak ada (belum pakai service).
2. Activity log tidak ada (belum pakai service).

- [ ] **Step 3: Commit test failing**

```bash
git add tests/Feature/Iam/IamSecretRecoveryTest.php
git commit -m "test(iam): tambah test store delegasi ke IamSecretService (RED)"
```

---

### Task 9: Tipiskan AplikasiController.store — delegate ke service (GREEN)

**Files:**
- Modify: `app/Http/Controllers/Iam/AplikasiController.php`

- [ ] **Step 1: Inject service dan ubah `store()` + `regenerateKey()`**

Edit `app/Http/Controllers/Iam/AplikasiController.php`:

Ubah method `store()` (line 60-75) dari:

```php
    public function store(StoreAplikasiRequest $request): RedirectResponse
    {
        // Buat aplikasi dengan data validasi (api_key & api_secret_hash tidak fillable)
        $app = IamApplication::create($request->validated());

        // Generate & set credentials secara manual setelah create
        // (sama dengan approach di regenerateKey())
        ['key' => $key, 'secret' => $secret, 'hash' => $hash] = IamApplication::generateApiCredentials();
        $app->api_key = $key;
        $app->api_secret_hash = $hash;
        $app->save();

        return redirect()
            ->route('iam.aplikasi.show', $app)
            ->with('api_secret_once', $secret);
    }
```

menjadi:

```php
    public function store(
        StoreAplikasiRequest $request,
        \App\Services\Iam\IamSecretService $secretService,
    ): RedirectResponse {
        $app = IamApplication::create($request->validated());
        $plaintext = $secretService->generateAndStore($app, $request);

        return redirect()
            ->route('iam.aplikasi.show', $app)
            ->with('api_secret_once', $plaintext);
    }
```

Ubah method `regenerateKey()` (line 103-113) dari:

```php
    public function regenerateKey(IamApplication $aplikasi): RedirectResponse
    {
        ['key' => $key, 'secret' => $secret, 'hash' => $hash] = IamApplication::generateApiCredentials();

        // Set field sensitif secara manual karena tidak fillable
        $aplikasi->api_key = $key;
        $aplikasi->api_secret_hash = $hash;
        $aplikasi->save();

        return back()->with('api_secret_once', $secret);
    }
```

menjadi:

```php
    public function regenerateKey(
        \Illuminate\Http\Request $request,
        IamApplication $aplikasi,
        \App\Services\Iam\IamSecretService $secretService,
    ): RedirectResponse {
        abort_if($aplikasi->is_system, 403, 'Aplikasi sistem tidak dapat diregenerasi');

        $plaintext = $secretService->regenerate($aplikasi, $request);

        return back()->with('api_secret_once', $plaintext);
    }
```

- [ ] **Step 2: Run test feature**

```bash
./vendor/bin/pest tests/Feature/Iam/IamSecretRecoveryTest.php
```

Expected: PASS — store test passing (cache + activity log terisi).

- [ ] **Step 3: Run existing AplikasiControllerTest untuk regression**

```bash
./vendor/bin/pest tests/Feature/Iam/AplikasiControllerTest.php
```

Expected: PASS — semua test existing tetap lulus.

- [ ] **Step 4: Commit refactor controller**

```bash
git add app/Http/Controllers/Iam/AplikasiController.php
git commit -m "refactor(iam): delegate AplikasiController ke IamSecretService

store() dan regenerateKey() sekarang tipis — semua lifecycle
secret di-handle oleh service. SRP-compliant."
```

---

### Task 10: Test props `recovery_status` ter-expose di show & `secret_recoverable` di index

**Files:**
- Modify: `tests/Feature/Iam/IamSecretRecoveryTest.php`

- [ ] **Step 1: Tambah 2 test untuk props recovery di controller**

Tambahkan di akhir `tests/Feature/Iam/IamSecretRecoveryTest.php`:

```php
test('GET /iam/aplikasi/{id} exposes recovery_status props correctly', function () {
    $app = IamApplication::factory()->create(['is_system' => false]);
    Cache::put("iam:secret:recovery:{$app->id}", 'TEST_RECOVERABLE_PLAINTEXT', now()->addMinutes(15));

    $response = $this->actingAs($this->admin)->get("/iam/aplikasi/{$app->id}");
    $response->assertOk();

    $props = $response->viewData('page')['props'];
    expect($props['recovery_status']['recoverable'])->toBeTrue();
    expect($props['recovery_status']['ttl_remaining_secs'])->toBeGreaterThan(0);
});

test('GET /iam/aplikasi/{id} returns recoverable=false when cache empty', function () {
    $app = IamApplication::factory()->create(['is_system' => false]);
    Cache::forget("iam:secret:recovery:{$app->id}");

    $response = $this->actingAs($this->admin)->get("/iam/aplikasi/{$app->id}");
    $response->assertOk();

    $props = $response->viewData('page')['props'];
    expect($props['recovery_status']['recoverable'])->toBeFalse();
    expect($props['recovery_status']['ttl_remaining_secs'])->toBe(0);
});

test('GET /iam/aplikasi exposes secret_recoverable on each app row', function () {
    $appWith = IamApplication::factory()->create(['slug' => 'with-cache']);
    $appWithout = IamApplication::factory()->create(['slug' => 'without-cache']);

    Cache::put("iam:secret:recovery:{$appWith->id}", 'X', now()->addMinutes(15));
    Cache::forget("iam:secret:recovery:{$appWithout->id}");

    $response = $this->actingAs($this->admin)->get('/iam/aplikasi');
    $response->assertOk();

    $list = $response->viewData('page')['props']['aplikasi'];

    $rowWith = collect($list)->firstWhere('slug', 'with-cache');
    $rowWithout = collect($list)->firstWhere('slug', 'without-cache');

    expect($rowWith['secret_recoverable'])->toBeTrue();
    expect($rowWithout['secret_recoverable'])->toBeFalse();
});
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
./vendor/bin/pest tests/Feature/Iam/IamSecretRecoveryTest.php
```

Expected: 3 test baru FAIL — props `recovery_status` dan `secret_recoverable` belum ada.

- [ ] **Step 3: Commit test failing**

```bash
git add tests/Feature/Iam/IamSecretRecoveryTest.php
git commit -m "test(iam): tambah test props recovery_status & secret_recoverable (RED)"
```

---

### Task 11: Tambah props `recovery_status` ke show() & `secret_recoverable` ke index() (GREEN)

**Files:**
- Modify: `app/Http/Controllers/Iam/AplikasiController.php`

- [ ] **Step 1: Update method `index()` untuk inject service & set field**

Edit `app/Http/Controllers/Iam/AplikasiController.php` method `index()` (line 15-28):

```php
    public function index(\App\Services\Iam\IamSecretService $secretService): Response
    {
        $aplikasi = IamApplication::withCount('roles')
            ->latest()
            ->get()
            ->map(function ($app) use ($secretService) {
                $app->api_key_display = $this->maskApiKey($app->api_key);
                $app->secret_recoverable = $secretService->hasRecoverableSecret($app);
                unset($app->api_key);

                return $app;
            });

        return inertia('iam/aplikasi/index', compact('aplikasi'));
    }
```

- [ ] **Step 2: Update method `show()` untuk tambah recovery_status**

Edit `app/Http/Controllers/Iam/AplikasiController.php` method `show()` (line 30-49):

```php
    public function show(
        IamApplication $aplikasi,
        \App\Services\Iam\IamPermissionAuditor $auditor,
        \App\Services\Iam\IamSecretService $secretService,
    ): Response {
        $aplikasi->load(['roles.permissions', 'permissions']);

        // Mask api_key — tampilkan 4 karakter pertama dan 8 terakhir saja
        $aplikasiArray = $aplikasi->toArray();
        $aplikasiArray['api_key_display'] = $this->maskApiKey($aplikasi->api_key);
        unset($aplikasiArray['api_key']);

        $nonCanonicalCount = $auditor->findNonCanonical()
            ->filter(fn ($p) => $p['app'] === $aplikasi->slug)
            ->count();

        return inertia('iam/aplikasi/show', [
            'aplikasi' => $aplikasiArray,
            'permission_audit' => [
                'non_canonical_count' => $nonCanonicalCount,
            ],
            'recovery_status' => [
                'recoverable' => $secretService->hasRecoverableSecret($aplikasi),
                'ttl_remaining_secs' => $secretService->getRecoveryTtlSeconds($aplikasi),
            ],
        ]);
    }
```

- [ ] **Step 3: Run tests**

```bash
./vendor/bin/pest tests/Feature/Iam/IamSecretRecoveryTest.php
```

Expected: PASS — semua test (5 sampai sini) lulus.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Iam/AplikasiController.php
git commit -m "feat(iam): expose recovery_status & secret_recoverable di props Inertia"
```

✓ **Checkpoint 2**: Flow store + regenerate sudah pakai service. Audit log otomatis. Props recovery sudah expose ke frontend.

---

## PHASE 3 — Recovery Endpoints

### Task 12: Test endpoint POST /recover-secret (RED)

**Files:**
- Modify: `tests/Feature/Iam/IamSecretRecoveryTest.php`

- [ ] **Step 1: Tambah test untuk recover endpoint**

Tambahkan di akhir `tests/Feature/Iam/IamSecretRecoveryTest.php`:

```php
test('POST /recover-secret returns plaintext via flash when within TTL', function () {
    $app = IamApplication::factory()->create(['is_system' => false]);
    Cache::put("iam:secret:recovery:{$app->id}", 'RECOVERED_PLAIN_64CHARS', now()->addMinutes(15));

    $this->actingAs($this->admin)
        ->post("/iam/aplikasi/{$app->id}/recover-secret")
        ->assertRedirect()
        ->assertSessionHas('api_secret_once', 'RECOVERED_PLAIN_64CHARS');

    $activity = Activity::query()
        ->where('log_name', 'iam_audit')
        ->where('event', 'secret.recovery_viewed')
        ->where('subject_id', $app->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
});

test('POST /recover-secret flashes error when cache expired', function () {
    $app = IamApplication::factory()->create(['is_system' => false]);
    Cache::forget("iam:secret:recovery:{$app->id}");

    $this->actingAs($this->admin)
        ->post("/iam/aplikasi/{$app->id}/recover-secret")
        ->assertRedirect()
        ->assertSessionMissing('api_secret_once')
        ->assertSessionHas('error');
});

test('POST /recover-secret denied 403 for is_system app', function () {
    $app = IamApplication::factory()->create(['is_system' => true]);
    Cache::put("iam:secret:recovery:{$app->id}", 'X', now()->addMinutes(15));

    $this->actingAs($this->admin)
        ->post("/iam/aplikasi/{$app->id}/recover-secret")
        ->assertForbidden();
});
```

- [ ] **Step 2: Run tests — expect FAIL (route belum ada)**

```bash
./vendor/bin/pest tests/Feature/Iam/IamSecretRecoveryTest.php
```

Expected: 3 test baru FAIL — route 404.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Iam/IamSecretRecoveryTest.php
git commit -m "test(iam): tambah test endpoint /recover-secret (RED)"
```

---

### Task 13: Implementasi IamSecretRecoveryController + route (GREEN)

**Files:**
- Create: `app/Http/Controllers/Iam/IamSecretRecoveryController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Buat controller baru**

Buat `app/Http/Controllers/Iam/IamSecretRecoveryController.php`:

```php
<?php

namespace App\Http\Controllers\Iam;

use App\Http\Controllers\Controller;
use App\Models\IamApplication;
use App\Services\Iam\IamSecretService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IamSecretRecoveryController extends Controller
{
    /**
     * Tampilkan ulang plaintext secret dari cache recovery.
     * Cache TIDAK dihapus setelah view — user bisa lihat berkali-kali
     * selama TTL belum habis (idempotent).
     */
    public function show(
        Request $request,
        IamApplication $aplikasi,
        IamSecretService $secretService,
    ): RedirectResponse {
        abort_if($aplikasi->is_system, 403, 'Aplikasi sistem tidak dapat di-recover');

        $plaintext = $secretService->recoverFromCache($aplikasi, $request);

        if ($plaintext === null) {
            return back()->with(
                'error',
                'Secret sudah tidak bisa dipulihkan. Silakan regenerasi key untuk membuat secret baru.',
            );
        }

        return back()->with('api_secret_once', $plaintext);
    }

    /**
     * User klik "Saya sudah simpan" — hapus cache recovery secara eksplisit.
     */
    public function acknowledge(
        Request $request,
        IamApplication $aplikasi,
        IamSecretService $secretService,
    ): RedirectResponse {
        abort_if($aplikasi->is_system, 403, 'Aplikasi sistem tidak dapat di-acknowledge');

        $secretService->invalidateRecovery($aplikasi, $request);

        return back()->with('success', 'Cache recovery secret telah dihapus.');
    }
}
```

- [ ] **Step 2: Tambah route di `routes/web.php`**

Edit `routes/web.php` — di dalam block `Route::prefix('iam')->name('iam.')->group()` (setelah line 85 `migrate-slug`), tambah:

```php
            Route::post('aplikasi/{aplikasi}/recover-secret', [\App\Http\Controllers\Iam\IamSecretRecoveryController::class, 'show'])
                ->name('aplikasi.recover-secret');
            Route::post('aplikasi/{aplikasi}/acknowledge-secret', [\App\Http\Controllers\Iam\IamSecretRecoveryController::class, 'acknowledge'])
                ->name('aplikasi.acknowledge-secret');
```

- [ ] **Step 3: Run tests untuk verifikasi GREEN**

```bash
./vendor/bin/pest tests/Feature/Iam/IamSecretRecoveryTest.php
```

Expected: PASS — 3 test recover lulus.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Iam/IamSecretRecoveryController.php routes/web.php
git commit -m "feat(iam): tambah endpoint recover-secret untuk recovery flow"
```

---

### Task 14: Test endpoint POST /acknowledge-secret (RED)

**Files:**
- Modify: `tests/Feature/Iam/IamSecretRecoveryTest.php`

- [ ] **Step 1: Tambah 3 test untuk acknowledge endpoint**

Tambahkan di akhir `tests/Feature/Iam/IamSecretRecoveryTest.php`:

```php
test('POST /acknowledge-secret removes cache and logs acknowledged event', function () {
    $app = IamApplication::factory()->create(['is_system' => false]);
    Cache::put("iam:secret:recovery:{$app->id}", 'WILL_BE_FORGOTTEN', now()->addMinutes(15));

    $this->actingAs($this->admin)
        ->post("/iam/aplikasi/{$app->id}/acknowledge-secret")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Cache::has("iam:secret:recovery:{$app->id}"))->toBeFalse();

    $activity = Activity::query()
        ->where('log_name', 'iam_audit')
        ->where('event', 'secret.recovery_acknowledged')
        ->where('subject_id', $app->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
});

test('POST /acknowledge-secret is idempotent when cache already empty', function () {
    $app = IamApplication::factory()->create(['is_system' => false]);
    Cache::forget("iam:secret:recovery:{$app->id}");

    // Tidak boleh error walau cache kosong
    $this->actingAs($this->admin)
        ->post("/iam/aplikasi/{$app->id}/acknowledge-secret")
        ->assertRedirect();
});

test('POST /acknowledge-secret denied 403 for is_system app', function () {
    $app = IamApplication::factory()->create(['is_system' => true]);

    $this->actingAs($this->admin)
        ->post("/iam/aplikasi/{$app->id}/acknowledge-secret")
        ->assertForbidden();
});
```

- [ ] **Step 2: Run tests — endpoint sudah ada (Task 13), expect PASS**

```bash
./vendor/bin/pest tests/Feature/Iam/IamSecretRecoveryTest.php
```

Expected: PASS — semua 11 test sampai sini lulus (1 store + 2 props show + 1 secret_recoverable + 3 recover + 3 acknowledge + 1 props show false).

- [ ] **Step 3: Commit test**

```bash
git add tests/Feature/Iam/IamSecretRecoveryTest.php
git commit -m "test(iam): tambah test endpoint /acknowledge-secret"
```

✓ **Checkpoint 3**: Backend recovery flow lengkap (store, regenerate, recover, acknowledge). Semua endpoint teruji + audit log.

---

## PHASE 4 — Rate Limiting & HMAC Audit

### Task 15: Test rate limit regenerate-key (RED — 6th request 429)

**Files:**
- Modify: `tests/Feature/Iam/IamSecretRecoveryTest.php`

- [ ] **Step 1: Tambah test untuk rate limit**

Tambahkan di akhir `tests/Feature/Iam/IamSecretRecoveryTest.php`:

```php
test('POST /regenerate-key respects rate limit: 5 per hour per user, 6th gets blocked', function () {
    $app = IamApplication::factory()->create(['is_system' => false]);

    // Clear rate limiter terlebih dahulu
    \Illuminate\Support\Facades\RateLimiter::clear('iam-regenerate:'.$this->admin->id);

    // 5 request pertama sukses
    for ($i = 1; $i <= 5; $i++) {
        $this->actingAs($this->admin)
            ->post("/iam/aplikasi/{$app->id}/regenerate-key")
            ->assertRedirect();
    }

    // Request ke-6 ditolak — back() dengan flash error
    $response = $this->actingAs($this->admin)
        ->post("/iam/aplikasi/{$app->id}/regenerate-key");

    $response->assertRedirect();
    $response->assertSessionHas('error');
    $errorMsg = session('error');
    expect($errorMsg)->toContain('batas regenerasi');
});

test('rate limit terpisah per user', function () {
    $admin2 = Pegawai::factory()->admin()->create();
    $app = IamApplication::factory()->create(['is_system' => false]);

    \Illuminate\Support\Facades\RateLimiter::clear('iam-regenerate:'.$this->admin->id);
    \Illuminate\Support\Facades\RateLimiter::clear('iam-regenerate:'.$admin2->id);

    // Admin 1 habiskan 5 request
    for ($i = 1; $i <= 5; $i++) {
        $this->actingAs($this->admin)
            ->post("/iam/aplikasi/{$app->id}/regenerate-key")
            ->assertRedirect();
    }

    // Admin 2 masih bisa
    $this->actingAs($admin2)
        ->post("/iam/aplikasi/{$app->id}/regenerate-key")
        ->assertRedirect()
        ->assertSessionMissing('error');
});
```

- [ ] **Step 2: Run tests — expect FAIL (limiter belum ada)**

```bash
./vendor/bin/pest tests/Feature/Iam/IamSecretRecoveryTest.php --filter="rate limit"
```

Expected: FAIL — semua 6 request sukses, tidak ada error.

- [ ] **Step 3: Commit test failing**

```bash
git add tests/Feature/Iam/IamSecretRecoveryTest.php
git commit -m "test(iam): tambah test rate limit regenerate-key 5/jam (RED)"
```

---

### Task 16: Register named rate limiter `iam-regenerate` (GREEN)

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Tambah register limiter di `AppServiceProvider::boot()`**

Edit `app/Providers/AppServiceProvider.php`. Tambah import di atas (setelah import lain):

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\RateLimiter;
```

Tambah method baru di class:

```php
    private function registerRateLimiters(): void
    {
        RateLimiter::for('iam-regenerate', function (HttpRequest $request) {
            return Limit::perHour(5)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (HttpRequest $request, array $headers) {
                    return back()->with(
                        'error',
                        'Anda telah melampaui batas regenerasi kunci (5 per jam). Silakan coba lagi nanti.',
                    );
                });
        });
    }
```

Panggil dari `boot()` — tambah di akhir method `boot()` (setelah `$this->registerSlowQueryLogger();`):

```php
        $this->registerRateLimiters();
```

- [ ] **Step 2: Tambah middleware `throttle:iam-regenerate` di route**

Edit `routes/web.php` — ganti route regenerate-key (line 74-75) dari:

```php
            Route::post('aplikasi/{aplikasi}/regenerate-key', [AplikasiController::class, 'regenerateKey'])
                ->name('aplikasi.regenerate-key');
```

menjadi:

```php
            Route::post('aplikasi/{aplikasi}/regenerate-key', [AplikasiController::class, 'regenerateKey'])
                ->middleware('throttle:iam-regenerate')
                ->name('aplikasi.regenerate-key');
```

- [ ] **Step 3: Run tests untuk verifikasi GREEN**

```bash
./vendor/bin/pest tests/Feature/Iam/IamSecretRecoveryTest.php --filter="rate limit"
```

Expected: PASS — 2 test rate limit lulus.

- [ ] **Step 4: Run ALL existing tests untuk pastikan tidak regression**

```bash
./vendor/bin/pest tests/Feature/Iam/
```

Expected: PASS — semua test IAM lulus.

- [ ] **Step 5: Commit**

```bash
git add app/Providers/AppServiceProvider.php routes/web.php
git commit -m "feat(iam): register rate limiter iam-regenerate 5/jam per user"
```

---

### Task 17: Test HMAC verification failure logging (RED)

**Files:**
- Modify: `tests/Feature/Iam/VerifyIamSignatureTest.php`

- [ ] **Step 1: Tambah test untuk audit log saat HMAC failure**

Tambahkan di akhir `tests/Feature/Iam/VerifyIamSignatureTest.php`:

```php
test('HMAC verification failure logs hmac.verification_failed event with reason', function () {
    $user = \App\Models\Pegawai::factory()->create();
    $secret = 'correct-secret-64chars-padding-here-abc123def456ghi789';

    $app = new \App\Models\IamApplication([
        'nama' => 'Test App HMAC Audit',
        'slug' => 'test-hmac-audit',
        'url' => 'http://test.local',
        'is_active' => true,
    ]);
    $app->api_key = 'audit-test-key';
    $app->api_secret_hash = \Illuminate\Support\Facades\Crypt::encryptString($secret);
    $app->is_system = false;
    $app->save();

    \Laravel\Sanctum\Sanctum::actingAs($user);

    $ts = now()->timestamp;
    $payload = 'GET:/test-iam-signature:::'.$ts;
    $wrongSignature = hash_hmac('sha256', $payload, 'wrong-secret');

    $this->getJson('/test-iam-signature', [
        'X-App-Key' => $app->api_key,
        'X-Signature' => $wrongSignature,
        'X-Timestamp' => $ts,
    ])->assertStatus(401);

    $activity = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'iam_audit')
        ->where('event', 'hmac.verification_failed')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['reason'])->toBe('signature_mismatch');
    expect($activity->properties['path'])->toBe('/test-iam-signature');
    expect($activity->properties['method'])->toBe('GET');
});

test('HMAC failure dengan api_key tidak terdaftar log reason=app_not_found', function () {
    $user = \App\Models\Pegawai::factory()->create();
    \Laravel\Sanctum\Sanctum::actingAs($user);

    $this->getJson('/test-iam-signature', [
        'X-App-Key' => 'nonexistent-key',
        'X-Signature' => 'whatever',
        'X-Timestamp' => now()->timestamp,
    ])->assertStatus(401);

    $activity = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'iam_audit')
        ->where('event', 'hmac.verification_failed')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['reason'])->toBe('app_not_found');
});

test('HMAC failure missing header log reason=missing_header', function () {
    $user = \App\Models\Pegawai::factory()->create();
    \Laravel\Sanctum\Sanctum::actingAs($user);

    $this->getJson('/test-iam-signature')->assertStatus(401);

    $activity = \Spatie\Activitylog\Models\Activity::query()
        ->where('log_name', 'iam_audit')
        ->where('event', 'hmac.verification_failed')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['reason'])->toBe('missing_header');
});
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
./vendor/bin/pest tests/Feature/Iam/VerifyIamSignatureTest.php --filter="hmac"
```

Expected: 3 test FAIL — activity log belum ditulis oleh middleware.

- [ ] **Step 3: Commit test failing**

```bash
git add tests/Feature/Iam/VerifyIamSignatureTest.php
git commit -m "test(iam): tambah test audit log HMAC failure (RED)"
```

---

### Task 18: Tambah audit log di VerifyIamSignature middleware (GREEN)

**Files:**
- Modify: `app/Http/Middleware/VerifyIamSignature.php`

- [ ] **Step 1: Refactor middleware untuk pisah failure reason dan log audit**

Edit `app/Http/Middleware/VerifyIamSignature.php`. Replace seluruh isi class dengan:

```php
<?php

namespace App\Http\Middleware;

use App\Models\IamApplication;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

class VerifyIamSignature
{
    private const TIMESTAMP_WINDOW = 300;
    private const ACTIVITY_LOG_NAME = 'iam_audit';

    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-App-Key');
        $timestamp = $request->header('X-Timestamp');
        $received = $request->header('X-Signature');

        if (! $apiKey || ! $timestamp || ! $received) {
            $this->logFailure($request, null, 'missing_header', $timestamp);

            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (abs(now()->timestamp - (int) $timestamp) > self::TIMESTAMP_WINDOW) {
            $this->logFailure($request, null, 'invalid_timestamp', $timestamp);

            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $app = IamApplication::where('api_key', $apiKey)->where('is_active', true)->first();

        if (! $app) {
            $this->logFailure($request, null, 'app_not_found', $timestamp);

            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Rekonstruksi payload: METHOD:PATH:SORTED_QUERY:BODY_SHA256:TIMESTAMP
        $queryString = http_build_query(collect($request->query())->sortKeys()->all());
        $bodyHash = hash('sha256', $request->getContent() ?? '');
        $payload = strtoupper($request->method())
            .':'.$request->getPathInfo()
            .':'.$queryString
            .':'.$bodyHash
            .':'.$timestamp;

        try {
            $secret = Crypt::decryptString($app->api_secret_hash);
            $expected = hash_hmac('sha256', $payload, $secret);

            if (! hash_equals($expected, $received)) {
                $this->logFailure($request, $app, 'signature_mismatch', $timestamp);

                return response()->json(['message' => 'Invalid credentials'], 401);
            }
        } catch (\Exception) {
            $this->logFailure($request, $app, 'signature_mismatch', $timestamp);

            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Inject app ke request attributes (aman, tidak bisa di-inject user)
        $request->attributes->set('iam_app', $app);

        return $next($request);
    }

    /**
     * Catat kegagalan verifikasi HMAC ke activity log.
     * Subject = aplikasi (kalau resolve), null kalau api_key tidak terdaftar.
     */
    private function logFailure(Request $request, ?IamApplication $app, string $reason, ?string $receivedTimestamp): void
    {
        $properties = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'path' => $request->getPathInfo(),
            'method' => strtoupper($request->method()),
            'reason' => $reason,
            'received_timestamp' => $receivedTimestamp,
        ];

        $logger = activity(self::ACTIVITY_LOG_NAME);

        if ($app !== null) {
            $logger->performedOn($app);
        }

        $logger->event('hmac.verification_failed')
            ->withProperties($properties)
            ->log('hmac.verification_failed');
    }
}
```

- [ ] **Step 2: Run tests HMAC**

```bash
./vendor/bin/pest tests/Feature/Iam/VerifyIamSignatureTest.php
```

Expected: PASS — semua test HMAC (existing + 3 baru) lulus.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Middleware/VerifyIamSignature.php
git commit -m "feat(iam): tambah audit log hmac.verification_failed di VerifyIamSignature

Catat ip, user_agent, path, method, reason (enum), received_timestamp
untuk forensik. Subject diisi aplikasi kalau api_key resolve."
```

✓ **Checkpoint 4**: Full backend selesai. Rate limit aktif, HMAC failure ter-audit. Semua test feature + unit lulus.

---

## PHASE 5 — Frontend Integration

### Task 19: Update types/iam.ts dengan field & type baru

**Files:**
- Modify: `resources/js/types/iam.ts`

- [ ] **Step 1: Tambah field `secret_recoverable` ke `IamApplication` dan type `RecoveryStatus`**

Edit `resources/js/types/iam.ts`. Ganti definisi `IamApplication` dari:

```typescript
export type IamApplication = {
    id: number;
    nama: string;
    slug: string;
    url: string;
    deskripsi: string | null;
    api_key_display?: string; // API key yang sudah di-mask, bukan full key
    is_active: boolean;
    is_system: boolean;
    roles_count?: number;
    roles?: IamRole[];
    permissions?: IamPermission[];
    created_at: string;
    updated_at: string;
};
```

menjadi:

```typescript
export type IamApplication = {
    id: number;
    nama: string;
    slug: string;
    url: string;
    deskripsi: string | null;
    api_key_display?: string; // API key yang sudah di-mask, bukan full key
    is_active: boolean;
    is_system: boolean;
    secret_recoverable?: boolean; // Apakah secret masih bisa di-recover dari cache
    roles_count?: number;
    roles?: IamRole[];
    permissions?: IamPermission[];
    created_at: string;
    updated_at: string;
};

/**
 * Status recovery secret di halaman show aplikasi.
 * recoverable=true berarti cache masih ada dan user bisa klik "Tampilkan Ulang".
 * ttl_remaining_secs untuk countdown timer di UI.
 */
export type IamRecoveryStatus = {
    recoverable: boolean;
    ttl_remaining_secs: number;
};
```

- [ ] **Step 2: Verifikasi TypeScript compile**

```bash
npm run type-check 2>&1 || npx tsc --noEmit
```

Expected: No errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/types/iam.ts
git commit -m "feat(iam-ui): tambah type IamRecoveryStatus & field secret_recoverable"
```

---

### Task 20: Refactor ApiSecretModal — countdown + tombol "Saya sudah simpan"

**Files:**
- Modify: `resources/js/components/iam/ApiSecretModal.tsx`

- [ ] **Step 1: Rewrite component dengan props baru + countdown + tombol acknowledge**

Replace seluruh isi `resources/js/components/iam/ApiSecretModal.tsx` dengan:

```tsx
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { AlertTriangle, Clock } from 'lucide-react';

interface ApiSecretModalProps {
    apiSecret?: string;
    open: boolean;
    onClose: () => void;
    /** Sisa TTL recovery (detik). Pass 0 atau undefined kalau tidak ada countdown */
    ttlRemainingSecs?: number;
    /** Handler tombol "Saya sudah simpan" — biasanya POST /acknowledge-secret */
    onAcknowledge?: () => void;
    /** Loading state tombol acknowledge */
    acknowledging?: boolean;
}

/**
 * Modal yang menampilkan plaintext API secret sekali setelah create/regenerate.
 * Mendukung recovery selama TTL cache: tampilkan countdown, tombol "Saya sudah simpan"
 * untuk hapus cache secara eksplisit.
 */
export function ApiSecretModal({
    apiSecret,
    open,
    onClose,
    ttlRemainingSecs,
    onAcknowledge,
    acknowledging,
}: ApiSecretModalProps) {
    const [copied, setCopied] = useState(false);
    const [secondsLeft, setSecondsLeft] = useState<number>(ttlRemainingSecs ?? 0);

    // Reset countdown ketika props ttlRemainingSecs berubah (mis. recovery click ulang)
    useEffect(() => {
        setSecondsLeft(ttlRemainingSecs ?? 0);
    }, [ttlRemainingSecs]);

    // Live countdown setiap detik selama modal terbuka
    useEffect(() => {
        if (!open || secondsLeft <= 0) return;

        const intervalId = window.setInterval(() => {
            setSecondsLeft((prev) => Math.max(0, prev - 1));
        }, 1000);

        return () => window.clearInterval(intervalId);
    }, [open, secondsLeft]);

    if (!apiSecret) {
        return null;
    }

    const handleCopy = async () => {
        await navigator.clipboard.writeText(apiSecret);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const formatCountdown = (totalSec: number): string => {
        const m = Math.floor(totalSec / 60);
        const s = totalSec % 60;
        return `${m} menit ${s.toString().padStart(2, '0')} detik`;
    };

    return (
        <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>API Secret Baru</DialogTitle>
                    <DialogDescription>
                        <span className="flex items-start gap-2">
                            <AlertTriangle className="mt-0.5 h-4 w-4 flex-shrink-0 text-amber-600" aria-hidden="true" />
                            <span>
                                <strong>PENTING</strong> — Simpan secret ini sekarang. Setelah 15 menit,
                                secret tidak bisa ditampilkan lagi (kecuali regenerasi).
                            </span>
                        </span>
                    </DialogDescription>
                </DialogHeader>

                <div className="flex items-start gap-2 rounded border bg-muted p-3">
                    <code className="flex-1 break-all text-sm">{apiSecret}</code>
                    <Button variant="outline" size="sm" onClick={handleCopy}>
                        {copied ? 'Tersalin!' : 'Salin'}
                    </Button>
                </div>

                {secondsLeft > 0 && (
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Clock className="h-4 w-4" aria-hidden="true" />
                        Bisa dipulihkan selama {formatCountdown(secondsLeft)}
                    </p>
                )}

                <DialogFooter className="flex-col gap-2 sm:flex-row">
                    <Button variant="outline" onClick={onClose} className="w-full sm:w-auto">
                        Tutup (tetap bisa recovery)
                    </Button>
                    {onAcknowledge && (
                        <Button
                            onClick={onAcknowledge}
                            disabled={acknowledging}
                            className="w-full sm:w-auto"
                        >
                            {acknowledging ? 'Memproses...' : '✓ Saya sudah simpan (hapus dari cache)'}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 2: Verifikasi TypeScript compile**

```bash
npx tsc --noEmit
```

Expected: No errors di file ini.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/iam/ApiSecretModal.tsx
git commit -m "feat(iam-ui): refactor ApiSecretModal dengan countdown & tombol acknowledge"
```

---

### Task 21: Update show.tsx — block recovery UI + handler ack/recover

**Files:**
- Modify: `resources/js/pages/iam/aplikasi/show.tsx`

- [ ] **Step 1: Update Props type & destructure**

Edit `resources/js/pages/iam/aplikasi/show.tsx`. Ganti definisi `Props` (line 51-59):

```typescript
type Props = {
    aplikasi: IamApplication & { api_key_display?: string };
    flash?: {
        api_secret_once?: string;
    };
    permission_audit?: {
        non_canonical_count: number;
    };
    recovery_status?: {
        recoverable: boolean;
        ttl_remaining_secs: number;
    };
};
```

Ganti destructure di awal `Show()` (line 62):

```typescript
    const { aplikasi, flash, permission_audit, recovery_status } = usePage<Props>().props;
```

- [ ] **Step 2: Tambah form & handler untuk recover & acknowledge**

Tambahkan setelah deklarasi `regenerateForm` (line 111) dan sebelum `deleteForm`:

```typescript
    // Form untuk recover & acknowledge secret
    const recoverForm = useForm({});
    const acknowledgeForm = useForm({});

    const handleRecoverSecret = useCallback(() => {
        recoverForm.post(`/iam/aplikasi/${aplikasi.id}/recover-secret`);
    }, [aplikasi.id, recoverForm]);

    const handleAcknowledgeSecret = useCallback(() => {
        acknowledgeForm.post(`/iam/aplikasi/${aplikasi.id}/acknowledge-secret`, {
            onSuccess: () => {
                setShowApiSecretModal(false);
                setApiSecret(null);
            },
        });
    }, [aplikasi.id, acknowledgeForm]);
```

- [ ] **Step 3: Render block kuning recovery di Tab Info, sebelum section "Regenerasi API Key"**

Di `Tab Info` (`<TabsContent value="info">`), tambahkan **sebelum** block "Regenerasi API Key" (line 844 — sebelum `{!aplikasi.is_system && (`):

```tsx
                                {recovery_status?.recoverable && !aplikasi.is_system && (
                                    <div className="rounded-md border-l-4 border-amber-500 bg-amber-50 dark:bg-amber-950/30 p-4">
                                        <h3 className="mb-2 flex items-center gap-2 font-medium text-amber-900 dark:text-amber-100">
                                            <Key className="h-4 w-4" aria-hidden="true" />
                                            Secret bisa dipulihkan
                                        </h3>
                                        <p className="mb-3 text-sm text-amber-800 dark:text-amber-200">
                                            Sisa waktu: {Math.floor(recovery_status.ttl_remaining_secs / 60)} menit{' '}
                                            {(recovery_status.ttl_remaining_secs % 60).toString().padStart(2, '0')} detik
                                        </p>
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={handleRecoverSecret}
                                                disabled={recoverForm.processing}
                                            >
                                                🔓 Tampilkan Ulang Secret
                                            </Button>
                                            <Button
                                                variant="default"
                                                size="sm"
                                                onClick={handleAcknowledgeSecret}
                                                disabled={acknowledgeForm.processing}
                                            >
                                                ✓ Saya sudah simpan
                                            </Button>
                                        </div>
                                    </div>
                                )}
```

- [ ] **Step 4: Update ApiSecretModal usage di akhir component**

Ganti block ApiSecretModal di akhir (line 905-909):

```tsx
            {/* Modal API Secret — gunakan komponen shared */}
            <ApiSecretModal
                apiSecret={apiSecret ?? undefined}
                open={showApiSecretModal}
                onClose={() => setShowApiSecretModal(false)}
                ttlRemainingSecs={recovery_status?.ttl_remaining_secs ?? 0}
                onAcknowledge={handleAcknowledgeSecret}
                acknowledging={acknowledgeForm.processing}
            />
```

- [ ] **Step 5: Verifikasi TypeScript compile**

```bash
npx tsc --noEmit
```

Expected: No errors.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/iam/aplikasi/show.tsx
git commit -m "feat(iam-ui): tambah block recovery UI & handler ack/recover di show page"
```

---

### Task 22: Update index.tsx — badge `🟡 Recoverable` di kolom Status

**Files:**
- Modify: `resources/js/pages/iam/aplikasi/index.tsx`

- [ ] **Step 1: Update render Status cell untuk tampil badge recoverable**

Edit `resources/js/pages/iam/aplikasi/index.tsx`. Ganti `<TableCell className="text-center">` untuk Status (line 295-307):

```tsx
                                        <TableCell className="text-center">
                                            <div className="flex flex-wrap items-center justify-center gap-1">
                                                <Badge
                                                    variant={
                                                        app.is_active
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                >
                                                    {app.is_active
                                                        ? 'Aktif'
                                                        : 'Nonaktif'}
                                                </Badge>
                                                {app.secret_recoverable && (
                                                    <Link
                                                        href={`/iam/aplikasi/${app.id}`}
                                                        aria-label={`Secret bisa dipulihkan untuk ${app.nama}`}
                                                    >
                                                        <Badge
                                                            variant="outline"
                                                            className="border-amber-500 bg-amber-50 text-amber-900 hover:bg-amber-100 dark:bg-amber-950/30 dark:text-amber-100"
                                                        >
                                                            🟡 Recoverable
                                                        </Badge>
                                                    </Link>
                                                )}
                                            </div>
                                        </TableCell>
```

- [ ] **Step 2: Verifikasi TypeScript compile**

```bash
npx tsc --noEmit
```

Expected: No errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/iam/aplikasi/index.tsx
git commit -m "feat(iam-ui): tambah badge Recoverable di kolom Status list aplikasi"
```

---

### Task 23: Run frontend lint & full backend test suite

**Files:** (no edits, verification only)

- [ ] **Step 1: Run ESLint untuk file frontend yang berubah**

```bash
npx eslint resources/js/components/iam/ApiSecretModal.tsx resources/js/pages/iam/aplikasi/show.tsx resources/js/pages/iam/aplikasi/index.tsx resources/js/types/iam.ts
```

Expected: No errors. (Warning style boleh, jangan critical.)

- [ ] **Step 2: Run TypeScript check**

```bash
npx tsc --noEmit
```

Expected: No errors.

- [ ] **Step 3: Run full backend test suite untuk regression check**

```bash
./vendor/bin/pest tests/Unit/Services/Iam/ tests/Feature/Iam/
```

Expected: PASS — semua test (unit + feature) lulus.

- [ ] **Step 4: Build frontend untuk pastikan tidak ada error production build**

```bash
npm run build
```

Expected: Build success.

- [ ] **Step 5: Commit (jika ada perbaikan lint)**

Jika ada perbaikan minor (e.g. unused import):

```bash
git add -p
git commit -m "chore(iam-ui): perbaiki lint warning di IAM secret display"
```

Jika tidak ada perbaikan, skip step ini.

✓ **Checkpoint 5**: Full feature siap. Test lulus, lint clean, build sukses.

---

## PHASE 6 — Documentation & Manual Smoke Test

### Task 24: Tulis dokumentasi `docs/iam/secret-management.md`

**Files:**
- Create: `docs/iam/secret-management.md`

- [ ] **Step 1: Buat folder docs/iam (jika belum ada)**

```bash
mkdir -p docs/iam
```

- [ ] **Step 2: Tulis dokumentasi lifecycle & query examples**

Buat `docs/iam/secret-management.md`:

```markdown
# IAM Secret Management

Dokumen ini menjelaskan lifecycle plaintext API secret di IAM Hub: kapan di-generate, di-cache, dan diaudit.

## Lifecycle Plaintext Secret

```
NEVER_GENERATED → RECOVERABLE (15m TTL) → COMMITTED (steady state)
                       ↓ (regenerate)
                  RECOVERABLE (new TTL)
```

### State Definisi

- **NEVER_GENERATED**: Aplikasi baru di-create, sebelum service.generateAndStore() selesai.
- **RECOVERABLE**: Plaintext ada di cache `iam:secret:recovery:{app_id}`, modal bisa di-recover.
- **COMMITTED**: Cache miss (TTL habis atau di-acknowledge). HMAC verification tetap jalan dari DB.

## Cache Convention

| Key | Value | TTL | Store |
|---|---|---|---|
| `iam:secret:recovery:{app_id}` | Plaintext secret 64 char | 15 menit | `CACHE_STORE=database` |

App ID pakai ULID (dari `HasUlids` trait), tidak bisa collision walaupun slug di-rename.

## Audit Events

Semua event ditulis ke table `activity_log` dengan `log_name='iam_audit'`.

### Query Examples

**Lihat semua event regenerate dalam 7 hari terakhir:**

```sql
SELECT created_at, causer_id, subject_id, properties
FROM activity_log
WHERE log_name = 'iam_audit'
  AND event = 'secret.regenerated'
  AND created_at >= NOW() - INTERVAL '7 days'
ORDER BY created_at DESC;
```

**Hitung HMAC failure per aplikasi (untuk threat detection):**

```sql
SELECT
    subject_id AS app_id,
    properties->>'reason' AS reason,
    COUNT(*) AS failure_count
FROM activity_log
WHERE log_name = 'iam_audit'
  AND event = 'hmac.verification_failed'
  AND created_at >= NOW() - INTERVAL '1 hour'
GROUP BY subject_id, properties->>'reason'
ORDER BY failure_count DESC;
```

**Trace siapa yang regenerate aplikasi tertentu:**

```sql
SELECT
    al.created_at,
    p.nama_lengkap AS causer_name,
    al.properties->>'previous_key_prefix' AS old_key,
    al.properties->>'ip' AS ip
FROM activity_log al
LEFT JOIN pegawai p ON p.id = al.causer_id
WHERE al.log_name = 'iam_audit'
  AND al.event = 'secret.regenerated'
  AND al.subject_id = '01HQX...your_app_ulid';
```

## Rate Limit

Endpoint `POST /iam/aplikasi/{id}/regenerate-key` di-protect named limiter `iam-regenerate`:
- 5 request per jam per user_id (fallback: per IP kalau guest).
- Request ke-6 dapat flash error "Anda telah melampaui batas regenerasi kunci (5 per jam)".

Reset limiter manual via Tinker:

```bash
php artisan tinker
>>> RateLimiter::clear('iam-regenerate:'.$user_id);
```

## Backward Compatibility

HMAC verification dari aplikasi client **tidak berubah**. Server tetap pakai `Crypt::decryptString($app->api_secret_hash)` (sumber DB). Cache hanya layer recovery, tidak operational.

## Manual Smoke Test Checklist

- [ ] Create aplikasi baru → modal tampil dengan secret.
- [ ] Tutup modal → reload show page → block kuning visible → klik "Tampilkan Ulang" → modal tampil lagi dengan secret yang sama.
- [ ] Klik "Saya sudah simpan" → reload → block kuning hilang.
- [ ] Tunggu >15 menit → block kuning hilang otomatis.
- [ ] Regenerate 6 kali → request ke-6 dapat flash error.
- [ ] Query `activity_log` → ada 4 jenis event sesuai aksi.
- [ ] Send signature salah ke endpoint protected → ada entry `hmac.verification_failed`.
```

- [ ] **Step 3: Commit dokumentasi**

```bash
git add docs/iam/secret-management.md
git commit -m "docs(iam): tambah dokumentasi lifecycle secret & query audit"
```

---

### Task 25: Manual smoke test dengan dev server

**Files:** (verification only, no edits)

- [ ] **Step 1: Start dev server**

Di terminal 1:

```bash
php artisan serve
```

Di terminal 2:

```bash
npm run dev
```

- [ ] **Step 2: Login sebagai admin, jalankan smoke checklist (catat hasil)**

Buka browser ke `http://localhost:8000/iam/aplikasi`. Login sebagai admin.

Jalankan checklist:

1. **Create aplikasi baru**: Klik "Daftarkan Aplikasi", isi form, submit.
   - **Expect**: Redirect ke show page, modal tampil dengan secret 64 char, countdown "14 menit XX detik".

2. **Tutup modal**: Klik tombol "Tutup (tetap bisa recovery)".
   - **Expect**: Modal close, block kuning di Tab Info muncul, badge "🟡 Recoverable" di list aplikasi.

3. **Recovery**: Buka tab Info, klik "🔓 Tampilkan Ulang Secret".
   - **Expect**: Modal tampil lagi dengan secret yang sama, countdown lanjut.

4. **Acknowledge**: Klik "✓ Saya sudah simpan".
   - **Expect**: Modal close, block kuning hilang dari Tab Info, badge "🟡 Recoverable" hilang dari list, success flash.

5. **Regenerate 6x**: Klik "Regenerasi Key" 6 kali berturut-turut.
   - **Expect**: 5 sukses, request ke-6 dapat flash error "Anda telah melampaui batas regenerasi kunci".

6. **Query audit log**: Buka Tinker:

```bash
php artisan tinker
>>> \Spatie\Activitylog\Models\Activity::where('log_name', 'iam_audit')->latest()->take(10)->get(['event', 'properties'])
```

   - **Expect**: Lihat event `secret.created`, `secret.regenerated`, `secret.recovery_viewed`, `secret.recovery_acknowledged`.

7. **HMAC failure**: Pakai curl untuk kirim request dengan signature salah:

```bash
curl -i -X POST http://localhost:8000/api/iam/check \
    -H "X-App-Key: any-key" \
    -H "X-Signature: invalid" \
    -H "X-Timestamp: $(date +%s)"
```

   - **Expect**: HTTP 401, entry `hmac.verification_failed` dengan `reason: app_not_found` di activity_log.

- [ ] **Step 3: Catat hasil smoke test di commit message**

Jika ada bug ditemukan, fix dulu (tambah task baru), kemudian commit hasil smoke:

```bash
git commit --allow-empty -m "test(iam): manual smoke test lulus untuk IAM Hub secret display

Verified:
- Modal tampil setelah create/regenerate
- Recovery flow via block kuning
- Acknowledge hapus cache
- Rate limit aktif 5/jam
- Audit log 5 jenis event ter-record"
```

✓ **Checkpoint 6**: Feature complete, dokumentasi lengkap, smoke test lulus. Siap untuk code review.

---

## Self-Review Coverage Map

| Spec Section | Task Coverage |
|---|---|
| 1.2 Modal tampil otomatis | Task 6-7, 21 |
| 1.2 Recovery 15 menit | Task 4, 12-13, 21 |
| 1.2 Audit trail lengkap | Task 2, 8, 10, 14, 17-18 |
| 1.2 Rate limit 5/jam | Task 15-16 |
| 1.2 IamSecretService SRP | Task 1-5, 9, 11 |
| 1.2 Backward compatibility HMAC | Task 18 (DB Crypt tetap) |
| 2.1 Component map | Tercermin di Task 7 (middleware), 9-11 (controller), 13 (recovery controller), 16 (rate limiter), 18 (HMAC middleware) |
| 2.2 File diff summary | Cek di "File Structure" section atas |
| 3.1 Cache key convention | Task 2 (constant `iam:secret:recovery:` + 15 min TTL) |
| 3.2 Sequence create | Task 9 (flow store → service → flash) |
| 3.3 Sequence recovery | Task 13 (recover endpoint) |
| 3.4 Sequence acknowledge | Task 14 (acknowledge endpoint) |
| 3.5 State machine | Task 2 (5 method service mengimplementasi 4 state transition) |
| 4.1 Service contract | Task 2 (semua 6 method dengan signature persis sama dengan spec) |
| 4.2 Audit event catalog | Task 2, 18 (5 event: created, regenerated, recovery_viewed, recovery_acknowledged, hmac.verification_failed) |
| 4.3 Rate limiter definition | Task 16 (`RateLimiter::for('iam-regenerate', ...)`) |
| 4.4 Route definitions | Task 13, 16 (3 route: regenerate+throttle, recover-secret, acknowledge-secret) |
| 4.5 Frontend props contract | Task 11 (recovery_status di show, secret_recoverable di index) |
| 5.1 ApiSecretModal layout | Task 20 (semua element: PENTING banner, countdown, 2 tombol) |
| 5.2 Tab Info block recovery | Task 21 (block kuning, sisa waktu, 2 tombol) |
| 5.3 List badge | Task 22 (badge Recoverable di kolom Status) |
| 6.1 Unit tests (10 case) | Task 1, 3, 4, 5 (10 test case implementasi spec) |
| 6.2 Feature tests | Task 6, 8, 10, 12, 14, 15, 17 |
| 6.3 Smoke checklist | Task 25 |
| 8.1 Edge case 1 (regenerate saat cache ada) | Task 3 (test overwrite cache) |
| 8.1 Edge case 2 (cache driver fail) | Task 2 (try-catch di putRecoveryCache) |
| 8.1 Edge case 3 (TTL expire) | Task 12 (test recover dengan cache miss → error flash) |
| 8.1 Edge case 5 (is_system) | Task 12, 14 (3 test 403 untuk system app) |
| 8.1 Edge case 7 (rate limit hit) | Task 15 (test request ke-6 dapat error) |
| 8.1 Edge case 9 (plaintext leak di log) | Task 2 (properties JSON whitelist — tidak ada plaintext) |
| 8.1 Edge case 10 (recovery multiple click) | Task 4 (test idempotent recovery) |

**Catatan migrasi cache table**: Project sudah pakai `CACHE_STORE=database` (per spec 10. References). Engineer perlu memastikan table `cache` sudah di-migrate (`php artisan cache:table && php artisan migrate`) — biasanya sudah ada di project ini. Jika tidak, run sekali sebelum Phase 1.

---

## Execution Handoff

Plan ini selesai dan tersimpan di `docs/superpowers/plans/2026-05-17-iam-hub-secret-display.md`.

Total: **25 task** dipecah dalam **6 fase**, mengikuti TDD (RED-GREEN-REFACTOR-COMMIT) per task.

### Critical Path

**Task 6-7 adalah minimum coherent fix** — jika urgent deploy, bisa ship sampai Task 7 saja (modal bug ter-fix). Sisanya enhancement.

### Pre-requisites untuk Engineer

1. Run `php artisan cache:table && php artisan migrate` sekali (jika table `cache` belum ada).
2. Verifikasi `composer.json` punya `spatie/laravel-activitylog ^5.0` (sudah ada).
3. Project pakai Pest 4.4 + Laravel 12 (sesuai composer.json).
