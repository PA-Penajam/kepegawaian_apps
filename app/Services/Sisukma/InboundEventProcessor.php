<?php

namespace App\Services\Sisukma;

use App\Models\Integration\IntegrationInboundEvent;
use App\Services\Sisukma\Contracts\SisukmaInboundHandler;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Memproses webhook event dari SiSukma secara idempoten.
 */
class InboundEventProcessor
{
    /**
     * Mencatat event inbound dan meneruskannya ke handler terdaftar.
     *
     * @param  array<string, mixed>  $payload
     * @return array{duplicate: bool, event: IntegrationInboundEvent}
     */
    public function process(string $eventId, array $payload): array
    {
        $existing = IntegrationInboundEvent::query()
            ->where('event_id', $eventId)
            ->first();

        if ($existing !== null) {
            return [
                'duplicate' => true,
                'event' => $existing,
            ];
        }

        $occurredAt = isset($payload['occurred_at']) && is_string($payload['occurred_at'])
            ? CarbonImmutable::parse($payload['occurred_at'])
            : null;

        try {
            $event = DB::transaction(function () use ($eventId, $payload, $occurredAt): IntegrationInboundEvent {
                return IntegrationInboundEvent::query()->create([
                    'source' => 'sisukma',
                    'event_id' => $eventId,
                    'event_type' => (string) $payload['event_type'],
                    'payload' => $payload,
                    'occurred_at' => $occurredAt,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return [
                'duplicate' => true,
                'event' => IntegrationInboundEvent::query()->where('event_id', $eventId)->firstOrFail(),
            ];
        }

        try {
            $this->dispatchToHandlers($event);
            $event->forceFill([
                'processed_at' => now(),
                'failed_reason' => null,
            ])->save();
        } catch (\Throwable $e) {
            $event->forceFill([
                'failed_reason' => $e->getMessage(),
            ])->save();

            throw $e;
        }

        return [
            'duplicate' => false,
            'event' => $event,
        ];
    }

    /**
     * Meneruskan event ke semua handler yang mendukung event_type tersebut.
     */
    private function dispatchToHandlers(IntegrationInboundEvent $event): void
    {
        $handlerBindings = config('sisukma.inbound_handlers', []);
        if (! is_array($handlerBindings)) {
            return;
        }

        foreach ($handlerBindings as $binding) {
            if (! is_string($binding) || $binding === '') {
                continue;
            }

            $handler = app($binding);
            if ($handler instanceof SisukmaInboundHandler && $handler->supports($event->event_type)) {
                $handler->handle($event);
            }
        }
    }
}
