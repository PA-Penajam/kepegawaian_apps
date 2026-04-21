# Integrasi CodeIgniter 4

Panduan ini menjelaskan cara mengintegrasikan aplikasi CodeIgniter 4 dengan SSO kepegawaian-apps.

---

## 1. Konfigurasi Environment

Tambahkan ke `.env`:

```env
IAM_URL=https://kepegawaian.pa-penajam.go.id
IAM_API_KEY=iam_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
IAM_API_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
IAM_APP_SLUG=nama-aplikasi-anda
```

Buat `app/Config/Iam.php`:

```php
<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Iam extends BaseConfig
{
    public string $url      = '';
    public string $apiKey   = '';
    public string $secret   = '';
    public string $appSlug  = '';

    public function __construct()
    {
        parent::__construct();

        $this->url     = env('IAM_URL', '');
        $this->apiKey  = env('IAM_API_KEY', '');
        $this->secret  = env('IAM_API_SECRET', '');
        $this->appSlug = env('IAM_APP_SLUG', '');
    }
}
```

---

## 2. Helper: Hitung HMAC Signature

Buat `app/Libraries/IamSignature.php`:

```php
<?php

namespace App\Libraries;

use Config\Iam;

class IamSignature
{
    private Iam $config;

    public function __construct()
    {
        $this->config = config('Iam');
    }

    public function buildHeaders(string $method, string $path, array $query = [], string $body = ''): array
    {
        $timestamp = (string) time();

        // Sort query parameters A-Z
        ksort($query);
        $sortedQuery = http_build_query($query);
        $bodyHash    = hash('sha256', $body);

        $payload = strtoupper($method)
            . ':' . $path
            . ':' . $sortedQuery
            . ':' . $bodyHash
            . ':' . $timestamp;

        $signature = hash_hmac('sha256', $payload, $this->config->secret);

        return [
            'X-App-Key'    => $this->config->apiKey,
            'X-Timestamp'  => $timestamp,
            'X-Signature'  => $signature,
            'Content-Type' => 'application/json',
        ];
    }
}
```

---

## 3. Filter: Protect Route

Buat `app/Filters/IamFilter.php`:

```php
<?php

namespace App\Filters;

use App\Libraries\IamSignature;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Iam;
use Config\Services;

class IamFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = Services::session();
        $token   = $session->get('iam_token');

        if (! $token) {
            return $this->redirectToSso();
        }

        $data = $this->fetchIamData($token);

        if (! $data || ! isset($data['user'])) {
            $session->remove('iam_token');
            return $this->redirectToSso();
        }

        // Cek permission jika diberikan sebagai argumen filter
        $permission = $arguments[0] ?? null;
        if ($permission && ! in_array($permission, $data['permissions'] ?? [], true)) {
            return Services::response()->setStatusCode(403)->setBody('Akses ditolak.');
        }

        // Simpan ke request untuk akses di controller
        $request->iam_user        = $data['user'];
        $request->iam_roles       = $data['roles'];
        $request->iam_permissions = $data['permissions'];
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // tidak diperlukan
    }

    private function fetchIamData(string $token): ?array
    {
        $cache    = Services::cache();
        $cacheKey = 'iam_' . hash('sha256', $token . env('IAM_API_KEY'));
        $cached   = $cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $config  = config('Iam');
        $signer  = new IamSignature();
        $path    = '/api/v1/iam/validate';
        $headers = $signer->buildHeaders('GET', $path);

        $client   = Services::curlrequest();
        $response = $client->request('GET', $config->url . $path, [
            'headers' => array_merge($headers, [
                'Authorization' => 'Bearer ' . $token,
            ]),
        ]);

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $data = json_decode($response->getBody(), true);
        $cache->save($cacheKey, $data, 60);

        return $data;
    }

    private function redirectToSso()
    {
        $config   = config('Iam');
        $callback = base_url('sso/callback');
        $url      = $config->url . '/sso/login'
            . '?app=' . $config->appSlug
            . '&redirect=' . urlencode($callback);

        return redirect()->to($url);
    }
}
```

Daftarkan di `app/Config/Filters.php`:

```php
public array $aliases = [
    // ...
    'iam' => \App\Filters\IamFilter::class,
];
```

---

## 4. Route SSO Callback

Tambahkan di `app/Config/Routes.php`:

```php
$routes->get('sso/callback', 'SsoCallbackController::handle');
```

Buat `app/Controllers/SsoCallbackController.php`:

```php
<?php

namespace App\Controllers;

use App\Libraries\IamSignature;
use CodeIgniter\Config\Services;
use Config\Iam;

class SsoCallbackController extends BaseController
{
    public function handle()
    {
        $code = $this->request->getGet('code');

        if (! $code || strlen($code) !== 64) {
            return $this->response->setStatusCode(400)->setBody('SSO code tidak valid.');
        }

        $config  = config('Iam');
        $signer  = new IamSignature();
        $path    = '/api/v1/iam/exchange-code';
        $body    = json_encode(['code' => $code]);
        $headers = $signer->buildHeaders('POST', $path, [], $body);

        $client   = Services::curlrequest();
        $response = $client->request('POST', $config->url . $path, [
            'headers' => $headers,
            'body'    => $body,
        ]);

        if ($response->getStatusCode() !== 200) {
            return redirect()->to('/login')->with('error', 'SSO gagal. Silakan coba lagi.');
        }

        $data    = json_decode($response->getBody(), true);
        $session = Services::session();

        // Simpan token di session server-side
        $session->set([
            'iam_token'      => $data['token'],
            'iam_expires_at' => $data['expires_at'],
        ]);

        return redirect()->to('/dashboard');
    }
}
```

---

## 5. Penggunaan di Route

```php
// app/Config/Routes.php

// Protect seluruh group
$routes->group('', ['filter' => 'iam'], function ($routes) {
    $routes->get('dashboard', 'DashboardController::index');
    $routes->get('rekap', 'RekapController::index');
});

// Protect dengan cek permission
$routes->post('absensi', 'AbsensiController::store', ['filter' => 'iam:absensi:create']);
```

---

## 6. Akses Data User di Controller

```php
public function index()
{
    $user        = $this->request->iam_user;
    $permissions = $this->request->iam_permissions;

    return view('dashboard', ['user' => $user]);
}
```

---

## 7. Logout

```php
public function logout()
{
    $session = Services::session();
    $token   = $session->get('iam_token');

    if ($token) {
        $config  = config('Iam');
        $signer  = new IamSignature();
        $path    = '/api/v1/iam/logout';
        $headers = $signer->buildHeaders('POST', $path);

        $client = Services::curlrequest();
        $client->request('POST', $config->url . $path, [
            'headers' => array_merge($headers, [
                'Authorization' => 'Bearer ' . $token,
            ]),
        ]);
    }

    $session->remove(['iam_token', 'iam_expires_at']);

    return redirect()->to('/login');
}
```
