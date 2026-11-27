<?php

use App\Console\Commands\Cuti\DispatchPendingEventsCommand;
use App\Models\Cuti\CutiEvent;
use App\Models\Cuti\CutiEventDelivery;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Pegawai;
use App\Services\Cuti\EventDispatcherService;
use Database\Seeders\CutiJenisMasterSeeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(CutiJenisMasterSeeder::class);
});

/**
 * Helper: buat pengajuan CT sederhana untuk testing outbox.
 */
function buatPengajuanUntukOutbox(): CutiPengajuan
{
    $pegawai = Pegawai::factory()->create();

    return CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
        'state' => 'DRAFT',
        'tanggal_mulai' => now()->addWeek()->toDateString(),
        'tanggal_selesai' => now()->addWeeks(2)->toDateString(),
        'jumlah_hari_kerja' => 5,
    ]);
}

test('dispatch writes event and deliveries atomically', function () {
    config(['cuti.consumers' => [
        'attendance-qr-system' => [
            'webhook_url' => 'https://example.com/webhook',
            'shared_secret_encrypted' => Crypt::encryptString('secret123'),
        ],
    ]]);

    $pengajuan = buatPengajuanUntukOutbox();
    $service = app(EventDispatcherService::class);

    $event = $service->dispatch('cuti.disetujui', $pengajuan);

    expect($event)->toBeInstanceOf(CutiEvent::class)
        ->and($event->aggregate_type)->toBe('PengajuanCuti')
        ->and($event->aggregate_id)->toBe($pengajuan->id)
        ->and($event->event_type)->toBe('cuti.disetujui')
        ->and($event->payload['event_id'])->toBe($event->id)
        ->and($event->payload['data']['pengajuan_id'])->toBe($pengajuan->id)
        ->and($event->payload['data']['pegawai_nip'])->toBe($pengajuan->pegawai_nip)
        ->and($event->payload['data']['jumlah_hari_kerja'])->toBe(5);

    $deliveries = CutiEventDelivery::where('event_id', $event->id)->get();
    expect($deliveries)->toHaveCount(1)
        ->and($deliveries->first()->consumer_id)->toBe('attendance-qr-system')
        ->and($deliveries->first()->status)->toBe('pending');
});

test('dispatch rollback includes event', function () {
    config(['cuti.consumers' => [
        'attendance-qr-system' => [
            'webhook_url' => 'https://example.com/webhook',
            'shared_secret_encrypted' => Crypt::encryptString('secret123'),
        ],
    ]]);

    $pengajuan = buatPengajuanUntukOutbox();
    $service = app(EventDispatcherService::class);

    try {
        DB::transaction(function () use ($service, $pengajuan) {
            $service->dispatch('cuti.disetujui', $pengajuan);
            throw new RuntimeException('Forced rollback');
        });
    } catch (RuntimeException) {
        // Diharapkan
    }

    expect(CutiEvent::count())->toBe(0)
        ->and(CutiEventDelivery::count())->toBe(0);
});

test('worker delivers successfully', function () {
    $encryptedSecret = Crypt::encryptString('test-secret');

    config(['cuti.consumers' => [
        'test-consumer' => [
            'webhook_url' => 'https://example.com/hook',
            'shared_secret_encrypted' => $encryptedSecret,
        ],
    ]]);

    $pengajuan = buatPengajuanUntukOutbox();
    $event = app(EventDispatcherService::class)->dispatch('cuti.disetujui', $pengajuan);

    // Update consumer_id sesuai config
    CutiEventDelivery::where('event_id', $event->id)->update(['consumer_id' => 'test-consumer']);

    Http::fake([
        'https://example.com/hook' => Http::response('OK', 200),
    ]);

    $this->artisan('cuti:dispatch-events')
        ->assertSuccessful();

    $delivery = CutiEventDelivery::where('event_id', $event->id)->first();
    expect($delivery->status)->toBe('delivered')
        ->and($delivery->delivered_at)->not->toBeNull()
        ->and($delivery->attempts)->toBe(1);
});

test('worker marks failed with retry', function () {
    $encryptedSecret = Crypt::encryptString('test-secret');

    config(['cuti.consumers' => [
        'test-consumer' => [
            'webhook_url' => 'https://example.com/hook',
            'shared_secret_encrypted' => $encryptedSecret,
        ],
    ]]);

    $pengajuan = buatPengajuanUntukOutbox();
    $event = app(EventDispatcherService::class)->dispatch('cuti.disetujui', $pengajuan);

    CutiEventDelivery::where('event_id', $event->id)->update(['consumer_id' => 'test-consumer']);

    Http::fake([
        'https://example.com/hook' => Http::response('Internal Server Error', 500),
    ]);

    $this->artisan('cuti:dispatch-events')
        ->assertSuccessful();

    $delivery = CutiEventDelivery::where('event_id', $event->id)->first();
    expect($delivery->status)->toBe('failed')
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->next_retry_at)->not->toBeNull()
        ->and($delivery->last_error)->toContain('500');
});

