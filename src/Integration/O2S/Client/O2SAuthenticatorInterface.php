<?php

declare(strict_types=1);

namespace App\Integration\O2S\Client;

use App\Integration\O2S\Config\O2SConfiguration;
use App\Integration\O2S\DTO\Auth\TokenDTO;

/**
 * Interface for O2S authentication.
 * 
 * Implementations should handle OAuth2 password grant flow and token refresh.
 */
interface O2SAuthenticatorInterface
{
    /**
     * Retrieves a valid access token, refreshing if necessary.
     * 
     * @throws \App\Integration\O2S\Exception\O2SAuthenticationException
     */
    public function getToken(O2SConfiguration $config): TokenDTO;

    /**
     * Forces a new token to be obtained (ignores cache).
     * 
     * @throws \App\Integration\O2S\Exception\O2SAuthenticationException
     */
    public function authenticate(O2SConfiguration $config): TokenDTO;

    /**
     * Refreshes an existing token using the refresh_token.
     * 
     * @throws \App\Integration\O2S\Exception\O2SAuthenticationException
     */
    public function refresh(O2SConfiguration $config, TokenDTO $token): TokenDTO;

    /**
     * Invalidates any cached tokens for the given configuration.
     */
    public function invalidate(O2SConfiguration $config): void;
}


