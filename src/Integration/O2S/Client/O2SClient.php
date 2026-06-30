<?php

declare(strict_types=1);

namespace App\Integration\O2S\Client;

use App\Integration\O2S\Config\O2SConfiguration;
use App\Integration\O2S\Exception\O2SApiException;
use App\Integration\O2S\Exception\O2SAuthenticationException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Main O2S API client.
 * 
 * Handles authenticated requests to O2S API with automatic token management.
 */
final class O2SClient implements O2SClientInterface
{
    private O2SConfiguration $config;
    private bool $configLoaded = false;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly O2SAuthenticatorInterface $authenticator,
        private readonly LoggerInterface $logger,
    ) {
        // Default configuration from environment
        $this->config = O2SConfiguration::fromEnv();
        $this->configLoaded = $this->config->isComplete();
    }

    public function get(string $endpoint, array $query = [], ?string $baseUrl = null): array
    {
        return $this->request('GET', $endpoint, ['query' => $query], $baseUrl);
    }

    public function post(string $endpoint, array $data = [], ?string $baseUrl = null): array
    {
        return $this->request('POST', $endpoint, ['json' => $data], $baseUrl);
    }

    public function put(string $endpoint, array $data = [], ?string $baseUrl = null): array
    {
        return $this->request('PUT', $endpoint, ['json' => $data], $baseUrl);
    }

    public function delete(string $endpoint, ?string $baseUrl = null): bool
    {
        $this->request('DELETE', $endpoint, [], $baseUrl);
        return true;
    }

    public function getConfiguration(): O2SConfiguration
    {
        return $this->config;
    }

    public function setConfiguration(O2SConfiguration $config): void
    {
        $this->config = $config;
        $this->configLoaded = $config->isComplete();
        
        // Invalidate cached token when configuration changes
        $this->authenticator->invalidate($config);
    }

    public function isConfigured(): bool
    {
        return $this->configLoaded && $this->config->isComplete();
    }

    public function testConnection(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $this->authenticator->getToken($this->config);
            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('O2S connection test failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Performs an authenticated HTTP request to the O2S API.
     * 
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, array $options = [], ?string $baseUrl = null): array
    {
        if (!$this->isConfigured()) {
            throw O2SAuthenticationException::missingConfiguration('O2S credentials not configured');
        }

        $token = $this->authenticator->getToken($this->config);
        $url = $this->buildUrl($endpoint, $baseUrl);

        $options['headers'] = array_merge($options['headers'] ?? [], [
            'Authorization' => $token->getAuthorizationHeader(),
            'Accept' => 'application/json',
        ]);

        $options['timeout'] = $options['timeout'] ?? 30;

        $this->logger->debug('O2S API request', [
            'method' => $method,
            'endpoint' => $endpoint,
            'url' => $url,
        ]);

        try {
            $response = $this->httpClient->request($method, $url, $options);
            return $this->handleResponse($response, $endpoint);
        } catch (O2SApiException|O2SAuthenticationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('O2S API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw O2SApiException::requestFailed($endpoint, 0, $e->getMessage());
        }
    }

    /**
     * Builds the full URL for an API endpoint.
     */
    private function buildUrl(string $endpoint, ?string $baseUrl = null): string
    {
        $baseUrl = $baseUrl ?? $this->config->getContactsApiUrl();
        
        // Ensure endpoint starts with /
        if (!str_starts_with($endpoint, '/')) {
            $endpoint = '/' . $endpoint;
        }

        return $baseUrl . $endpoint;
    }

    /**
     * Handles the API response and converts to array.
     * 
     * @return array<string, mixed>
     */
    private function handleResponse(ResponseInterface $response, string $endpoint): array
    {
        $statusCode = $response->getStatusCode();

        // Success responses
        if ($statusCode >= 200 && $statusCode < 300) {
            // 204 No Content - return empty array
            if ($statusCode === 204) {
                return [];
            }

            try {
                return $response->toArray();
            } catch (\Throwable $e) {
                // If response is empty or not JSON, return empty array
                $content = $response->getContent(false);
                if (empty($content)) {
                    return [];
                }
                throw O2SApiException::invalidResponse($endpoint, 'Invalid JSON response');
            }
        }

        // Handle specific error codes
        $errorContent = [];
        try {
            $errorContent = $response->toArray(false);
        } catch (\Throwable) {
            // Ignore JSON parsing errors for error responses
        }

        $errorMessage = $errorContent['detail'] ?? $errorContent['message'] ?? 'Unknown error';

        match ($statusCode) {
            401 => throw O2SAuthenticationException::invalidCredentials($errorMessage),
            404 => throw O2SApiException::resourceNotFound($endpoint, ''),
            429 => throw O2SApiException::rateLimited(),
            default => throw O2SApiException::requestFailed($endpoint, $statusCode, $errorMessage),
        };
    }

    /**
     * Performs a paginated GET request and returns all results.
     * 
     * @param array<string, mixed> $query
     * @return array<int, array<string, mixed>>
     */
    public function getPaginated(string $endpoint, array $query = [], int $limit = 100, ?string $baseUrl = null): array
    {
        $allResults = [];
        $offset = 0;

        do {
            $query['limit'] = $limit;
            $query['offset'] = $offset;

            $response = $this->get($endpoint, $query, $baseUrl);
            
            // If response is a simple array, add all items
            if (isset($response[0])) {
                $allResults = array_merge($allResults, $response);
                $count = count($response);
            } else {
                // Single item or empty response
                if (!empty($response)) {
                    $allResults[] = $response;
                }
                break;
            }

            $offset += $limit;

            // Stop if we got fewer results than the limit (last page)
        } while ($count === $limit);

        return $allResults;
    }
}


