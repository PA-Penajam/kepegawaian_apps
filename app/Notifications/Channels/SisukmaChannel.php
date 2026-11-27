<?php

namespace App\Notifications\Channels;

use App\Jobs\Sisukma\SendSisukmaNotificationJob;
use App\Notifications\Contracts\HasSisukmaNotification;
use Illuminate\Notifications\Notification;

/**
 * Kanal notifikasi untuk meneruskan pemberitahuan ke lonceng dan Telegram melalui SiSukma.
 */
class SisukmaChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! (bool) config('sisukma.enabled') || ! (bool) config('sisukma.notifications_enabled')) {
            return;
        }

        if (! $notification instanceof HasSisukmaNotification) {
            return;
        }

        $nip = trim((string) ($notifiable->nip ?? ''));
        if ($nip === '') {
            return;
        }

        $payload = $notification->toSisukma($notifiable);

        SendSisukmaNotificationJob::dispatch(
            recipientNip: $nip,
            idempotencyKey: (string) $payload['idempotency_key'],
            title: (string) $payload['title'],
            message: (string) $payload['message'],
            actionPath: (string) $payload['action_path'],
            type: (string) ($payload['type'] ?? 'cuti'),
            metadata: (array) ($payload['metadata'] ?? []),
        );
    }
}
