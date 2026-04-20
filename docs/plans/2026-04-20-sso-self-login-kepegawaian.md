# SSO Self-Login Kepegawaian-Apps Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Membuat kepegawaian-apps sendiri melewati alur SSO yang sama dengan aplikasi lain — unauthenticated users diarahkan ke `/sso/login?app=kepegawaian&redirect=...` bukan langsung ke `/login`.

**Architecture:** Ada 3 perubahan inti: (1) middleware redirect ke SSO login bukan langsung ke `/login`, (2) custom `LoginResponse` yang mendeteksi SSO session dan redirect ke `/sso/callback`, (3) `SsoController@callback` yang mendeteksi jika app adalah kepegawaian sendiri dan skip code generation — langsung redirect ke URL tujuan.

**Tech Stack:** Laravel 12, Fortify v1, Inertia.js v2, Sanctum v4, Pest v4

---

## Konteks Codebase

- `app/Http/Middleware/EnsurePermission.php` — middleware lama (masih ada, redirect ke `route('login')`)
- `app/Http/Middleware/VerifyIamPermission.php` — middleware utama IAM, redirect ke `route('login')`
- `app/Http/Controllers/SsoController.php` — SSO provider controller
- `app/Providers/FortifyServiceProvider.php` — konfigurasi Fortify actions & views
- `config/fortify.php` — `home => '/dashboard'`, `username => 'nip'`
- `config/iam.php` — `app_slug => env('IAM_APP_SLUG', 'kepegawaian')`
- `IamApplication` slug `kepegawaian` — sudah ada di DB
- Fortify default `LoginResponse` menggunakan `redirect()->intended(Fortify::redirects('login'))`

---

## Task 1: Custom LoginResponse — redirect ke SSO callback jika ada SSO session

**Files:**
- Create: `app/Http/Responses/SsoAwareLoginResponse.php`
- Modify: `app/Providers/FortifyServiceProvider.php`
- Test: `tests/Feature/Auth/SsoAwareLoginResponseTest.php`

### Step 1: Tulis failing test

```php
// tests/Feature/Auth/SsoAwareLoginResponseTest.php
<?php

use App\Models\Pegawai;
use Illuminate\Support\Facades\Hash;

test('setelah login, redirect ke sso callback jika ada sso session', function () {
    $pegawai = Pegawai::factory()->create([
        'password' => Hash::make('password'),
    ]);

    session(['sso_app' => 'kepegawaian', 'sso_redirect' => 'http://localhost/dashboard']);

    $response = $this->post('/login', [
        'nip' => $pegawai->nip,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('sso.callback'));
});

test('setelah login tanpa sso session, redirect ke dashboard seperti biasa', function () {
    $pegawai = Pegawai::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $response = $this->post('/login', [
        'nip' => $pegawai->nip,
        'password' => 'password',
    ]);

    $response->assertRedirect('/dashboard');
});
```

### Step 2: Jalankan test — pastikan FAIL

```bash
./vendor/bin/pest --filter "SsoAwareLoginResponseTest" --compact
```

Expected: 2 FAIL (class tidak ada)

### Step 3: Buat `SsoAwareLoginResponse`

```php
// app/Http/Responses/SsoAwareLoginResponse.php
<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class SsoAwareLoginResponse implements LoginResponseContract
{
    /**
     * Redirect ke sso.callback jika ada SSO session aktif,
     * jika tidak redirect ke intended URL atau dashboard.
     */
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        // Jika ada SSO session (dari /sso/login), teruskan ke callback
        if (session()->has('sso_app')) {
            return redirect()->route('sso.callback');
        }

        return redirect()->intended(Fortify::redirects('login'));
    }
}
```

### Step 4: Bind di FortifyServiceProvider

Tambahkan di method `register()`:

```php
// app/Providers/FortifyServiceProvider.php
use App\Http\Responses\SsoAwareLoginResponse;
use Laravel\Fortify\Contracts\LoginResponse;

public function register(): void
{
    $this->app->singleton(LoginResponse::class, SsoAwareLoginResponse::class);
}
```

### Step 5: Jalankan test — pastikan PASS

