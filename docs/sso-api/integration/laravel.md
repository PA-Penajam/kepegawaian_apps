# Integrasi Laravel

Panduan ini menjelaskan cara mengintegrasikan aplikasi Laravel dengan SSO kepegawaian-apps.

---

## 1. Konfigurasi Environment

Tambahkan ke `.env`:

```env
IAM_URL=https://kepegawaian.pa-penajam.go.id
IAM_API_KEY=iam_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
IAM_API_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
IAM_APP_SLUG=nama-aplikasi-anda
```

Buat `config/iam.php`:

```php
<?php

return [
    'url'        => env('IAM_URL'),
    'api_key'    => env('IAM_API_KEY'),
    'api_secret' => env('IAM_API_SECRET'),
    'app_slug'   => env('IAM_APP_SLUG'),
];
```

---

## 2. Helper: Hitung HMAC Signature

Buat `app/Services/IamSignatureService.php`:

```php
<?php

namespace App\Services;

class IamSignatureService
{
    public function buildHeaders(string $method, string $path, array $query = [], string $body = ''): array
    {
        $timestamp   = (string) time();
        $sortedQuery = http_build_query(collect($query)->sortKeys()->all());
        $bodyHash    = hash('sha256', $body);

        $payload = strtoupper($method)
            . ':' . $path
            . ':' . $sortedQuery
            . ':' . $bodyHash
            . ':' . $timestamp;

        $signature = hash_hmac('sha256', $payload, config('iam.api_secret'));

        return [
            'X-App-Key'   => config('iam.api_key'),
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
        ];
    }
}
```

---

## 3. Middleware: Protect Route

Buat `app/Http/Middleware/VerifyIamSession.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Services\IamSignatureService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class VerifyIamSession
{
    public function __construct(private IamSignatureService $signer) {}

    public function handle(Request $request, Closure $next, string $permission = null)
    {
        $token = session('iam_token');

        if (! $token) {
            return $this->redirectToSso($request);
        }

        $data = $this->fetchIamData($token);

        if (! $data || ! isset($data['user'])) {
            session()->forget('iam_token');
            return $this->redirectToSso($request);
        }

        if ($permission && ! in_array($permission, $data['permissions'] ?? [], true)) {
            abort(403, 'Akses ditolak.');
        }

        // Inject ke request agar bisa diakses di controller
        $request->merge([
            'iam_user'        => $data['user'],
            'iam_roles'       => $data['roles'],
            'iam_permissions' => $data['permissions'],
        ]);

        return $next($request);
    }

    private function fetchIamData(string $token): ?array
    {
        $cacheKey = 'iam_' . hash('sha256', $token . config('iam.api_key'));

        return Cache::remember($cacheKey, 60, function () use ($token) {
            $path    = '/api/v1/iam/validate';
            $headers = $this->signer->buildHeaders('GET', $path);

            $response = Http::withToken($token)
                ->withHeaders($headers)
                ->get(config('iam.url') . $path);

            return $response->ok() ? $response->json() : null;
        });
    }

    private function redirectToSso(Request $request)
    {
        $callback = route('sso.callback');
        $url      = config('iam.url') . '/sso/login'
            . '?app=' . config('iam.app_slug')
            . '&redirect=' . urlencode($callback);

        return redirect($url);
    }
}
```

Daftarkan di `bootstrap/app.php` atau `app/Http/Kernel.php`:

```php
// bootstrap/app.php (Laravel 11+)
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'iam' => \App\Http\Middleware\VerifyIamSession::class,
    ]);
})
```

---

## 4. Route SSO Callback

Tambahkan route di `routes/web.php`:

```php
use App\Http\Controllers\SsoCallbackController;

Route::get('/sso/callback', [SsoCallbackController::class, 'handle'])->name('sso.callback');
```

Buat `app/Http/Controllers/SsoCallbackController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\IamSignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SsoCallbackController extends Controller
{
    public function __construct(private IamSignatureService $signer) {}

    public function handle(Request $request)
    {
        $code = $request->query('code');

        if (! $code || strlen($code) !== 64) {
            abort(400, 'SSO code tidak valid.');
        }

        $path    = '/api/v1/iam/exchange-code';
        $body    = json_encode(['code' => $code]);
        $headers = $this->signer->buildHeaders('POST', $path, [], $body);

        $response = Http::withHeaders($headers)
            ->withBody($body, 'application/json')
            ->post(config('iam.url') . $path);

        if (! $response->ok()) {
            abort(401, 'SSO code tidak valid atau sudah kadaluarsa.');
        }

        $data = $response->json();

        // Simpan token di session server-side (BUKAN cookie atau localStorage)
        session([
            'iam_token'      => $data['token'],
            'iam_expires_at' => $data['expires_at'],
        ]);

        return redirect()->intended('/dashboard');
    }
}
```

---

## 5. Penggunaan di Route

```php
// Protect seluruh group route
Route::middleware('iam')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/rekap', [RekapController::class, 'index']);
});

// Protect dengan cek permission spesifik
Route::middleware('iam:absensi:create')->post('/absensi', [AbsensiController::class, 'store']);
```

---

## 6. Akses Data User di Controller

```php
public function index(Request $request)
{
    $user        = $request->iam_user;        // array: id, name, email, nip
    $roles       = $request->iam_roles;       // array of string
    $permissions = $request->iam_permissions; // array of string

    return view('dashboard', compact('user'));
}
```

---

## 7. Logout

```php
use App\Services\IamSignatureService;
use Illuminate\Support\Facades\Http;

public function logout(Request $request, IamSignatureService $signer)
{
    $token = session('iam_token');

    if ($token) {
        $path    = '/api/v1/iam/logout';
        $headers = $signer->buildHeaders('POST', $path);

        Http::withToken($token)
            ->withHeaders($headers)
            ->post(config('iam.url') . $path);
    }

    session()->forget(['iam_token', 'iam_expires_at']);

    return redirect('/login');
}
```
