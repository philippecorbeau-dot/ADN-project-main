<?php

declare(strict_types=1);

namespace App\Integration\O2S\Client;

use App\Integration\O2S\Config\O2SConfiguration;

/**
 * Interface for O2S API client.
 * 
 * Provides low-level HTTP methods for interacting with O2S API.
 * Higher-level operations should be implemented in dedicated services.
 */
interface O2SClientInterface
{
    /**
     * Performs a GET request to the O2S API.
     * 
     * @param array<string, mixed> $query Query parameters
     * @param string|null $baseUrl Optional base URL (defaults to contacts API)
     * @return array<string, mixed> Response data
     * 
     * @throws \App\Integration\O2S\Exception\O2SApiException
     * @throws \App\Integration\O2S\Exception\O2SAuthenticationException
     */
    public function get(string $endpoint, array $query = [], ?string $baseUrl = null): array;

    /**
     * Performs a POST request to the O2S API.
     * 
     * @param array<string, mixed> $data Request body
     * @param string|null $baseUrl Optional base URL (defaults to contacts API)
     * @return array<string, mixed> Response data
     * 
     * @throws \App\Integration\O2S\Exception\O2SApiException
     * @throws \App\Integration\O2S\Exception\O2SAuthenticationException
     */
    public function post(string $endpoint, array $data = [], ?string $baseUrl = null): array;

    /**
     * Performs a PUT request to the O2S API.
     * 
     * @param array<string, mixed> $data Request body
     * @param string|null $baseUrl Optional base URL (defaults to contacts API)
     * @return array<string, mixed> Response data
     * 
     * @throws \App\Integration\O2S\Exception\O2SApiException
     * @throws \App\Integration\O2S\Exception\O2SAuthenticationException
     */
    public function put(string $endpoint, array $data = [], ?string $baseUrl = null): array;

    /**
     * Performs a DELETE request to the O2S API.
     * 
     * @param string|null $baseUrl Optional base URL (defaults to contacts API)
     * @return bool True if deletion was successful
     * 
     * @throws \App\Integration\O2S\Exception\O2SApiException
     * @throws \App\Integration\O2S\Exception\O2SAuthenticationException
     */
    public function delete(string $endpoint, ?string $baseUrl = null): bool;

    /**
     * Returns the current configuration.
     */
    public function getConfiguration(): O2SConfiguration;

    /**
     * Sets a new configuration (useful for multi-tenant scenarios).
     */
    public function setConfiguration(O2SConfiguration $config): void;

    /**
     * Checks if the client is properly configured and can authenticate.
     */
    public function isConfigured(): bool;

    /**
     * Tests the connection to O2S API.
     * 
     * @return bool True if connection is successful
     */
    public function testConnection(): bool;
}


