<?php

namespace App\Jobs\Exceptions;

use RuntimeException;

class AnonymousSiebelCsvValidationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly array $report,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
