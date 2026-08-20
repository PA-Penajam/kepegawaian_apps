<?php

namespace App\Services\Sso\Services;

use App\Services\Sso\DataTransferObjects\PkcePair;
use App\Services\Sso\Exceptions\SsoException;
use Exception;

/**
 * Generator untuk PKCE pair sesuai RFC 7636.
 *
 * Menghasilkan code_verifier dan code_challenge yang digunakan
 * untuk mengamankan OAuth 2.0 Authorization Code Flow terhadap
 * serangan interception.
 */
class PkceGenerator
{
    /** Jumlah random bytes minimum sesuai RFC 7636 */
    private const int RANDOM_BYTES_LENGTH = 64;

    /**
     * Generate PKCE pair baru.
     *
     * Menghasilkan code_verifier dari CSPRNG dan menghitung
     * code_challenge menggunakan SHA-256 + base64url encoding.
     *
     * @throws SsoException Jika CSPRNG tidak tersedia
     */
    public function generate(): PkcePair
    {
        $randomBytes = $this->generateRandomBytes();

        $codeVerifier = $this->base64UrlEncode($randomBytes);
        $codeChallenge = $this->base64UrlEncode(
            hash('sha256', $codeVerifier, true)
        );

        return new PkcePair(
            verifier: $codeVerifier,
            challenge: $codeChallenge,
            method: 'S256',
        );
    }

    /**
     * Generate random bytes menggunakan CSPRNG.
     *
     * @throws SsoException Jika CSPRNG tidak tersedia atau gagal
     */
    private function generateRandomBytes(): string
    {
        try {
            return random_bytes(self::RANDOM_BYTES_LENGTH);
        } catch (Exception $e) {
            throw new SsoException(
                'CSPRNG tidak tersedia. Login aman tidak dapat dilakukan: '.$e->getMessage(),
                SsoException::CSPRNG_UNAVAILABLE,
                $e,
            );
        }
    }

    /**
     * Encode data menggunakan base64url tanpa padding.
     *
     * Menghasilkan string yang hanya berisi karakter A-Z, a-z, 0-9,
     * hyphen (-), dan underscore (_) sesuai RFC 7636 Section 4.1.
     */
    public function base64UrlEncode(string $data): string
    {
        return strtr(rtrim(base64_encode($data), '='), '+/', '-_');
    }
}
