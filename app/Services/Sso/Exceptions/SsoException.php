<?php

namespace App\Services\Sso\Exceptions;

use Exception;
use Throwable;

class SsoException extends Exception
{
    public const int UNKNOWN = 1000;

    public const int CODE_EXCHANGE_FAILED = 1001;

    public const int USER_INFO_FAILED = 1002;

    public const int REFRESH_TOKEN_FAILED = 1003;

    public const int INVALID_STATE = 1004;

    public const int STATE_EXPIRED = 1005;

    public const int CSPRNG_UNAVAILABLE = 1006;

    public const int SSO_UNREACHABLE = 1007;

    public function __construct(
        string $message = '',
        int $code = self::UNKNOWN,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