```bash
./vendor/bin/pest --filter "SsoAwareLoginResponseTest" --compact
```

Expected: 2 PASS

### Step 6: Commit

```bash
git add app/Http/Responses/SsoAwareLoginResponse.php \
        app/Providers/FortifyServiceProvider.php \
        tests/Feature/Auth/SsoAwareLoginResponseTest.php
git commit -m "feat(sso): custom LoginResponse yang redirect ke sso.callback jika ada SSO session"
```

---

## Task 2: SsoController@callback — bypass code generation untuk kepegawaian sendiri

**Files:**
- Modify: `app/Http/Controllers/SsoController.php`
- Test: `tests/Feature/Auth/SsoCallbackSelfTest.php`

### Step 1: Tulis failing test

```php
// tests/Feature/Auth/SsoCallbackSelfTest.php
<?php

use App\Models\IamApplication;
use App\Models\Pegawai;

test('sso callback untuk kepegawaian sendiri redirect langsung tanpa code', function () {
    $pegawai = Pegawai::factory()->create();
    $redirectUrl = 'http://localhost/dashboard';

    session(['sso_app' => 'kepegawaian', 'sso_redirect' => $redirectUrl]);

    $response = $this->actingAs($pegawai)->get(route('sso.callback'));

    // Harus redirect ke URL tujuan langsung — tanpa ?code= apapun
    $response->assertRedirect($redirectUrl);
    $this->assertStringNotContainsString('?code=', $response->headers->get('Location'));
});

test('sso callback untuk aplikasi lain tetap generate code', function () {
    $app = IamApplication::factory()->create([
        'slug' => 'test-app',
        'url' => 'http://test-app.local',
        'is_active' => true,
    ]);

    $pegawai = Pegawai::factory()->create();
    $redirectUrl = 'http://test-app.local/callback';

    session(['sso_app' => 'test-app', 'sso_redirect' => $redirectUrl]);

    $response = $this->actingAs($pegawai)->get(route('sso.callback'));

    // Harus redirect dengan ?code=
    $location = $response->headers->get('Location');
    $this->assertStringContainsString('?code=', $location);
});
```

### Step 2: Jalankan test — pastikan FAIL

```bash
./vendor/bin/pest --filter "SsoCallbackSelfTest" --compact
```

Expected: kedua test FAIL (logic belum diubah)

### Step 3: Modifikasi `SsoController@callback`

Tambahkan deteksi self-app sebelum `generateCodeAndRedirect`:

```php
// app/Http/Controllers/SsoController.php

/** Dipanggil setelah login berhasil jika ada SSO session */
public function callback(Request $request): RedirectResponse
{
    $appSlug  = session()->pull('sso_app');
    $redirect = session()->pull('sso_redirect');

    if (! $appSlug || ! $redirect) {
        return redirect()->route('dashboard');
    }

    // Kepegawaian adalah provider-nya sendiri — skip code generation,
    // langsung redirect ke URL tujuan (session sudah terbuat oleh Fortify)
    $selfSlug = config('iam.app_slug', 'kepegawaian');
    if ($appSlug === $selfSlug) {
        return redirect($redirect);
    }

    $app = IamApplication::where('slug', $appSlug)->where('is_active', true)->first();

    if (! $app) {
        return redirect()->route('dashboard');
    }

    return $this->generateCodeAndRedirect($request->user()->id, $app, $redirect);
}
```

### Step 4: Jalankan test — pastikan PASS

```bash
./vendor/bin/pest --filter "SsoCallbackSelfTest" --compact
```

Expected: 2 PASS

### Step 5: Commit

```bash
git add app/Http/Controllers/SsoController.php \
        tests/Feature/Auth/SsoCallbackSelfTest.php
git commit -m "feat(sso): bypass code generation untuk kepegawaian self-callback"
```

---

## Task 3: Middleware — redirect ke /sso/login bukan langsung ke /login

**Files:**
- Modify: `app/Http/Middleware/VerifyIamPermission.php`
- Modify: `app/Http/Middleware/EnsurePermission.php`
- Test: `tests/Feature/Auth/SsoMiddlewareRedirectTest.php`

