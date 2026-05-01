<?php

namespace App\Services\Cuti;

use InvalidArgumentException;

class ConsumerRegistry
{
    /**
     * Mengambil konfigurasi consumer berdasarkan consumer_id.
     *
     * @return array{webhook_url: string|null, shared_secret_encrypted: string|null}
     */
    public function get(string $consumerId): array
    {
        $consumer = config("cuti.consumers.{$consumerId}");

        if ($consumer === null) {
            throw new InvalidArgumentException("Consumer [{$consumerId}] tidak terdaftar di konfigurasi.");
        }

        return $consumer;
    }

    /**
     * Mengambil semua consumer_id yang terdaftar.
     *
     * @return array<string>
     */
    public function allIds(): array
    {
        return array_keys(config('cuti.consumers', []));
    }
}
