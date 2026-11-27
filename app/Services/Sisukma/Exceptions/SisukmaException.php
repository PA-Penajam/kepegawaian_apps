<?php

namespace App\Services\Sisukma\Exceptions;

use RuntimeException;

class SisukmaException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }
}
