<?php

namespace App\Keycloak\DataTransferObjects;

use Carbon\CarbonInterface;

/**
 * DTO untuk hasil operasi sinkronisasi Pegawai ke Keycloak.
 *
 * Menyimpan statistik hasil sync termasuk jumlah record
 * yang dibuat, diupdate, di-skip, konflik, dan error.
 */
readonly class SyncResult
{
    /**
     * @param  array<int, array{nip: string, error: string}>  $errorDetails
     */
    public function __construct(
        public bool $success,
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
        public int $conflicts = 0,
        public int $errors = 0,
        public array $errorDetails = [],
        public ?string $syncType = null,
        public ?CarbonInterface $completedAt = null,
    ) {}
}
