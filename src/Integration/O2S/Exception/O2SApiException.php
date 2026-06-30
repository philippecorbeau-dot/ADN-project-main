<?php

declare(strict_types=1);

namespace App\Integration\O2S\Exception;

/**
 * Exception thrown when an O2S API call fails.
 */
class O2SApiException extends O2SException
{
    public static function requestFailed(string $endpoint, int $statusCode, string $message = ''): self
    {
        return new self(
            sprintf('O2S API request to "%s" failed with status %d: %s', $endpoint, $statusCode, $message),
            $statusCode,
            null,
            ['endpoint' => $endpoint, 'status_code' => $statusCode]
        );
    }

    public static function resourceNotFound(string $resource, string $id): self
    {
        return new self(
            sprintf('O2S resource "%s" with id "%s" not found', $resource, $id),
            404,
            null,
            ['resource' => $resource, 'id' => $id]
        );
    }

    public static function invalidResponse(string $endpoint, string $reason): self
    {
        return new self(
            sprintf('O2S API returned invalid response from "%s": %s', $endpoint, $reason),
            500,
            null,
            ['endpoint' => $endpoint, 'reason' => $reason]
        );
    }

    public static function rateLimited(): self
    {
        return new self('O2S API rate limit exceeded', 429);
    }
}


