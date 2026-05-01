<?php

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
