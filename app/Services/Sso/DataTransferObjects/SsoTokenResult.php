<?php

namespace App\Services\Sso\DataTransferObjects;

readonly class SsoTokenResult
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken = null,
        public int $expiresIn = 3600,
        public string $tokenType = 'Bearer',
    ) {}

    /**
     * Membuat instance SsoTokenResult dari response JSON SSO Passport.
     *
     * @param  array{access_token: string, refresh_token?: string|null, expires_in?: int|null, token_type?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: $data['access_token'],
            refreshToken: $data['refresh_token'] ?? null,
            expiresIn: (int) ($data['expires_in'] ?? 3600),
            tokenType: $data['token_type'] ?? 'Bearer',
        );
    }
}
