<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class DeepSeekException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
