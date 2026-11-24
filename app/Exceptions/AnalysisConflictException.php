<?php

namespace App\Exceptions;

use App\Models\AiAnalysis;
use RuntimeException;
use Throwable;

class AnalysisConflictException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?AiAnalysis $analysis = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