test('worker marks dead letter after max attempts', function () {
    $encryptedSecret = Crypt::encryptString('test-secret');

    config(['cuti.consumers' => [
        'test-consumer' => [
            'webhook_url' => 'https://example.com/hook',
            'shared_secret_encrypted' => $encryptedSecret,
        ],
    ]]);

    $pengajuan = buatPengajuanUntukOutbox();
    $event = app(EventDispatcherService::class)->dispatch('cuti.disetujui', $pengajuan);

    // Simulasi sudah 5 attempts sebelumnya, next retry sudah lewat
    CutiEventDelivery::where('event_id', $event->id)->update([
        'consumer_id' => 'test-consumer',
        'status' => 'failed',
        'attempts' => 5,
        'next_retry_at' => now()->subMinute(),
    ]);

    Http::fake([
        'https://example.com/hook' => Http::response('Error', 500),
    ]);

    $this->artisan('cuti:dispatch-events')
        ->assertSuccessful();

    $delivery = CutiEventDelivery::where('event_id', $event->id)->first();
    expect($delivery->status)->toBe('dead_letter')
        ->and($delivery->attempts)->toBe(6);
});

test('signature uses canonical string', function () {
    $secret = 'my-webhook-secret';
    $encryptedSecret = Crypt::encryptString($secret);

    config(['cuti.consumers' => [
        'test-consumer' => [
            'webhook_url' => 'https://example.com/hook',
            'shared_secret_encrypted' => $encryptedSecret,
        ],
    ]]);

    $pengajuan = buatPengajuanUntukOutbox();
    $event = app(EventDispatcherService::class)->dispatch('cuti.disetujui', $pengajuan);

    CutiEventDelivery::where('event_id', $event->id)->update(['consumer_id' => 'test-consumer']);

    $capturedRequest = null;
    Http::fake(function ($request) use (&$capturedRequest) {
        $capturedRequest = $request;

        return Http::response('OK', 200);
    });

    $this->artisan('cuti:dispatch-events')
        ->assertSuccessful();

    expect($capturedRequest)->not->toBeNull();

    $eventId = $capturedRequest->header('X-Event-Id')[0];
    $timestamp = $capturedRequest->header('X-Timestamp')[0];
    $receivedSignature = $capturedRequest->header('X-Signature')[0];
    $rawBody = $capturedRequest->body();

    // Recompute signature yang diharapkan
    $canonicalString = "{$eventId}.{$timestamp}.{$rawBody}";
    $expectedSignature = hash_hmac('sha256', $canonicalString, $secret);

    expect($receivedSignature)->toBe($expectedSignature)
        ->and($eventId)->toBe($event->id);
});

test('worker memulihkan delivery in_flight yang stale lebih dari 5 menit', function () {
    config(['cuti.consumers' => [
        'test-consumer' => [
            'webhook_url' => 'https://example.test/hook',
            'shared_secret_encrypted' => Crypt::encryptString('secret-stale'),
        ],
    ]]);

    $pengajuan = buatPengajuanUntukOutbox();
    $event = app(EventDispatcherService::class)->dispatch('cuti.disetujui', $pengajuan);

    CutiEventDelivery::where('event_id', $event->id)->update([
        'consumer_id' => 'test-consumer',
        'status' => 'in_flight',
        'last_attempt_at' => now()->subMinutes(10),
    ]);

    Http::fake([
        'https://example.test/hook' => Http::response('OK', 200),
    ]);

    $this->artisan('cuti:dispatch-events')->assertSuccessful();

    $delivery = CutiEventDelivery::where('event_id', $event->id)->first();
    expect($delivery->status)->toBe('delivered')
        ->and($delivery->attempts)->toBe(1);
});

