<?php

namespace App\Keycloak\Exceptions;

use Exception;

/**
 * Exception umum untuk error terkait Keycloak.
 *
 * Digunakan untuk berbagai kegagalan komunikasi dengan Keycloak
 * termasuk invalid token, token expired, dan user not found.
 */
class KeycloakException extends Exception
{
    /** Token tidak valid (signature gagal, claims invalid) */
    public const int INVALID_TOKEN = 1001;

    /** Token sudah expired */
    public const int TOKEN_EXPIRED = 1002;

    /** User tidak ditemukan di sistem Pegawai */
    public const int USER_NOT_FOUND = 1003;

    /** Gagal exchange authorization code */
    public const int CODE_EXCHANGE_FAILED = 1004;

    /** Gagal refresh token */
    public const int REFRESH_FAILED = 1005;

    /** Gagal logout dari Keycloak */
    public const int LOGOUT_FAILED = 1006;
}
