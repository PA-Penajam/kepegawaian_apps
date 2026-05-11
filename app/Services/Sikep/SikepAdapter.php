<?php

declare(strict_types=1);

namespace App\Services\Sikep;

interface SikepAdapter
{
    /**
     * Push usulan (KP/Cuti) ke SIKEP.
     */
    public function pushUsulan(?object $usulan): ?array;

    /**
     * Pull status usulan berdasarkan external ID.
     */
    public function pullStatusUsulan(string $externalId): ?string;

    /**
     * Pull SK final berdasarkan external ID.
     */
    public function pullSkFinal(string $externalId): ?array;

    /**
     * Cek apakah adapter sudah dikonfigurasi.
     */
    public function isConfigured(): bool;
}
