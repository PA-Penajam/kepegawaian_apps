<?php

namespace App\Keycloak\Exceptions;

/**
 * Exception yang dilempar saat circuit breaker dalam state OPEN.
 *
 * Mengindikasikan bahwa Keycloak tidak tersedia dan semua request
 * ke Keycloak ditolak tanpa mencoba koneksi.
 */
class KeycloakCircuitOpenException extends KeycloakException
{
    /** Circuit breaker sedang open, request ditolak */
    public const int CIRCUIT_OPEN = 2001;

    public function __construct(string $message = 'Keycloak tidak tersedia. Circuit breaker dalam state OPEN.', int $code = self::CIRCUIT_OPEN, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
