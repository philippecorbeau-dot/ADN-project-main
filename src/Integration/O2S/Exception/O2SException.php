<?php

declare(strict_types=1);

namespace App\Integration\O2S\Exception;

/**
 * Base exception for all O2S-related errors.
 * 
 * This allows consumers to catch all O2S errors with a single catch block
 * while still being able to differentiate between specific error types.
 */
class O2SException extends \RuntimeException
{
    public function __construct(
        string $message = 'An O2S error occurred',
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly ?array $context = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getContext(): ?array
    {
        return $this->context;
    }
}


