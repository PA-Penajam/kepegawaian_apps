<?php

namespace App\Keycloak\Contracts;

/**
 * Interface untuk circuit breaker pattern pada koneksi ke Keycloak.
 *
 * Melindungi aplikasi dari cascading failures saat Keycloak tidak tersedia
 * dengan mengelola state machine: CLOSED → OPEN → HALF_OPEN → CLOSED.
 */
interface CircuitBreakerInterface
{
    /**
     * Eksekusi operasi dengan circuit breaker protection.
     *
     * Jika circuit CLOSED atau HALF_OPEN: operasi dijalankan.
     * Jika circuit OPEN dan belum recovery timeout: throw exception.
     * Jika circuit OPEN dan sudah recovery timeout: transition ke HALF_OPEN, coba operasi.
     */
    public function call(callable $operation): mixed;

    /**
     * Cek apakah circuit sedang open (blocking requests).
     *
     * Mengembalikan true jika circuit dalam state OPEN dan
     * belum melewati recovery timeout.
     */
    public function isOpen(): bool;

    /**
     * Dapatkan state saat ini (closed, open, half_open).
     *
     * Mengembalikan string representasi dari current state
     * circuit breaker.
     */
    public function getState(): string;

    /**
     * Reset circuit breaker ke closed state.
     *
     * Manual reset yang mengubah state ke CLOSED dan
     * mereset consecutive failure count dan success count ke nol.
     */
    public function reset(): void;

    /**
     * Dapatkan jumlah failure berturut-turut.
     *
     * Mengembalikan jumlah consecutive failures yang terjadi
     * sejak terakhir kali terjadi success atau reset.
     */
    public function getFailureCount(): int;
}