test('worker tetap memproses item lain saat satu consumer memiliki secret korup', function () {
    config(['cuti.consumers' => [
        'bad-consumer' => [
            'webhook_url' => 'https://bad.example.test/hook',
            'shared_secret_encrypted' => 'ciphertext-korup-tidak-valid',
        ],
        'good-consumer' => [
            'webhook_url' => 'https://good.example.test/hook',
            'shared_secret_encrypted' => Crypt::encryptString('good-secret'),
        ],
    ]]);

    $pengajuan = buatPengajuanUntukOutbox();
    $event = app(EventDispatcherService::class)->dispatch('cuti.disetujui', $pengajuan);

    Http::fake([
        'https://good.example.test/hook' => Http::response('OK', 200),
    ]);

    $this->artisan('cuti:dispatch-events')->assertSuccessful();

    $bad = CutiEventDelivery::where('event_id', $event->id)->where('consumer_id', 'bad-consumer')->first();
    $good = CutiEventDelivery::where('event_id', $event->id)->where('consumer_id', 'good-consumer')->first();

    expect($bad->status)->toBe('failed')
        ->and($bad->last_error)->toContain('mendekripsi')
        ->and($good->status)->toBe('delivered');
});

test('worker memblokir SSRF (http, loopback, metadata IP, CGNAT, localhost) dan tidak mengikuti redirect 302', function (string $badUrl) {
    config(['cuti.consumers' => [
        'ssrf-consumer' => [
            'webhook_url' => $badUrl,
            'shared_secret_encrypted' => Crypt::encryptString('secret-ssrf'),
        ],
    ]]);

    $pengajuan = buatPengajuanUntukOutbox();
    $event = app(EventDispatcherService::class)->dispatch('cuti.disetujui', $pengajuan);

    Http::fake();

    $this->artisan('cuti:dispatch-events')->assertSuccessful();

    Http::assertNothingSent();

    $delivery = CutiEventDelivery::where('event_id', $event->id)->first();
    expect($delivery->status)->toBe('dead_letter');
})->with([
    'plain http' => 'http://example.test/hook',
    'loopback v4' => 'https://127.0.0.1/hook',
    'metadata IP' => 'https://169.254.169.254/latest/meta-data',
    'loopback v6' => 'https://[::1]/hook',
    'cgnat' => 'https://100.64.0.1/hook',
    'localhost' => 'https://localhost/hook',
]);

test('worker tidak mengikuti redirect HTTP 302 dan menandai delivery failed', function () {
    config(['cuti.consumers' => [
        'redirect-consumer' => [
            'webhook_url' => 'https://redirect.example.test/hook',
            'shared_secret_encrypted' => Crypt::encryptString('secret-redirect'),
        ],
    ]]);

    $pengajuan = buatPengajuanUntukOutbox();
    $event = app(EventDispatcherService::class)->dispatch('cuti.disetujui', $pengajuan);

    Http::fake([
        'https://redirect.example.test/hook' => Http::response('', 302, ['Location' => 'http://127.0.0.1/internal']),
    ]);

    $this->artisan('cuti:dispatch-events')->assertSuccessful();

    Http::assertSentCount(1);
    $delivery = CutiEventDelivery::where('event_id', $event->id)->first();
    expect($delivery->status)->toBe('failed')
        ->and($delivery->last_error)->toContain('302');
});

test('worker menaikkan attempts saat klaim stale in_flight hingga berstatus dead_letter', function () {
    config(['cuti.consumers' => [
        'test-consumer' => [
            'webhook_url' => 'https://example.test/hook',
            'shared_secret_encrypted' => Crypt::encryptString('secret-lease'),
        ],
    ]]);

    $pengajuan = buatPengajuanUntukOutbox();
    $event = app(EventDispatcherService::class)->dispatch('cuti.disetujui', $pengajuan);
    $delivery = CutiEventDelivery::where('event_id', $event->id)->firstOrFail();

    $attemptsObservedDuringHttp = [];

    Http::fake(function () use ($delivery, &$attemptsObservedDuringHttp) {
        $attemptsObservedDuringHttp[] = (int) CutiEventDelivery::query()->whereKey($delivery->id)->value('attempts');

        return Http::response('Error', 500);
    });

    for ($cycle = 1; $cycle <= 6; $cycle++) {
        // Simulasikan lease timeout pekerja sebelumnya yang tidak sempat memanggil markFailed()
        CutiEventDelivery::query()->whereKey($delivery->id)->update([
            'status' => 'in_flight',
            'last_attempt_at' => now()->subMinutes(10),
            'next_retry_at' => null,
        ]);

        $this->artisan('cuti:dispatch-events')->assertSuccessful();
    }

    expect($attemptsObservedDuringHttp)->toBe([1, 2, 3, 4, 5, 6]);

    // Simulasikan stale in_flight yang sudah mencapai MAX_ATTEMPTS = 6 -> sweep ke dead_letter
    CutiEventDelivery::query()->whereKey($delivery->id)->update([
        'status' => 'in_flight',
        'last_attempt_at' => now()->subMinutes(10),
    ]);

    $this->artisan('cuti:dispatch-events')->assertSuccessful();

    $fresh = $delivery->fresh();
    expect($fresh->status)->toBe('dead_letter')
        ->and($fresh->attempts)->toBe(6);
});

