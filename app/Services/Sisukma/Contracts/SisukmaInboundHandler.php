<?php

namespace App\Services\Sisukma\Contracts;

use App\Models\Integration\IntegrationInboundEvent;

interface SisukmaInboundHandler
{
    /**
     * Menentukan apakah handler ini memproses tipe event tertentu dari SiSukma.
     */
    public function supports(string $eventType): bool;

    /**
     * Memproses event inbound dari SiSukma.
     */
    public function handle(IntegrationInboundEvent $event): void;
}
