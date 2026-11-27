<?php

namespace App\Notifications\Contracts;

interface HasSisukmaNotification
{
    /**
     * Menyusun payload notifikasi untuk dikirim ke lonceng dan Telegram melalui SiSukma.
     *
     * @return array{
     *     idempotency_key: string,
     *     title: string,
     *     message: string,
     *     action_path: string,
     *     type: string,
     *     metadata?: array<string, string|int|null>
     * }
     */
    public function toSisukma(object $notifiable): array;
}
