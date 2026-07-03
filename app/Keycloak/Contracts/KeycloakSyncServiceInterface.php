<?php

namespace App\Keycloak\Contracts;

use App\Keycloak\DataTransferObjects\HealthStatus;
use App\Keycloak\DataTransferObjects\SyncResult;
use App\Models\Pegawai;

/**
 * Interface untuk sinkronisasi data Pegawai dengan Keycloak users.
 *
 * Mengelola operasi CRUD terhadap Keycloak Admin API termasuk
 * full sync, incremental sync, single sync, disable user,
 * dan health check koneksi.
 */
interface KeycloakSyncServiceInterface
{
    /**
     * Full sync semua Pegawai aktif ke Keycloak.
     *
     * Mengambil seluruh Pegawai dengan status aktif dan melakukan
     * create/update di Keycloak. Konflik di-resolve menggunakan
     * kebijakan "Pegawai Wins".
     */
    public function fullSync(): SyncResult;

    /**
     * Incremental sync (Pegawai berubah dalam 24 jam terakhir).
     *
     * Hanya memproses Pegawai yang updated_at-nya dalam
     * rentang 24 jam terakhir untuk efisiensi.
     */
    public function incrementalSync(): SyncResult;

    /**
     * Sync single Pegawai ke Keycloak.
     *
     * Melakukan sinkronisasi satu record Pegawai dengan logika
     * yang sama seperti full sync (create jika belum ada,
     * detect dan resolve conflict jika sudah ada).
     */
    public function syncPegawai(Pegawai $pegawai): SyncResult;

    /**
     * Disable user di Keycloak.
     *
     * Mengatur atribut enabled=false pada Keycloak user
     * yang sesuai dengan NIP yang diberikan.
     */
    public function disableUser(string $nip): void;

    /**
     * Cek apakah user dengan NIP tertentu ada di Keycloak.
     *
     * Melakukan pencarian user berdasarkan username=NIP
     * di Keycloak realm.
     */
    public function userExists(string $nip): bool;

    /**
     * Health check koneksi ke Keycloak.
     *
     * Mengembalikan status kesehatan koneksi termasuk
     * circuit breaker state, failure count, dan timestamps.
     */
    public function healthCheck(): HealthStatus;
}
