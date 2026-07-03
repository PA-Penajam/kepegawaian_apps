<?php

namespace App\Keycloak\Contracts;

use App\Keycloak\DataTransferObjects\ConflictResult;
use App\Keycloak\Enums\ConflictPolicy;
use App\Keycloak\Enums\ConflictType;
use App\Models\Pegawai;

/**
 * Interface untuk penanganan konflik data antara Pegawai dan Keycloak user.
 *
 * Mendeteksi dan menyelesaikan konflik menggunakan kebijakan "Pegawai Wins"
 * sebagai default, dimana data Pegawai selalu menjadi source of truth.
 */
interface ConflictResolutionInterface
{
    /**
     * Deteksi konflik antara data Pegawai dan Keycloak user.
     *
     * Membandingkan field-field yang relevan (email, firstName, lastName,
     * enabled status, roles) dan mengembalikan array berisi jenis konflik
     * yang terdeteksi. Tidak memutasi input data (pure function).
     *
     * @param  array<string, mixed>  $keycloakUser
     * @return array<int, ConflictType>
     */
    public function detectConflicts(Pegawai $pegawai, array $keycloakUser): array;

    /**
     * Resolve konflik berdasarkan policy (default: Pegawai Wins).
     *
     * Menerapkan kebijakan resolusi yang aktif untuk menyelesaikan
     * konflik antara data Pegawai dan Keycloak user.
     *
     * @param  array<string, mixed>|null  $keycloakUser
     */
    public function resolve(ConflictType $type, Pegawai $pegawai, ?array $keycloakUser): ConflictResult;

    /**
     * Dapatkan policy yang aktif.
     *
     * Mengembalikan kebijakan resolusi konflik yang saat ini
     * digunakan oleh sistem (default: Pegawai Wins).
     */
    public function getPolicy(): ConflictPolicy;
}
