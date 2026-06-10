<?php

namespace App\Keycloak\Contracts;

use App\Keycloak\DataTransferObjects\TokenResult;
use Carbon\CarbonInterface;

/**
 * Interface untuk penyimpanan dan lifecycle management token Keycloak.
 *
 * Mengelola penyimpanan token dalam Laravel encrypted session,
 * termasuk enkripsi refresh token, tracking expiry, dan token rotation.
 */
interface KeycloakTokenStorageInterface
{
    /**
     * Menyimpan token set ke session (encrypted).
     *
     * Refresh token dienkripsi sebelum disimpan menggunakan
     * application encryption key untuk keamanan tambahan.
     */
    public function storeTokens(TokenResult $tokens): void;

    /**
     * Mengambil access token dari session.
     *
     * Mengembalikan null jika tidak ada access token yang tersimpan.
     */
    public function getAccessToken(): ?string;

    /**
     * Mengambil refresh token (decrypted) dari session.
     *
     * Mendekripsi refresh token yang tersimpan sebelum dikembalikan.
     * Mengembalikan null jika tidak ada refresh token yang tersimpan.
     */
    public function getRefreshToken(): ?string;

    /**
     * Mendapatkan waktu expiry access token.
     *
     * Digunakan untuk menentukan kapan proactive refresh harus dilakukan.
     * Mengembalikan null jika tidak ada token yang tersimpan.
     */
    public function getAccessTokenExpiry(): ?CarbonInterface;

    /**
     * Rotate tokens (simpan baru, invalidasi lama).
     *
     * Melakukan atomic replacement dari token lama ke token baru
     * dalam session untuk memastikan konsistensi data.
     */
    public function rotateTokens(TokenResult $newTokens): void;

    /**
     * Hapus semua token dari session.
     *
     * Menghapus seluruh data Keycloak (access token, refresh token,
     * expiry, user claims) dari session.
     */
    public function clearTokens(): void;

    /**
     * Cek apakah access token masih valid.
     *
     * Memverifikasi bahwa access token ada dan belum melewati
     * waktu expiry-nya.
     */
    public function isTokenValid(): bool;
}
