<?php

namespace App\Keycloak\DataTransferObjects;

/**
 * DTO untuk authorization request ke Keycloak.
 *
 * Berisi URL authorization endpoint, state parameter untuk
 * CSRF protection, dan PKCE pair untuk code exchange.
 */
readonly class AuthorizationRequest
{
    public function __construct(
        public string $url,
        public string $state,
        public PkcePair $pkce,
    ) {}
}
