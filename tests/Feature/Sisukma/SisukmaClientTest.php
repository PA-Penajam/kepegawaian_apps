<?php

use App\Services\Sisukma\Exceptions\SisukmaException;
use App\Services\Sisukma\SisukmaClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('SisukmaClient mengirim header HMAC sesuai kontrak untuk GET dan POST', function () {
    $secret = 'rahasia-sisukma-client';
    config([
        'sisukma.enabled' => true,
        'sisukma.base_url' => 'https://sisukma.example.test',
        'sisukma.client_id' => 'kepegawaian',
        'sisukma.secret' => $secret,
        'sisukma.timeout' => 5,
    ]);

    $capturedGet = null;
    $capturedPost = null;

    Http::fake(function (Request $request) use (&$capturedGet, &$capturedPost) {
        if ($request->method() === 'GET') {
            $capturedGet = $request;

            return Http::response(['ok' => true, 'app' => 'sisukma'], 200);
        }

        $capturedPost = $request;

        return Http::response(['created' => true], 201);
    });

    $client = app(SisukmaClient::class);

    $getResponse = $client->get('/api/integrations/v1/ping', ['b' => '2', 'a' => '1']);
    expect($getResponse)->toBe(['ok' => true, 'app' => 'sisukma'])
        ->and($capturedGet)->not->toBeNull();

    $tsGet = $capturedGet->header('X-Timestamp')[0];
    $sigGet = $capturedGet->header('X-Signature')[0];
    $expectedGet = hash_hmac('sha256', 'GET:/api/integrations/v1/ping:a=1&b=2:'.hash('sha256', '').':'.$tsGet, $secret);
    expect($capturedGet->header('X-Client-Id')[0])->toBe('kepegawaian')
        ->and($capturedGet->header('X-Nonce')[0])->not->toBeEmpty()
        ->and($sigGet)->toBe($expectedGet);

    $postPayload = ['subject' => 'Cuti Tahunan'];
    $postResponse = $client->post('/api/integrations/v1/outgoing-mails', $postPayload);
    expect($postResponse)->toBe(['created' => true])
        ->and($capturedPost)->not->toBeNull();

    $tsPost = $capturedPost->header('X-Timestamp')[0];
    $sigPost = $capturedPost->header('X-Signature')[0];
    $expectedPost = hash_hmac(
        'sha256',
        'POST:/api/integrations/v1/outgoing-mails::'.hash('sha256', $capturedPost->body()).':'.$tsPost,
        $secret,
    );
    expect($sigPost)->toBe($expectedPost);
});

test('SisukmaClient melakukan retry dengan X-Nonce baru pada 500/429 dan tidak me-retry 422', function () {
    config([
        'sisukma.enabled' => true,
        'sisukma.base_url' => 'https://sisukma.example.test',
        'sisukma.client_id' => 'kepegawaian',
        'sisukma.secret' => 'secret-retry',
        'sisukma.timeout' => 5,
    ]);

    $nonces = [];
    $attempt = 0;

    Http::fake(function (Request $request) use (&$nonces, &$attempt) {
        $nonces[] = $request->header('X-Nonce')[0] ?? null;
        $attempt++;

        if ($attempt === 1) {
            return Http::response('Server Error', 500);
        }

        if ($attempt === 2) {
            return Http::response('Too Many Requests', 429);
        }

        return Http::response(['ok' => true], 200);
    });

    $result = app(SisukmaClient::class)->ping();

    expect($result)->toBe(['ok' => true])
        ->and($attempt)->toBe(3)
        ->and(count(array_unique($nonces)))->toBe(3);
});

test('SisukmaClient tidak melakukan retry pada respons 422', function () {
    config([
        'sisukma.enabled' => true,
        'sisukma.base_url' => 'https://sisukma.example.test',
        'sisukma.client_id' => 'kepegawaian',
        'sisukma.secret' => 'secret-no-retry',
        'sisukma.timeout' => 5,
    ]);

    $calls422 = 0;
    Http::fake(function () use (&$calls422) {
        $calls422++;

        return Http::response(['message' => 'Validation error'], 422);
    });

    expect(fn () => app(SisukmaClient::class)->post('/api/integrations/v1/outgoing-mails', []))
        ->toThrow(SisukmaException::class);

    expect($calls422)->toBe(1);
});

test('SisukmaClient melempar SisukmaException bila konfigurasi belum lengkap', function () {
    config([
        'sisukma.enabled' => true,
        'sisukma.base_url' => '',
        'sisukma.client_id' => '',
        'sisukma.secret' => '',
    ]);

    expect(fn () => app(SisukmaClient::class)->ping())
        ->toThrow(SisukmaException::class);
});

test('perintah sisukma:ping mengembalikan sukses saat SiSukma menjawab 200', function () {
    config([
        'sisukma.enabled' => true,
        'sisukma.base_url' => 'https://sisukma.example.test',
        'sisukma.client_id' => 'kepegawaian',
        'sisukma.secret' => 'secret-ping',
    ]);

    Http::fake([
        'https://sisukma.example.test/api/integrations/v1/ping' => Http::response([
            'ok' => true,
            'app' => 'sisukma',
            'client' => 'kepegawaian',
        ], 200),
    ]);

    $this->artisan('sisukma:ping')
        ->expectsOutputToContain('Koneksi ke SiSukma berhasil')
        ->assertSuccessful();
});

test('perintah sisukma:ping mengembalikan gagal saat SiSukma menolak permintaan', function () {
    config([
        'sisukma.enabled' => true,
        'sisukma.base_url' => 'https://sisukma.example.test',
        'sisukma.client_id' => 'kepegawaian',
        'sisukma.secret' => 'secret-ping',
    ]);

    Http::fake([
        'https://sisukma.example.test/api/integrations/v1/ping' => Http::response(['message' => 'Unauthorized'], 401),
    ]);

    $this->artisan('sisukma:ping')
        ->assertFailed();
});
