<?php

namespace App\Keycloak\DataTransferObjects;

use App\Keycloak\Enums\ConflictPolicy;
use App\Keycloak\Enums\ConflictType;

/**
 * DTO untuk hasil resolusi konflik antara data Pegawai dan Keycloak.
 *
 * Menyimpan jenis konflik, data dari kedua sumber,
 * data yang sudah di-resolve, dan kebijakan yang diterapkan.
 */
readonly class ConflictResult
{
    /**
     * @param  array<string, mixed>  $pegawaiData
     * @param  array<string, mixed>  $keycloakData
     * @param  array<string, mixed>  $resolvedData
     */
    public function __construct(
        public ConflictType $type,
        public array $pegawaiData,
        public array $keycloakData,
        public array $resolvedData,
        public ConflictPolicy $policy = ConflictPolicy::PegawaiWins,
    ) {}
}
