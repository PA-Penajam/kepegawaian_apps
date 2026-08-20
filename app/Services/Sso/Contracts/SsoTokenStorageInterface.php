<?php

namespace App\Services\Sso\Contracts;

use App\Services\Sso\DataTransferObjects\SsoTokenResult;
use Carbon\CarbonInterface;

interface SsoTokenStorageInterface
{
    /**
     * Menyimpan data token (access token, refresh token terenkripsi, expiry) ke session.
     */
    public function storeTokens(SsoTokenResult $tokens): void;

    /**
     * Mengambil access token dari session.
     */
    public function getAccessToken(): ?string;

    /**
     * Mengambil dan mendekripsi refresh token dari session.
     */
    public function getRefreshToken(): ?string;

    /**
     * Mengambil timestamp expiry access token.
     */
    public function getAccessTokenExpiry(): ?CarbonInterface;

    /**
     * Memperbarui token yang tersimpan di session dengan token baru (atomic replacement).
     */
    public function rotateTokens(SsoTokenResult $newTokens): void;

    /**
     * Menghapus seluruh data token dan data session terkait SSO.
     */
    public function clearTokens(): void;

    /**
     * Memeriksa apakah token yang tersimpan masih valid dan belum kedaluwarsa.
     */
    public function isTokenValid(): bool;

    /**
     * Memeriksa apakah ada token yang tersimpan di session.
     */
    public function hasTokens(): bool;
}
