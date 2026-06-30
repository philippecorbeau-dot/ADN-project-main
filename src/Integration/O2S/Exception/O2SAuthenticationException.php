<?php

declare(strict_types=1);

namespace App\Integration\O2S\Exception;

/**
 * Exception thrown when O2S authentication fails.
 */
class O2SAuthenticationException extends O2SException
{
    public static function invalidCredentials(string $message = ''): self
    {
        return new self(
            sprintf('O2S authentication failed: invalid credentials. %s', $message),
            401
        );
    }

    public static function tokenExpired(): self
    {
        return new self('O2S authentication token has expired', 401);
    }

    public static function missingConfiguration(string $field): self
    {
        return new self(
            sprintf('O2S configuration is incomplete: missing "%s"', $field),
            500
        );
    }
}


