<?php

namespace App\Keycloak\Services;

use App\Keycloak\Enums\ConflictType;
use App\Keycloak\Models\KeycloakSyncAudit;
use App\Models\Pegawai;

/**
 * Service untuk mencatat audit log operasi sinkronisasi Keycloak.
 *
 * Menangani logging event: create, update, conflict, dan sync_failure.
 * Setiap log menyimpan snapshot data Pegawai dan Keycloak dalam format JSON
 * dengan batas maksimal 64KB per field.
 */
class SyncAuditLogger
{
    /**
     * Batas maksimal karakter untuk field error_details.
     */
    private const int MAX_ERROR_DETAILS_LENGTH = 1000;

    /**
     * Batas maksimal ukuran JSON snapshot dalam bytes (64KB).
     */
    private const int MAX_SNAPSHOT_BYTES = 65536;

    /**
     * Log event pembuatan user baru di Keycloak.
     *
     * Mencatat pegawai_id, nip, snapshot data Pegawai, dan siapa yang memicu operasi.
     */
    public function logCreate(Pegawai $pegawai, ?Pegawai $causedBy = null): void
    {
        KeycloakSyncAudit::create([
            'event_type' => 'create',
            'pegawai_id' => $pegawai->id,
            'nip' => $pegawai->nip,
            'pegawai_snapshot' => $this->truncateSnapshot($this->buildPegawaiSnapshot($pegawai)),
            'resolved_by' => 'system',
            'caused_by' => $causedBy?->id,
            'caused_by_nip' => $causedBy?->nip,
        ]);
    }

    /**
     * Log event update user di Keycloak tanpa konflik.
     *
     * Mencatat pegawai_id, nip, dan snapshot data Pegawai terbaru.
     */
    public function logUpdate(Pegawai $pegawai, ?Pegawai $causedBy = null): void
    {
        KeycloakSyncAudit::create([
            'event_type' => 'update',
            'pegawai_id' => $pegawai->id,
            'nip' => $pegawai->nip,
            'pegawai_snapshot' => $this->truncateSnapshot($this->buildPegawaiSnapshot($pegawai)),
            'resolved_by' => 'system',
            'caused_by' => $causedBy?->id,
            'caused_by_nip' => $causedBy?->nip,
        ]);
    }

    /**
     * Log event konflik antara data Pegawai dan Keycloak.
     *
     * Mencatat jenis konflik, snapshot kedua sisi, resolusi yang diambil,
     * dan siapa yang menyelesaikan konflik.
     *
     * @param  array<string, mixed>  $pegawaiSnapshot
     * @param  array<string, mixed>  $keycloakSnapshot
     * @param  array<string, mixed>  $resolution
     */
    public function logConflict(
        Pegawai $pegawai,
        ConflictType $conflictType,
        array $pegawaiSnapshot,
        array $keycloakSnapshot,
        array $resolution,
        string $resolvedBy = 'system',
    ): void {
        KeycloakSyncAudit::create([
            'event_type' => 'conflict',
            'pegawai_id' => $pegawai->id,
            'nip' => $pegawai->nip,
            'conflict_type' => $conflictType->value,
            'pegawai_snapshot' => $this->truncateSnapshot($pegawaiSnapshot),
            'keycloak_snapshot' => $this->truncateSnapshot($keycloakSnapshot),
            'resolution' => $this->truncateSnapshot($resolution),
            'resolved_by' => $resolvedBy,
        ]);
    }

    /**
     * Log event kegagalan sinkronisasi.
     *
     * Mencatat pegawai_id, nip, dan detail error yang dipotong
     * maksimal 1000 karakter. Error details disimpan dalam field resolution
     * sebagai JSON karena schema tidak memiliki kolom dedicated.
     */
    public function logSyncFailure(Pegawai $pegawai, string $errorDetails, ?Pegawai $causedBy = null): void
    {
        KeycloakSyncAudit::create([
            'event_type' => 'sync_failure',
            'pegawai_id' => $pegawai->id,
            'nip' => $pegawai->nip,
            'pegawai_snapshot' => $this->truncateSnapshot($this->buildPegawaiSnapshot($pegawai)),
            'resolution' => [
                'error_details' => mb_substr($errorDetails, 0, self::MAX_ERROR_DETAILS_LENGTH),
            ],
            'resolved_by' => 'system',
            'caused_by' => $causedBy?->id,
            'caused_by_nip' => $causedBy?->nip,
        ]);
    }

    /**
     * Bangun snapshot data Pegawai untuk disimpan di audit log.
     *
     * @return array<string, mixed>
     */
    private function buildPegawaiSnapshot(Pegawai $pegawai): array
    {
        return [
            'id' => $pegawai->id,
            'nip' => $pegawai->nip,
            'nama_lengkap' => $pegawai->nama_lengkap,
            'email' => $pegawai->email,
            'status_pegawai' => $pegawai->status_pegawai?->value,
            'status_kepegawaian' => $pegawai->status_kepegawaian?->value,
            'keycloak_user_id' => $pegawai->keycloak_user_id,
            'keycloak_synced_at' => $pegawai->keycloak_synced_at?->toIso8601String(),
        ];
    }

    /**
     * Potong snapshot JSON agar tidak melebihi batas 64KB.
     *
     * Jika ukuran JSON melebihi batas, data akan dikurangi dengan
     * menyertakan indikator truncation.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function truncateSnapshot(array $data): array
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);

        if ($json === false || strlen($json) <= self::MAX_SNAPSHOT_BYTES) {
            return $data;
        }

        // Jika melebihi batas, kembalikan versi terpotong dengan indikator
        return [
            '_truncated' => true,
            '_original_size_bytes' => strlen($json),
            '_max_allowed_bytes' => self::MAX_SNAPSHOT_BYTES,
            'data' => json_decode(
                mb_strcut($json, 0, self::MAX_SNAPSHOT_BYTES - 100),
                associative: true,
            ) ?? ['_error' => 'Snapshot terlalu besar untuk disimpan'],
        ];
    }
}
