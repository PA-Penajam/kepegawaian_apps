<?php

use App\Models\Integration\IntegrationInboundEvent;
use App\Services\Sisukma\Contracts\SisukmaInboundHandler;
use Illuminate\Support\Str;

function kaF0BuildWebhookHeaders(string $eventId, string $secret, string $rawBody, ?int $timestamp = null): array
{
    $ts = (string) ($timestamp ?? now()->timestamp);
    $canonical = "{$eventId}.{$ts}.{$rawBody}";

    return [
        'X-Event-Id' => $eventId,
        'X-Timestamp' => $ts,
        'X-Signature' => hash_hmac('sha256', $canonical, $secret),
    ];
}

test('webhook SiSukma menerima event valid, menyimpan baris inbound, dan mengeksekusi handler', function () {
    $secret = 'rahasia-webhook-sisukma';
    config(['sisukma.webhook_secret' => $secret]);

    $spy = new class implements SisukmaInboundHandler
    {
        public int $calls = 0;

        public function supports(string $eventType): bool
        {
            return $eventType === 'outgoing_mail.number_issued';
        }

        public function handle(IntegrationInboundEvent $event): void
        {
            $this->calls++;
        }
    };
    app()->instance('test.ka.f0_handler', $spy);
    config(['sisukma.inbound_handlers' => ['test.ka.f0_handler']]);

    $eventId = (string) Str::uuid();
    $rawBody = json_encode([
        'event_id' => $eventId,
        'event_type' => 'outgoing_mail.number_issued',
        'occurred_at' => now()->toIso8601String(),
        'data' => ['mail_number' => '10/KPA.W17-A8/KP5.3/XI/2026'],
    ], JSON_THROW_ON_ERROR);
    $headers = kaF0BuildWebhookHeaders($eventId, $secret, $rawBody);

    $this->call('POST', '/api/integrations/sisukma/events', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_EVENT_ID' => $headers['X-Event-Id'],
        'HTTP_X_TIMESTAMP' => $headers['X-Timestamp'],
        'HTTP_X_SIGNATURE' => $headers['X-Signature'],
    ], $rawBody)
        ->assertStatus(202)
        ->assertJson([
            'accepted' => true,
            'duplicate' => false,
        ]);

    $stored = IntegrationInboundEvent::query()->where('event_id', $eventId)->first();
    expect($stored)->not->toBeNull()
        ->and($stored->source)->toBe('sisukma')
        ->and($stored->processed_at)->not->toBeNull()
        ->and($spy->calls)->toBe(1);
});

test('webhook SiSukma bersifat idempoten untuk event_id yang sama', function () {
    $secret = 'rahasia-webhook-sisukma';
    config(['sisukma.webhook_secret' => $secret]);

    $spy = new class implements SisukmaInboundHandler
    {
        public int $calls = 0;

        public function supports(string $eventType): bool
        {
            return $eventType === 'outgoing_mail.number_issued';
        }

        public function handle(IntegrationInboundEvent $event): void
        {
            $this->calls++;
        }
    };
    app()->instance('test.ka.f0_handler_dup', $spy);
    config(['sisukma.inbound_handlers' => ['test.ka.f0_handler_dup']]);

    $eventId = (string) Str::uuid();
    $rawBody = json_encode([
        'event_id' => $eventId,
        'event_type' => 'outgoing_mail.number_issued',
        'data' => [],
    ], JSON_THROW_ON_ERROR);
    $headers = kaF0BuildWebhookHeaders($eventId, $secret, $rawBody);

    $server = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_EVENT_ID' => $headers['X-Event-Id'],
        'HTTP_X_TIMESTAMP' => $headers['X-Timestamp'],
        'HTTP_X_SIGNATURE' => $headers['X-Signature'],
    ];

    $this->call('POST', '/api/integrations/sisukma/events', [], [], [], $server, $rawBody)
        ->assertStatus(202)
        ->assertJson(['accepted' => true, 'duplicate' => false]);

    $this->call('POST', '/api/integrations/sisukma/events', [], [], [], $server, $rawBody)
        ->assertStatus(202)
        ->assertJson(['accepted' => true, 'duplicate' => true]);

    expect($spy->calls)->toBe(1)
        ->and(IntegrationInboundEvent::query()->where('event_id', $eventId)->count())->toBe(1);
});

test('webhook SiSukma menolak signature salah, timestamp kedaluwarsa, X-Event-Id invalid, atau mismatch body', function () {
    $secret = 'rahasia-webhook-sisukma';
    config(['sisukma.webhook_secret' => $secret]);

    $eventId = (string) Str::uuid();
    $rawBody = json_encode([
        'event_id' => $eventId,
        'event_type' => 'outgoing_mail.sent',
        'data' => [],
    ], JSON_THROW_ON_ERROR);

    $badSig = kaF0BuildWebhookHeaders($eventId, 'salah', $rawBody);
    $this->call('POST', '/api/integrations/sisukma/events', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_EVENT_ID' => $badSig['X-Event-Id'],
        'HTTP_X_TIMESTAMP' => $badSig['X-Timestamp'],
        'HTTP_X_SIGNATURE' => $badSig['X-Signature'],
    ], $rawBody)->assertUnauthorized();

    $expired = kaF0BuildWebhookHeaders($eventId, $secret, $rawBody, now()->subSeconds(350)->timestamp);
    $this->call('POST', '/api/integrations/sisukma/events', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_EVENT_ID' => $expired['X-Event-Id'],
        'HTTP_X_TIMESTAMP' => $expired['X-Timestamp'],
        'HTTP_X_SIGNATURE' => $expired['X-Signature'],
    ], $rawBody)->assertUnauthorized();

    $nonUuid = kaF0BuildWebhookHeaders('tidak-valid', $secret, $rawBody);
    $this->call('POST', '/api/integrations/sisukma/events', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_EVENT_ID' => $nonUuid['X-Event-Id'],
        'HTTP_X_TIMESTAMP' => $nonUuid['X-Timestamp'],
        'HTTP_X_SIGNATURE' => $nonUuid['X-Signature'],
    ], $rawBody)->assertUnauthorized();

    $mismatchBody = json_encode([
        'event_id' => (string) Str::uuid(),
        'event_type' => 'outgoing_mail.sent',
    ], JSON_THROW_ON_ERROR);
    $mismatchHeaders = kaF0BuildWebhookHeaders($eventId, $secret, $mismatchBody);
    $this->call('POST', '/api/integrations/sisukma/events', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_EVENT_ID' => $mismatchHeaders['X-Event-Id'],
        'HTTP_X_TIMESTAMP' => $mismatchHeaders['X-Timestamp'],
        'HTTP_X_SIGNATURE' => $mismatchHeaders['X-Signature'],
    ], $mismatchBody)->assertUnprocessable();
});
