<?php

declare(strict_types=1);

namespace App\Integration\O2S\DTO\Auth;

/**
 * Data Transfer Object for O2S OAuth2 token response.
 */
final class TokenDTO
{
    private \DateTimeImmutable $expiresAt;
    private \DateTimeImmutable $refreshExpiresAt;

    public function __construct(
        private readonly string $accessToken,
        private readonly string $refreshToken,
        private readonly int $expiresIn,
        private readonly int $refreshExpiresIn,
        private readonly string $tokenType = 'bearer',
        private readonly ?string $scope = null,
    ) {
        $now = new \DateTimeImmutable();
        // Subtract 60 seconds as safety margin
        $this->expiresAt = $now->modify(sprintf('+%d seconds', max(0, $expiresIn - 60)));
        $this->refreshExpiresAt = $now->modify(sprintf('+%d seconds', max(0, $refreshExpiresIn - 60)));
    }

    /**
     * Creates a TokenDTO from O2S API response.
     * 
     * @param array{
     *     access_token: string,
     *     refresh_token: string,
     *     expires_in: int,
     *     refresh_expires_in: int,
     *     token_type?: string,
     *     scope?: string
     * } $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            accessToken: $data['access_token'],
            refreshToken: $data['refresh_token'],
            expiresIn: (int) $data['expires_in'],
            refreshExpiresIn: (int) $data['refresh_expires_in'],
            tokenType: $data['token_type'] ?? 'bearer',
            scope: $data['scope'] ?? null,
        );
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    public function getExpiresIn(): int
    {
        return $this->expiresIn;
    }

    public function getRefreshExpiresIn(): int
    {
        return $this->refreshExpiresIn;
    }

    public function getTokenType(): string
    {
        return $this->tokenType;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getRefreshExpiresAt(): \DateTimeImmutable
    {
        return $this->refreshExpiresAt;
    }

    /**
     * Checks if the access token has expired.
     */
    public function isExpired(): bool
    {
        return new \DateTimeImmutable() >= $this->expiresAt;
    }

    /**
     * Checks if the refresh token has expired.
     */
    public function isRefreshExpired(): bool
    {
        return new \DateTimeImmutable() >= $this->refreshExpiresAt;
    }

    /**
     * Returns the Authorization header value.
     */
    public function getAuthorizationHeader(): string
    {
        return sprintf('Bearer %s', $this->accessToken);
    }

    /**
     * Serializes the token for caching.
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_in' => $this->expiresIn,
            'refresh_expires_in' => $this->refreshExpiresIn,
            'token_type' => $this->tokenType,
            'scope' => $this->scope,
            'expires_at' => $this->expiresAt->format(\DateTimeInterface::ATOM),
            'refresh_expires_at' => $this->refreshExpiresAt->format(\DateTimeInterface::ATOM),
        ];
    }
}


