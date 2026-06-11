<?php

namespace App\Keycloak\DataTransferObjects;

/**
 * DTO untuk PKCE pair sesuai RFC 7636.
 *
 * Berisi code_verifier dan code_challenge yang digunakan
 * untuk mengamankan authorization code flow.
 */
readonly class PkcePair
{
    public function __construct(
        public string $verifier,
        public string $challenge,
        public string $method = 'S256',
    ) {}
}
