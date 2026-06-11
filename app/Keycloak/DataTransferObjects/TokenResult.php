<?php

namespace App\Keycloak\DataTransferObjects;

/**
 * DTO untuk menyimpan hasil token exchange dari Keycloak.
 *
 * Berisi access token, refresh token, id token beserta
 * informasi expiry dan tipe token dari OIDC response.
 */
readonly class TokenResult
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public string $idToken,
        public int $expiresIn,
        public int $refreshExpiresIn,
        public string $tokenType = 'Bearer',
    ) {}
}