### Step 1: Tulis failing test

```php
// tests/Feature/Auth/SsoMiddlewareRedirectTest.php
<?php

test('unauthenticated user diarahkan ke sso login bukan langsung ke login', function () {
    $response = $this->get('/dashboard');

    // Harus ke /sso/login bukan /login
    $response->assertRedirectContains('/sso/login');
    $response->assertRedirectContains('app=kepegawaian');
});

test('sso login redirect membawa parameter redirect yang benar', function () {
    $response = $this->get('/dashboard');

    $location = $response->headers->get('Location');
    $this->assertStringContainsString('redirect=', $location);
});
```

### Step 2: Jalankan test — pastikan FAIL

```bash
./vendor/bin/pest --filter "SsoMiddlewareRedirectTest" --compact
```

Expected: 2 FAIL (masih redirect ke /login)

### Step 3: Modifikasi `VerifyIamPermission`

Ganti `redirect()->route('login')` dengan SSO redirect:

```php
// app/Http/Middleware/VerifyIamPermission.php

if ($user === null) {
    if ($request->expectsJson()) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    return redirect()->to(
        route('sso.login', [
            'app'      => config('iam.app_slug', 'kepegawaian'),
            'redirect' => $request->url(),
        ])
    );
}
```

### Step 4: Modifikasi `EnsurePermission`

Ganti `redirect()->route('login')` dengan SSO redirect:

```php
// app/Http/Middleware/EnsurePermission.php

if ($user === null) {
    if ($request->expectsJson()) {
        abort(Response::HTTP_UNAUTHORIZED);
    }

    return redirect()->to(
        route('sso.login', [
            'app'      => config('iam.app_slug', 'kepegawaian'),
            'redirect' => $request->url(),
        ])
    );
}
```

### Step 5: Tangani redirect dari Laravel default `auth` middleware

Laravel juga punya middleware `auth` bawaan yang redirect ke `route('login')`. Cek apakah ada route yang pakai `middleware('auth')` langsung:

```bash
grep -n "middleware.*'auth'" routes/web.php
```

Jika ada, kita perlu override `unauthenticated()` di `app/Exceptions/Handler.php` (atau `bootstrap/app.php`) untuk redirect ke SSO:

```php
// bootstrap/app.php — tambahkan di withExceptions()
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderHttpExceptions(false);

    $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return redirect()->to(
            route('sso.login', [
                'app'      => config('iam.app_slug', 'kepegawaian'),
                'redirect' => $request->url(),
            ])
        );
    });
})
```

### Step 6: Jalankan test — pastikan PASS

```bash
./vendor/bin/pest --filter "SsoMiddlewareRedirectTest" --compact
```

Expected: 2 PASS

### Step 7: Commit

```bash
git add app/Http/Middleware/VerifyIamPermission.php \
        app/Http/Middleware/EnsurePermission.php \
        bootstrap/app.php \
        tests/Feature/Auth/SsoMiddlewareRedirectTest.php
git commit -m "feat(sso): middleware redirect unauthenticated ke sso.login bukan langsung ke login"
```

---

## Task 4: Validasi — jalankan full test suite

### Step 1: Jalankan semua test

```bash
./vendor/bin/pest --compact
```

Expected: semua PASS (390+ tests)

### Step 2: Cek tidak ada regresi pada alur SSO aplikasi lain

```bash
./vendor/bin/pest --filter "Sso" --compact
```

### Step 3: Jika ada test yang FAIL

Identify root cause, fix, commit dengan message `fix: ...`

### Step 4: Push ke remote

```bash
git push origin main
```

---

## Verifikasi Manual (opsional)

Setelah implementasi, verifikasi di browser:

1. Buka `http://localhost/dashboard` tanpa login
2. Seharusnya redirect ke `http://localhost/sso/login?app=kepegawaian&redirect=http://localhost/dashboard`
3. Kemudian redirect ke `http://localhost/login`
4. Login dengan NIP + password
5. Setelah login → redirect ke `http://localhost/sso/callback`
6. Callback → redirect ke `http://localhost/dashboard` (tanpa `?code=`)
7. ✅ Berhasil masuk ke dashboard
