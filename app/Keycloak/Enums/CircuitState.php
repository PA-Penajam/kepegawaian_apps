<?php

namespace App\Keycloak\Enums;

/**
 * Enum untuk state circuit breaker.
 *
 * Merepresentasikan tiga state dalam circuit breaker pattern:
 * Closed (normal), Open (blocking), dan HalfOpen (testing recovery).
 */
enum CircuitState: string
{
    /** Operasi normal, semua request diteruskan ke Keycloak */
    case Closed = 'closed';

    /** Blocking semua request ke Keycloak tanpa mencoba koneksi */
    case Open = 'open';

    /** Testing recovery, hanya 1 request probe yang diizinkan */
    case HalfOpen = 'half_open';
}
