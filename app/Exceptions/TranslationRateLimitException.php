<?php

namespace App\Exceptions;

use RuntimeException;

class TranslationRateLimitException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $retryAfterSeconds
    ) {
        parent::__construct($message);
    }
}
