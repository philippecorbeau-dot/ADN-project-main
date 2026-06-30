<?php

declare(strict_types=1);

namespace App\Integration\O2S\Client;

use App\Integration\O2S\Config\O2SConfiguration;
use App\Integration\O2S\DTO\Auth\TokenDTO;
use App\Integration\O2S\Exception\O2SAuthenticationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Handles OAuth2 authentication with O2S API.
 * 
 * Implements token caching and automatic refresh to minimize API calls.
 */
final class O2SAuthenticator implements O2SAuthenticatorInterface
{
    private const CACHE_NAMESPACE = 'o2s_auth';
    private const CACHE_TTL = 86400; // 24 hours (tokens last 24h according to docs)

    private FilesystemAdapter $cache;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
        $this->cache = new FilesystemAdapter(self::CACHE_NAMESPACE, self::CACHE_TTL);
    }

    public function getToken(O2SConfiguration $config): TokenDTO
    {
        $config->validate();
        $cacheKey = $config->getCacheKey();

        // Try to get from cache
        $cachedItem = $this->cache->getItem($cacheKey);
        
        if ($cachedItem->isHit()) {
            $cached = $cachedItem->get();
            
            if (is_array($cached) && !empty($cached['access_token'])) {
                $token = TokenDTO::fromApiResponse($cached);
                
                // Return cached token if still valid
                if (!$token->isExpired()) {
                    $this->logger->debug('Using cached O2S token');
                    return $token;
                }
                
                // Try to refresh if refresh token is still valid
                if (!$token->isRefreshExpired()) {
                    try {
                        $this->logger->debug('Refreshing O2S token');
                        return $this->refresh($config, $token);
                    } catch (\Throwable $e) {
                        $this->logger->warning('Token refresh failed, re-authenticating', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        // Authenticate and cache the new token
        $this->logger->debug('Fetching new O2S token');
        return $this->authenticate($config);
    }

    public function authenticate(O2SConfiguration $config): TokenDTO
    {
        $config->validate();

        $this->logger->info('Authenticating with O2S API', [
            'environment' => $config->getEnvironment(),
            'username' => $config->getUsername(),
        ]);

        try {
            $response = $this->httpClient->request('POST', $config->getTokenEndpoint(), [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'grant_type' => 'password',
                    'client_id' => $config->getClientId(),
                    'client_secret' => $config->getClientSecret(),
                    'username' => $config->getUsername(),
                    'password' => $config->getPassword(),
                ],
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 401) {
                $error = $response->toArray(false);
                throw O2SAuthenticationException::invalidCredentials(
                    $error['error_description'] ?? 'Unknown error'
                );
            }

            if ($statusCode !== 200) {
                throw new O2SAuthenticationException(
                    sprintf('O2S authentication failed with status %d', $statusCode),
                    $statusCode
                );
            }

            $data = $response->toArray();

            if (!isset($data['access_token']) || !isset($data['refresh_token'])) {
                throw new O2SAuthenticationException('Invalid token response from O2S');
            }

            $token = TokenDTO::fromApiResponse($data);

            // Cache the token
            $this->cacheToken($config, $token);

            $this->logger->info('O2S authentication successful', [
                'expires_in' => $token->getExpiresIn(),
            ]);

            return $token;
        } catch (O2SAuthenticationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('O2S authentication request failed', [
                'error' => $e->getMessage(),
            ]);
            throw new O2SAuthenticationException(
                sprintf('O2S authentication request failed: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }

    public function refresh(O2SConfiguration $config, TokenDTO $token): TokenDTO
    {
        $config->validate();

        if ($token->isRefreshExpired()) {
            throw O2SAuthenticationException::tokenExpired();
        }

        $this->logger->info('Refreshing O2S token');

        try {
            $response = $this->httpClient->request('POST', $config->getTokenEndpoint(), [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $config->getClientId(),
                    'client_secret' => $config->getClientSecret(),
                    'refresh_token' => $token->getRefreshToken(),
                ],
                'timeout' => 30,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new O2SAuthenticationException(
                    sprintf('Token refresh failed with status %d', $response->getStatusCode())
                );
            }

            $data = $response->toArray();
            $newToken = TokenDTO::fromApiResponse($data);

            // Cache the new token
            $this->cacheToken($config, $newToken);

            $this->logger->info('O2S token refreshed successfully');

            return $newToken;
        } catch (O2SAuthenticationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new O2SAuthenticationException(
                sprintf('Token refresh failed: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }

    public function invalidate(O2SConfiguration $config): void
    {
        $this->cache->delete($config->getCacheKey());
        $this->logger->info('O2S token cache invalidated');
    }

    private function cacheToken(O2SConfiguration $config, TokenDTO $token): void
    {
        $cacheKey = $config->getCacheKey();
        $item = $this->cache->getItem($cacheKey);
        $item->set($token->toArray());
        $item->expiresAfter($token->getExpiresIn());
        $this->cache->save($item);
    }
}