test('worker mem-pin IP publik hasil resolusi DNS ke CURLOPT_RESOLVE untuk mencegah DNS rebinding', function () {
    $command = new class extends DispatchPendingEventsCommand
    {
        protected function resolveHostIps(string $host): array
        {
            return ['93.184.216.34'];
        }

        public function inspectResolveAndClient(string $url): array
        {
            $validation = $this->resolveAndValidateWebhookUrl($url);
            $entries = $this->buildCurlResolveEntries((string) $validation['host'], $validation['port'], $validation['ips']);

            return [
                'validation' => $validation,
                'entries' => $entries,
            ];
        }
    };

    $result = $command->inspectResolveAndClient('https://webhook.example.test:8443/hook');

    expect($result['validation']['error'])->toBeNull()
        ->and($result['validation']['ips'])->toBe(['93.184.216.34'])
        ->and($result['entries'])->toBe(['webhook.example.test:8443:93.184.216.34']);
});

test('payload event menyertakan nomor_pengajuan, state, dan pegawai_nama secara aditif', function () {
    config(['cuti.consumers' => [
        'sisukma' => [
            'webhook_url' => 'https://sisukma.example.test/api/integrations/v1/events/kepegawaian',
            'shared_secret_encrypted' => Crypt::encryptString('secret-sisukma'),
        ],
    ]]);

    $pegawai = Pegawai::factory()->create([
        'nama_lengkap' => 'Budi Santoso, S.H.',
    ]);
    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'nomor_pengajuan' => 'CUTI/2026/00001234/0009',
        'jenis_cuti_kode' => 'CT',
        'state' => 'DISETUJUI',
    ]);

    $event = app(EventDispatcherService::class)->dispatch('cuti.disetujui', $pengajuan);

    expect($event->payload['data']['pengajuan_id'])->toBe($pengajuan->id)
        ->and($event->payload['data']['pegawai_nip'])->toBe($pegawai->nip)
        ->and($event->payload['data']['nomor_pengajuan'])->toBe('CUTI/2026/00001234/0009')
        ->and($event->payload['data']['state'])->toBe('DISETUJUI')
        ->and($event->payload['data']['pegawai_nama'])->toBe('Budi Santoso, S.H.');
});

test('worker mengizinkan host lokal saat allow_private_hosts aktif tetapi tetap memblokir IP metadata', function () {
    config([
        'cuti.webhook.allow_private_hosts' => true,
        'cuti.consumers' => [
            'sisukma' => [
                'webhook_url' => 'https://sisukma.test/api/integrations/v1/events/kepegawaian',
                'shared_secret_encrypted' => Crypt::encryptString('secret-sisukma'),
            ],
            'lokal-http' => [
                'webhook_url' => 'http://127.0.0.1:8000/hook',
                'shared_secret_encrypted' => Crypt::encryptString('secret-lokal'),
            ],
            'metadata-bad' => [
                'webhook_url' => 'https://169.254.169.254/latest/meta-data',
                'shared_secret_encrypted' => Crypt::encryptString('secret-meta'),
            ],
        ],
    ]);

    $pengajuan = buatPengajuanUntukOutbox();
    $event = app(EventDispatcherService::class)->dispatch('cuti.disetujui', $pengajuan);

    Http::fake([
        'https://sisukma.test/*' => Http::response(['accepted' => true], 202),
        'http://127.0.0.1:8000/*' => Http::response('OK', 200),
    ]);

    $this->artisan('cuti:dispatch-events')->assertSuccessful();

    $sisukmaDelivery = CutiEventDelivery::where('event_id', $event->id)->where('consumer_id', 'sisukma')->firstOrFail();
    $lokalDelivery = CutiEventDelivery::where('event_id', $event->id)->where('consumer_id', 'lokal-http')->firstOrFail();
    $metaDelivery = CutiEventDelivery::where('event_id', $event->id)->where('consumer_id', 'metadata-bad')->firstOrFail();

    expect($sisukmaDelivery->status)->toBe('delivered')
        ->and($lokalDelivery->status)->toBe('delivered')
        ->and($metaDelivery->status)->toBe('dead_letter');
});
