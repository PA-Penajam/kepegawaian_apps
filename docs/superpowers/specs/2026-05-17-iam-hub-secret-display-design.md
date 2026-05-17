# IAM Hub — Perbaikan Display Secret & Recovery — Design

**Status**: Draft (menunggu review)
**Tanggal**: 2026-05-17
**Author**: brainstorming dengan user (via superpowers:brainstorming)
**Modul terkait**: IAM Hub (`iam_applications`)
**Referensi**:
- [`docs/superpowers/specs/2026-03-21-iam-sso-design.md`](2026-03-21-iam-sso-design.md)
- [`docs/superpowers/specs/2026-05-16-iam-informative-design.md`](2026-05-16-iam-informative-design.md)
- [Spatie Activitylog v5](https://spatie.be/docs/laravel-activitylog/v5)
- [Laravel 12 Rate Limiting](https://laravel.com/docs/12.x/routing#rate-limiting)

---

## 1. Tujuan & Cakupan

### 1.1 Masalah yang Dipecahkan

User admin yang **membuat aplikasi baru** atau **meregenerasi API key** di halaman IAM Aplikasi (`resources/js/pages/iam/aplikasi/show.tsx`, `index.tsx`) **tidak melihat plaintext secret yang di-generate**. Modal yang seharusnya tampil tidak pernah muncul.

**Root cause**: Backend menyimpan plaintext secret ke flash session dengan key `api_secret_once` (`AplikasiController.php:74` & `:112`), tetapi middleware `HandleInertiaRequests::share()` (`app/Http/Middleware/HandleInertiaRequests.php:55-58`) hanya meng-expose key `success` dan `error` ke Inertia props. Akibatnya `flash.api_secret_once` di React selalu `undefined`, `useEffect` tidak trigger, dan `ApiSecretModal` tidak pernah render.

Bug ini sudah ada sejak awal modul IAM Hub deploy — modal memang **tidak pernah berfungsi** di production.

Selain root cause utama, design ini juga memperbaiki kelemahan UX dan security:
- Tidak ada mekanisme **recovery** kalau user accidentally tutup modal sebelum copy.
- Tidak ada **audit trail** untuk operasi sensitif (generate, regenerate, view secret).
- Tidak ada **rate limit** untuk endpoint regenerate-key (bisa di-abuse).
- Tidak ada **logging HMAC verification failure** untuk threat detection.

### 1.2 Hasil yang Diinginkan

1. Modal `ApiSecretModal` muncul otomatis setelah create/regenerate dengan plaintext secret.
2. Plaintext secret bisa di-**recover** dari cache selama 15 menit (TTL) atau sampai user klik "Saya sudah simpan".
3. Audit trail lengkap untuk: generate awal, regenerate, view recovery, acknowledge, HMAC failure.
4. Rate limit regenerate-key: 5x/jam per user.
5. Service class `IamSecretService` sebagai single source of truth lifecycle secret (SRP-compliant).
6. Backward compatibility 100%: HMAC verification dari aplikasi client existing tetap jalan tanpa perubahan kontrak.

### 1.3 Yang Tidak Masuk (YAGNI)

- Email notification ke admin saat regenerate.
- Grace period (secret lama valid X menit setelah regenerate).
- Multi-admin approval workflow.
- Secret rotation reminder (e.g. "secret berusia >180 hari").
- Webhook untuk notifikasi aplikasi client tentang rotation.
- Dashboard analytic untuk HMAC failures (cukup query manual via `activity_log`).
- Persist plaintext history untuk audit (compliance concern, tidak diminta).
- E2E test browser-based; smoke test manual yang akan dijalankan saat implementasi.

---

## 2. Architecture Overview

### 2.1 Component Map

```
┌─────────────────────────────────────────────────────────────────────┐
│                         FRONTEND (React/Inertia)                    │
│                                                                     │
│  show.tsx        ApiSecretModal.tsx        index.tsx                │
│   └─ baca flash    ├─ render plaintext       └─ baca badge          │
│      .api_secret    └─ tombol "Saya sudah         "secret_recoverable"│
│       _once            simpan"                                       │
└──────────────────────────┬──────────────────────────────────────────┘
                           │ Inertia props
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│                  MIDDLEWARE: HandleInertiaRequests                  │
│  share() ──► flash array: success, error, api_secret_once  [FIX]    │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────────────┐
│                CONTROLLERS (tipis, delegate ke service)             │
│                                                                     │
│  AplikasiController                  IamSecretRecoveryController    │
│   - store(req) ─► service.gen        - show(app) ─► service.recover │
│   - regenerateKey(app)               - acknowledge(app) ─► service  │
│       └─► service.regen                .invalidateRecovery          │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────────────┐
│       SERVICE: app/Services/Iam/IamSecretService.php  [NEW]         │
│  ┌────────────────────────────────────────────────────────────────┐ │
│  │ generateAndStore(app, request?): string                        │ │
│  │   ├─ IamApplication::generateApiCredentials()                  │ │
│  │   ├─ save key + crypted_hash to DB                             │ │
│  │   ├─ Cache::put("iam:secret:recovery:{id}", plain, 15m)        │ │
│  │   └─ activity('iam_audit')->event('secret.created')->log(…)    │ │
│  │                                                                │ │
│  │ regenerate(app, request?): string  → event 'secret.regenerated'│ │
│  │                                                                │ │
│  │ recoverFromCache(app, request?): ?string                       │ │
│  │   ├─ Cache::get("iam:secret:recovery:{id}")                    │ │
│  │   └─ if hit → activity()->event('secret.recovery_viewed')      │ │
│  │                                                                │ │
│  │ invalidateRecovery(app, request?): void                        │ │
│  │   ├─ Cache::forget(…)                                          │ │
│  │   └─ activity()->event('secret.recovery_acknowledged')         │ │
│  │                                                                │ │
│  │ hasRecoverableSecret(app): bool  (untuk badge UI)              │ │
│  │ getRecoveryTtlSeconds(app): int  (untuk countdown UI)          │ │
│  └────────────────────────────────────────────────────────────────┘ │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────────────┐
│  MIDDLEWARE: VerifyIamSignature  (existing, +audit log on failure)  │
│   ├─ HMAC mismatch ─► activity()->event('hmac.verification_failed') │
│   └─ on success ─► (no log, terlalu noisy)                          │
└─────────────────────────────────────────────────────────────────────┘
```

### 2.2 File Diff Summary

| File | Action |
|---|---|
| `app/Services/Iam/IamSecretService.php` | **NEW** — service class |
| `app/Http/Controllers/Iam/AplikasiController.php` | Modify — inject service, tipiskan `store()` & `regenerateKey()` |
| `app/Http/Controllers/Iam/IamSecretRecoveryController.php` | **NEW** — endpoint recovery & acknowledge |
| `app/Http/Middleware/HandleInertiaRequests.php` | Modify — share `api_secret_once` |
| `app/Http/Middleware/VerifyIamSignature.php` | Modify — audit log on HMAC failure |
| `app/Providers/AppServiceProvider.php` | Modify — register named rate limiter `iam-regenerate` |
| `routes/web.php` | Modify — tambah 2 route recovery + throttle middleware |
| `resources/js/components/iam/ApiSecretModal.tsx` | Modify — tombol "Saya sudah simpan" + countdown |
| `resources/js/pages/iam/aplikasi/show.tsx` | Modify — block UI recovery + handler |
| `resources/js/pages/iam/aplikasi/index.tsx` | Modify — handle flash + badge recoverable |
| `resources/js/types/iam.ts` | Modify — type `recovery_status`, `secret_recoverable` |
| `tests/Unit/Services/Iam/IamSecretServiceTest.php` | **NEW** — unit test service |
| `tests/Feature/Iam/IamSecretRecoveryTest.php` | **NEW** — integration test endpoint |
| `tests/Feature/Iam/HandleInertiaRequestsFlashTest.php` | **NEW** — verify flash share |

---

## 3. Data Flow & State Lifecycle

### 3.1 Cache Key Convention

```
Key:   iam:secret:recovery:{app_id}
Value: plaintext secret (string, 64 char)
TTL:   15 menit (900 detik) sejak generate/regenerate
Store: cache driver project (database; konsisten dengan CACHE_STORE=database di .env)
```

Naming pakai colon-separated mengikuti konvensi modul lain di project (cek `iam_app:{$slug}` di `AplikasiController::update`). ID aplikasi pakai ULID (bukan integer auto-increment) agar tidak collision ketika slug di-rename.

### 3.2 Sequence: Create Aplikasi Baru

```
User             AplikasiController       IamSecretService      DB        Cache    ActivityLog
 │                       │                       │              │           │           │
 ├─ POST /iam/aplikasi ─►│                       │              │           │           │
 │                       ├─ create($validated) ──┼─► INSERT app │           │           │
 │                       ├─ generateAndStore($app)──►           │           │           │
 │                       │                       ├─ generateApiCredentials()│           │
 │                       │                       ├─ UPDATE key+hash ──►     │           │
 │                       │                       ├─ Cache::put(key, plain, 15m) ─►      │
 │                       │                       ├─ activity('iam_audit')─► │           │
 │                       │                       │  ->event('secret.created')─────────► │
 │                       │◄── return $plaintext ─┤              │           │           │
 │◄─ 302 redirect show ──┤  with('api_secret_once', $plain)     │           │           │
 │                                                                                      │
 ├─ GET /iam/aplikasi/{id}                                                              │
 │                       │ (HandleInertiaRequests share)                                │
 │                       │  flash.api_secret_once = $plain (1x baca, hilang setelah)    │
 │◄─ Inertia render ─────┤                                                              │
 │   useEffect → modal tampil dengan secret                                             │
```

### 3.3 Sequence: Recovery (modal accidentally tertutup)

```
User             Show page             IamSecretRecoveryController    IamSecretService     Cache
 │                  │                          │                            │                │
 │ Badge "Secret bisa dipulihkan (12 menit lagi)" tampil di Tab Info        │                │
 │ ─ Klik "Tampilkan Ulang Secret" ─►                                       │                │
 │                  ├─ POST /iam/aplikasi/{id}/recover-secret ─►            │                │
 │                  │                          ├─ recoverFromCache($app) ──►│                │
 │                  │                          │                            ├─ Cache::get ──►│
 │                  │                          │                            │◄─── plaintext ─┤
 │                  │                          │                            ├─ activity()    │
 │                  │                          │                            │  ->event('secret.
 │                  │                          │                            │  recovery_viewed')
 │                  │                          │◄─── plaintext ─────────────┤                │
 │                  │◄── back()->with('api_secret_once', $plain) ───────────┤                │
 │ Modal tampil ulang dengan secret (cache TIDAK hilang, masih bisa lihat lagi)
```

### 3.4 Sequence: Acknowledge

```
User             ApiSecretModal          IamSecretRecoveryController     IamSecretService    Cache
 │                  │                          │                              │                │
 │ ─ Tombol "Saya sudah simpan" di modal ─►    │                              │                │
 │                  ├─ POST /iam/aplikasi/{id}/acknowledge-secret ─►          │                │
 │                  │                          ├─ invalidateRecovery($app) ──►│                │
 │                  │                          │                              ├─ Cache::forget─►
 │                  │                          │                              ├─ activity()    │
 │                  │                          │                              │  ->event('secret.
 │                  │                          │                              │   recovery_acknowledged')
 │                  │◄── back ─────────────────┤                              │                │
 │ Modal close, badge "Secret bisa dipulihkan" HILANG di list/show
```

### 3.5 State Machine Secret per Aplikasi

```
                  ┌─────────────────┐
                  │  NEVER_GENERATED │  (aplikasi baru dibuat, sebelum store selesai)
                  └────────┬─────────┘
                           │ generateAndStore()
                           ▼
                  ┌─────────────────┐
              ┌──►│  RECOVERABLE     │  (cache hit, badge visible, modal bisa di-recover)
              │   └────┬─────────┬────┘
              │        │ TTL 15m │ user klik "Saya sudah simpan"
              │        │ expire  │ → invalidateRecovery()
              │        ▼         ▼
              │   ┌─────────────────┐
              │   │   COMMITTED      │  (cache miss, no recovery, hanya HMAC verify
              │   │   (steady state) │   yang masih jalan dari DB Crypt)
              │   └────────┬─────────┘
              │            │ user klik "Regenerasi Key"
              └────────────┘ → regenerate() (state kembali RECOVERABLE dengan secret baru)
```

**Catatan**: HMAC verification tetap jalan di state COMMITTED karena server pakai `Crypt::decryptString($app->api_secret_hash)` yang sumbernya DB, bukan cache. Cache hanya layer recovery, bukan operational. Penting agar kehilangan cache tidak break service.

---

## 4. Service API & Contracts

### 4.1 `IamSecretService` Contract

```php
<?php

namespace App\Services\Iam;

use App\Models\IamApplication;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;

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
    public function generateAndStore(IamApplication $app, ?Request $request = null): string;

    /**
     * Rotasi kredensial — invalidate yang lama, generate yang baru.
     * Cache recovery secret lama otomatis ter-overwrite.
     */
    public function regenerate(IamApplication $app, ?Request $request = null): string;

    /**
     * Coba ambil plaintext dari cache recovery.
     * Return null kalau cache miss (sudah expired / acknowledged).
     * Trigger audit log event 'secret.recovery_viewed' kalau hit.
     */
    public function recoverFromCache(IamApplication $app, ?Request $request = null): ?string;

    /**
     * Hapus cache recovery secara eksplisit (user klik "Saya sudah simpan").
     * Idempotent: aman dipanggil walau cache sudah kosong.
     */
    public function invalidateRecovery(IamApplication $app, ?Request $request = null): void;

    /**
     * Cek apakah aplikasi punya secret yang masih bisa di-recover.
     * Untuk dipakai oleh UI badge dan props Inertia.
     */
    public function hasRecoverableSecret(IamApplication $app): bool;

    /**
     * Ambil sisa waktu recovery dalam detik (untuk countdown di UI).
     * Return 0 kalau tidak ada.
     */
    public function getRecoveryTtlSeconds(IamApplication $app): int;
}
```

### 4.2 Audit Event Catalog

Semua event ditulis ke `activity_log` dengan `log_name='iam_audit'`. Schema property minimum:

| Event | Trigger | Properties JSON | Subject | Causer |
|---|---|---|---|---|
| `secret.created` | App baru dibuat | `{ip, user_agent, app_slug}` | IamApplication | User (admin) |
| `secret.regenerated` | Tombol regenerasi | `{ip, user_agent, app_slug, previous_key_prefix}` | IamApplication | User (admin) |
| `secret.recovery_viewed` | Klik "Tampilkan Ulang" | `{ip, user_agent, app_slug, ttl_remaining_seconds}` | IamApplication | User (admin) |
| `secret.recovery_acknowledged` | Klik "Saya sudah simpan" | `{ip, user_agent, app_slug}` | IamApplication | User (admin) |
| `hmac.verification_failed` | Middleware reject | `{ip, user_agent, path, method, reason, received_timestamp}` | IamApplication (or null) | null |

**Catatan**:
- `previous_key_prefix` di event `regenerated` sengaja hanya simpan 4 char pertama (e.g. `iam_abcd...`) — cukup untuk forensik tanpa expose key tua secara penuh.
- HMAC failure tanpa causer karena permintaan invalid bisa dari aktor anonim. Subject diisi `IamApplication` kalau `api_key` yang dikirim resolve ke aplikasi valid; null kalau api_key tidak terdaftar.
- `reason` di HMAC failure pakai enum string: `"missing_header"|"invalid_timestamp"|"app_not_found"|"signature_mismatch"|"replay_detected"`.

### 4.3 Rate Limiter Definition

Di `app/Providers/AppServiceProvider.php::boot()`:

```php
RateLimiter::for('iam-regenerate', function (Request $request) {
    return Limit::perHour(5)
        ->by($request->user()?->id ?: $request->ip())
        ->response(function (Request $request, array $headers) {
            return back()->with(
                'error',
                'Anda telah melampaui batas regenerasi kunci (5 per jam). Silakan coba lagi nanti.'
            );
        });
});
```

### 4.4 Route Definitions

```php
Route::middleware(['auth', 'verified'])->prefix('iam')->name('iam.')->group(function () {
    // Existing routes ...

    Route::prefix('aplikasi/{aplikasi}')->name('aplikasi.')->group(function () {
        Route::post('regenerate-key', [AplikasiController::class, 'regenerateKey'])
            ->middleware('throttle:iam-regenerate')   // ← rate limit baru
            ->name('regenerate-key');

        Route::post('recover-secret', [IamSecretRecoveryController::class, 'show'])
            ->name('recover-secret');                  // ← endpoint baru

        Route::post('acknowledge-secret', [IamSecretRecoveryController::class, 'acknowledge'])
            ->name('acknowledge-secret');              // ← endpoint baru
    });
});
```

### 4.5 Frontend Props Contract

`HandleInertiaRequests::share()` akan menambahkan:

```php
'flash' => [
    'success'         => $request->session()->get('success'),
    'error'           => $request->session()->get('error'),
    'api_secret_once' => $request->session()->get('api_secret_once'),  // ← FIX utama
],
```

Halaman `show` dan `index` akan menerima props baru lewat controller. `IamSecretService` di-inject via method parameter (auto-resolved oleh Laravel container):

```php
// show(IamApplication $aplikasi, IamPermissionAuditor $auditor, IamSecretService $secretService)
return inertia('iam/aplikasi/show', [
    'aplikasi' => $aplikasiArray,
    'permission_audit' => [...],
    'recovery_status' => [
        'recoverable'         => $secretService->hasRecoverableSecret($aplikasi),
        'ttl_remaining_secs'  => $secretService->getRecoveryTtlSeconds($aplikasi),
    ],
]);

// index(IamSecretService $secretService)
$aplikasi = IamApplication::withCount('roles')->latest()->get()->map(function ($app) use ($secretService) {
    $app->api_key_display      = $this->maskApiKey($app->api_key);
    $app->secret_recoverable   = $secretService->hasRecoverableSecret($app);
    unset($app->api_key);
    return $app;
});
```

---

## 5. UI/UX Detail

### 5.1 ApiSecretModal — Final Layout

```
┌─────────────────────────────────────────────────────────────┐
│  API Secret Baru                                       [×]  │
├─────────────────────────────────────────────────────────────┤
│  ⚠️  PENTING — Simpan secret ini sekarang.                  │
│      Setelah 15 menit, secret tidak bisa ditampilkan        │
│      lagi (kecuali regenerasi).                             │
│                                                              │
│  ┌────────────────────────────────────────────────┬──────┐  │
│  │ SecREt_PLAINTEXT_64_CHARS_AAAAAAAAAAAAAAAAAA…  │ Salin│  │
│  └────────────────────────────────────────────────┴──────┘  │
│                                                              │
│  ⏱  Bisa dipulihkan selama 14 menit 32 detik                │
│                                                              │
│  ┌────────────────┐       ┌─────────────────────────────┐   │
│  │ Tutup (tetap   │       │ ✓ Saya sudah simpan         │   │
│  │ bisa recovery) │       │   (hapus dari cache)        │   │
│  └────────────────┘       └─────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

**Behavior**:
- Tombol "Salin" → copy plaintext + toast "Tersalin!" 2 detik.
- Tombol "Tutup" → modal close, cache TIDAK invalidate (state RECOVERABLE).
- Tombol "Saya sudah simpan" → POST `/acknowledge-secret` (Inertia `router.post`) → cache.forget di backend → modal close → toast "Cache recovery dihapus" → Inertia partial reload `router.reload({ only: ['recovery_status', 'aplikasi'] })` untuk refresh props tanpa full page reload.
- Countdown timer di footer modal (live update setiap detik, hitung dari `ttl_remaining_secs`).
- Klik backdrop / Escape → konfirmasi dialog "Tutup tanpa menyimpan?" jika belum klik "Salin" atau "Saya sudah simpan".

### 5.2 Tab Info — Section "API Key" Update

```
┌─────────────────────────────────────────────────────────────┐
│  API Key                                                    │
│  ┌──────────────────────────────────────┐  ┌──────┐         │
│  │ iam_********************abcd1234     │  │ Salin│         │
│  └──────────────────────────────────────┘  └──────┘         │
│                                                              │
│  ┌─ 🟡 Secret bisa dipulihkan ───────────────────────────┐  │
│  │ Sisa waktu: 12 menit 04 detik                         │  │
│  │ ┌─────────────────────┐  ┌──────────────────────────┐ │  │
│  │ │ 🔓 Tampilkan Ulang  │  │ ✓ Saya sudah simpan      │ │  │
│  │ └─────────────────────┘  └──────────────────────────┘ │  │
│  └────────────────────────────────────────────────────────┘ │
│  (Block kuning di atas HANYA muncul jika recovery_status.   │
│   recoverable === true)                                     │
│                                                              │
│  Regenerasi API Key                                         │
│  ┌─────────────────────────┐                                │
│  │ 🔑 Regenerasi Key       │                                │
│  └─────────────────────────┘                                │
│  Perlu regenerasi jika API key tercompromise. Secret baru   │
│  akan ditampilkan setelah regenerasi.                       │
└─────────────────────────────────────────────────────────────┘
```

### 5.3 List Aplikasi — Badge Recovery

Di `index.tsx`, kolom Status tambah badge:
```
| Nama         | Slug      | API Key            | Status                   | Aksi  |
| Sistem Cuti  | cuti      | iam_****1234       | Aktif 🟡 Recoverable     | Edit  |
| eKinerja     | ekinerja  | iam_****abcd       | Aktif                    | Edit  |
```

Klik badge `🟡 Recoverable` → navigate ke halaman show aplikasi (`/iam/aplikasi/{id}`). Tab Info **tidak** otomatis aktif (defaultValue tab adalah "roles" di implementasi existing — deep-link ke tab tertentu di luar scope spec ini).

---

## 6. Testing Strategy

Mengikuti TDD workflow (RED → GREEN → REFACTOR) dari CLAUDE.md. Pakai **Pest 4.4.2**.

### 6.1 Unit Tests (Service)
File: `tests/Unit/Services/Iam/IamSecretServiceTest.php`

```
✓ generateAndStore creates credentials, stores hash in DB, caches plaintext, logs activity
✓ generateAndStore returns plaintext that matches what's cached
✓ regenerate overwrites existing credentials and old cache key
✓ regenerate logs previous_key_prefix
✓ recoverFromCache returns plaintext when cache hit, logs viewed event
✓ recoverFromCache returns null when cache miss, no log
✓ invalidateRecovery removes cache, logs acknowledged event
✓ invalidateRecovery is idempotent when cache already empty
✓ hasRecoverableSecret returns true/false correctly
✓ getRecoveryTtlSeconds returns positive int when cache exists, 0 when miss
```

### 6.2 Feature Tests
File: `tests/Feature/Iam/IamSecretRecoveryTest.php`

```
✓ POST regenerate-key flashes api_secret_once and caches plaintext
✓ POST regenerate-key respects rate limit (5/hour per user, 6th gets 429)
✓ GET show page exposes recovery_status props correctly
✓ POST recover-secret returns plaintext via flash when within TTL
✓ POST recover-secret returns null flash when cache expired
✓ POST acknowledge-secret forgets cache and confirms via flash
✓ Activity log entries created for each event with correct properties
✓ HMAC verification failure logs 'hmac.verification_failed' event
✓ Non-admin user cannot access regenerate-key (403)
✓ System app (is_system=true) cannot regenerate (403)
```

File: `tests/Feature/Iam/HandleInertiaRequestsFlashTest.php`

```
✓ flash.api_secret_once is exposed to Inertia props
✓ flash.success and flash.error still work (regression)
```

### 6.3 Manual Smoke Checklist

- [ ] Buat aplikasi baru → modal tampil dengan secret.
- [ ] Tutup modal → reload page → tab Info → klik "Tampilkan Ulang" → modal tampil lagi.
- [ ] Klik "Saya sudah simpan" → reload → block kuning hilang.
- [ ] Tunggu >15 menit → block kuning hilang otomatis.
- [ ] Regenerate 6 kali → request ke-6 dapat error rate limit.
- [ ] Buka tabel `activity_log` → ada 4 jenis event sesuai aksi.
- [ ] Aplikasi client kirim signature salah → ada entry `hmac.verification_failed`.

### 6.4 Acceptance Criteria

Modul ini dianggap selesai jika:

1. **Functional**: User membuat aplikasi baru → modal tampil otomatis dengan plaintext. Tutup modal, kembali ke show page → ada block kuning, klik "Tampilkan Ulang" → modal tampil dengan plaintext yang sama. Klik "Saya sudah simpan" → block hilang.
2. **Security**: Plaintext tidak ada di response body manapun selain Inertia flash. Cache key tidak bisa di-guess (pakai ULID). Rate limit 5/jam aktif. Endpoint `recover-secret` & `acknowledge-secret` butuh auth.
3. **Audit**: Setiap event (4 jenis + HMAC failure) ter-record di `activity_log` dengan properties lengkap.
4. **Backward compat**: HMAC verification dari aplikasi client existing **tetap jalan tanpa perubahan**. Crypt::decryptString tetap pakai DB.
5. **Test coverage**: ≥90% line coverage di `IamSecretService` + semua feature test pass.

---

## 7. Implementation Order

```
PHASE 1 — Foundation (sequential, satu PR/branch)
─────────────────────────────────────────────────
1. Test: tulis tests/Unit/Services/Iam/IamSecretServiceTest.php (RED)
2. Implement: app/Services/Iam/IamSecretService.php (GREEN)
3. Refactor: ekstrak konstanta, type hint, namespace cleanup
   ✓ Checkpoint 1: service standalone teruji, belum ada controller change

PHASE 2 — Wiring Controllers & Middleware
─────────────────────────────────────────────────
4. Test: tests/Feature/Iam/HandleInertiaRequestsFlashTest.php (RED)
5. Implement: tambah api_secret_once ke flash share (GREEN)
   ↓ MINIMAL FIX SUDAH MASUK SAMPAI SINI — flow create/regenerate sudah balance ke modal
6. Test: tests/Feature/Iam/IamSecretRecoveryTest.php tests untuk store & regenerate (RED)
7. Refactor: AplikasiController inject IamSecretService, tipiskan store & regenerateKey (GREEN)
   ✓ Checkpoint 2: existing flow sudah pakai service, audit log ter-create

PHASE 3 — Recovery Endpoints
─────────────────────────────────────────────────
8. Test: feature test untuk recover-secret & acknowledge-secret (RED)
9. Implement: IamSecretRecoveryController + 2 method (GREEN)
10. Wire route: POST recover-secret, POST acknowledge-secret di web.php
    ✓ Checkpoint 3: backend recovery flow lengkap

PHASE 4 — Rate Limiting & HMAC Audit
─────────────────────────────────────────────────
11. Test: rate limit feature test (RED — 6th request gets 429)
12. Implement: RateLimiter::for('iam-regenerate', ...) di AppServiceProvider
13. Wire: route regenerate-key + throttle:iam-regenerate
14. Test: HMAC failure logging test (RED)
15. Implement: VerifyIamSignature middleware tambah audit log on failure (GREEN)
    ✓ Checkpoint 4: full backend selesai, semua test pass

PHASE 5 — Frontend Integration
─────────────────────────────────────────────────
16. Update show.tsx: tambah handler recovery & acknowledge, block kuning UI
17. Update index.tsx: handle flash + badge "Recoverable"
18. Update ApiSecretModal.tsx: tombol "Saya sudah simpan" + countdown timer
19. Type updates: resources/js/types/iam.ts (recovery_status, secret_recoverable)
20. Smoke test manual (acceptance criteria 1-3)
    ✓ Checkpoint 5: full feature siap untuk code review

PHASE 6 — Documentation & Polish
─────────────────────────────────────────────────
21. Buat docs/iam/secret-management.md (lifecycle secret, recovery flow, audit query examples)
22. Add inline doc comments di service (Bahasa Indonesia per CLAUDE.md)
23. Self-code-review: cek tidak ada plaintext leak, error message lokalisasi
24. Final test run: pest + manual smoke
```

**Phase 2 step 5 adalah "minimum coherent fix"**: kalau diperlukan deploy mendesak (mis. ada admin yang butuh generate secret hari ini), bisa ship sampai step 5 saja. Tambahan dari step 6 dst murni enhancement.

---

## 8. Edge Cases & Risk Register

### 8.1 Edge Cases

| # | Skenario | Behavior | Justifikasi |
|---|---|---|---|
| 1 | User regenerate saat masih ada cache recovery | Overwrite cache dengan plaintext baru, log event `regenerated` (bukan `recovery_acknowledged`) | Regenerate adalah authoritative action, cache lama otomatis tergantikan |
| 2 | Cache driver `database` fail (table dropped, dll) | Service wrap `Cache::put` di try-catch (`\Throwable`), log warning ke Laravel log, lanjut. Secret tetap ter-save di DB Crypt, modal `api_secret_once` tetap tampil sekali via flash. Recovery & badge tidak tersedia (degraded mode) | Flash adalah path utama; cache hanya enhancement recovery. Tidak boleh block flow utama |
| 3 | User akses `/recover-secret` setelah TTL expire | Controller return `back()->with('error', 'Secret sudah tidak bisa dipulihkan. Silakan regenerasi.')` | Tidak silent fail; user perlu tahu kenapa modal kosong |
| 4 | Concurrent regenerate dari 2 tab admin | Last write wins di DB (model save), cache key ter-overwrite. Activity log mencatat 2 event terpisah | Acceptable — admin tindakan sengaja, audit trail jelas |
| 5 | Aplikasi `is_system=true` | Endpoint regenerate-key & recover-secret return 403 via `abort_if` (sama pattern existing edit/destroy) | Konsisten dengan policy existing |
| 6 | User tidak login (session expired saat acknowledge) | Middleware `auth` redirect ke login. Frontend handle 401 dengan toast | Standard Laravel flow |
| 7 | Rate limit hit saat 6th regenerate | `back()->with('error', '...')` dengan kode 429 di response object. Frontend dapat error toast | Tidak crash, jelas ke admin batasnya |
| 8 | App di-delete saat cache masih hidup | Cache key orphan, expire natural setelah 15 menit. Tidak ada side effect | YAGNI — tidak perlu cleanup hook |
| 9 | Plaintext leak ke logs | `Activitylog\Models\Activity` properties JSON **TIDAK** menyimpan plaintext secret, hanya metadata (key_prefix, ip, dll). Plaintext **hanya** di cache + session flash | Audit log harus aman untuk dilihat reviewer non-admin |
| 10 | User klik "Tampilkan Ulang" berkali-kali | Setiap klik log 1 event `recovery_viewed`. Idempotent dari sisi cache (read-only) | Audit trail lengkap untuk forensik |
| 11 | XHR mengirim CSRF token expired | Laravel default 419 page. Frontend Inertia handle dengan reload page (built-in behavior) | Standard Laravel CSRF flow |
| 12 | Database cache table belum di-migrate | `php artisan cache:table && migrate` di README/deploy doc | One-time setup, dokumentasikan |

### 8.2 Risk Register

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| HMAC verification break for existing clients | Low | Critical | Service class approach tidak ubah `Crypt::encryptString`/`Crypt::decryptString` logic di `IamApplication` model; existing HMAC flow protected oleh integration test di `VerifyIamSignature` |
| Plaintext leak via wrong log level | Low | Critical | Audit properties whitelist (test asserts properties JSON tidak mengandung secret) |
| Cache TTL race condition | Low | Low | Service operasi single-key, cache driver atomic untuk read/write tunggal |
| Frontend countdown drift | Medium | Low | Re-fetch TTL dari server tiap mount; akurasi ±5 detik acceptable untuk UX |
| Activity log volume spike | Medium | Medium | HMAC failure terbatas oleh middleware path; per-event punya properties limit alami |
| Rate limit accidentally lock admin | Low | Medium | Rate limit per user_id, admin lain tidak terdampak. Fallback: clear via Tinker |

---

## 9. Open Questions

Tidak ada — semua keputusan sudah di-lock saat brainstorming:

1. ✅ Scope: Fix + redesign keamanan (Crypt-based recovery cache).
2. ✅ Arah: Pertahankan `Crypt::encryptString` + tambah recovery cache (kompatibel HMAC).
3. ✅ Recovery TTL: 15 menit + tombol "Saya sudah simpan".
4. ✅ Audit events: 4 jenis (created, regenerated, recovery_viewed, recovery_acknowledged) + HMAC failure.
5. ✅ Rate limit: 5x/jam per user.
6. ✅ Audit storage: `spatie/laravel-activitylog` v5 dengan `log_name='iam_audit'`.
7. ✅ Implementation approach: Service class (Pendekatan B).

---

## 10. References & Dependencies

- **Packages**: `spatie/laravel-activitylog ^5.0` (sudah terinstall), `laravel/framework ^12.0`, `laravel/sanctum ^4.3`.
- **Configuration**: `CACHE_STORE=database` (default project — `config/cache.php:18`).
- **Existing modules touched**: `IamApplication` model (tidak diubah), `VerifyIamSignature` middleware (tambah audit), `HandleInertiaRequests` middleware (tambah flash key).
- **Related specs**:
  - `2026-03-21-iam-sso-design.md` — definisi awal IAM Hub & SSO
  - `2026-05-16-iam-informative-design.md` — UX improvement form permission

---

**End of design document.**
