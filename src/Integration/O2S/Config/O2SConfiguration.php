<?php

declare(strict_types=1);

namespace App\Integration\O2S\Config;

use App\Integration\O2S\Exception\O2SAuthenticationException;

/**
 * Immutable configuration object for O2S API connection.
 * 
 * This configuration can be loaded from environment variables or from a database
 * for multi-tenant scenarios (white-label).
 */
final class O2SConfiguration
{
    // API Base URLs
    public const ENV_PRODUCTION = 'production';
    public const ENV_RECETTE = 'recette';
    
    // Cache key prefix for O2S data
    public const CACHE_KEY_PREFIX = 'o2s_';

    private const AUTH_URLS = [
        self::ENV_PRODUCTION => 'https://auth.harvest.fr',
        self::ENV_RECETTE => 'https://auth-r7.harvest.fr',
    ];

    // API base URL - all resources use the same base URL
    private const API_BASE_URLS = [
        self::ENV_PRODUCTION => 'https://api.office2s.com',
        self::ENV_RECETTE => 'https://api.office2s.com', // TODO: Update if recette URL differs
    ];

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $username,
        private readonly string $password,
        private readonly string $environment = self::ENV_PRODUCTION,
        private readonly ?string $tenantId = null,
    ) {
    }

    /**
     * Creates configuration from environment variables.
     */
    public static function fromEnv(): self
    {
        $clientId = $_ENV['O2S_CLIENT_ID'] ?? getenv('O2S_CLIENT_ID') ?: '';
        $clientSecret = $_ENV['O2S_CLIENT_SECRET'] ?? getenv('O2S_CLIENT_SECRET') ?: '';
        $username = $_ENV['O2S_USERNAME'] ?? getenv('O2S_USERNAME') ?: '';
        $password = $_ENV['O2S_PASSWORD'] ?? getenv('O2S_PASSWORD') ?: '';
        $environment = $_ENV['O2S_ENVIRONMENT'] ?? getenv('O2S_ENVIRONMENT') ?: self::ENV_PRODUCTION;

        return new self($clientId, $clientSecret, $username, $password, $environment);
    }

    /**
     * Creates configuration from an array (useful for multi-tenant / database storage).
     * 
     * @param array{
     *     client_id: string,
     *     client_secret: string,
     *     username: string,
     *     password: string,
     *     environment?: string,
     *     tenant_id?: string
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            clientId: $data['client_id'] ?? '',
            clientSecret: $data['client_secret'] ?? '',
            username: $data['username'] ?? '',
            password: $data['password'] ?? '',
            environment: $data['environment'] ?? self::ENV_PRODUCTION,
            tenantId: $data['tenant_id'] ?? null,
        );
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function getAuthUrl(): string
    {
        return self::AUTH_URLS[$this->environment] ?? self::AUTH_URLS[self::ENV_PRODUCTION];
    }

    public function getApiBaseUrl(): string
    {
        return self::API_BASE_URLS[$this->environment] ?? self::API_BASE_URLS[self::ENV_PRODUCTION];
    }

    public function getContactsApiUrl(): string
    {
        return $this->getApiBaseUrl();
    }

    public function getComptesApiUrl(): string
    {
        return $this->getApiBaseUrl();
    }

    public function getDocumentsApiUrl(): string
    {
        return $this->getApiBaseUrl();
    }

    public function getReferentielsApiUrl(): string
    {
        return $this->getApiBaseUrl();
    }

    /**
     * @deprecated Use getApiBaseUrl() instead
     */
    public function getApiUrl(): string
    {
        return $this->getApiBaseUrl();
    }

    /**
     * Returns the full authentication endpoint URL.
     */
    public function getTokenEndpoint(): string
    {
        return $this->getAuthUrl() . '/auth/realms/AppUsers/protocol/openid-connect/token';
    }

    /**
     * Validates that all required configuration is present.
     * 
     * @throws O2SAuthenticationException if configuration is incomplete
     */
    public function validate(): void
    {
        if (empty($this->clientId)) {
            throw O2SAuthenticationException::missingConfiguration('client_id');
        }
        if (empty($this->clientSecret)) {
            throw O2SAuthenticationException::missingConfiguration('client_secret');
        }
        if (empty($this->username)) {
            throw O2SAuthenticationException::missingConfiguration('username');
        }
        if (empty($this->password)) {
            throw O2SAuthenticationException::missingConfiguration('password');
        }
    }

    /**
     * Checks if configuration is complete (without throwing).
     */
    public function isComplete(): bool
    {
        return !empty($this->clientId)
            && !empty($this->clientSecret)
            && !empty($this->username)
            && !empty($this->password);
    }

    /**
     * Returns a cache key unique to this configuration (for token caching).
     */
    public function getCacheKey(): string
    {
        return sprintf(
            'o2s_token_%s_%s',
            $this->tenantId ?? 'default',
            md5($this->clientId . $this->username)
        );
    }
}


