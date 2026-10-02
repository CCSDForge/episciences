<?php
declare(strict_types=1);

namespace Episciences\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * OAuth 2.0 Client Credentials token provider for the OpenAIRE AAI (aai.openaire.eu).
 *
 * Gracefully degrades: if no client ID/secret is configured, isConfigured() returns
 * false and getAccessToken() returns null, letting callers fall back to the
 * unauthenticated (rate-limited) mode instead of failing.
 *
 * Cache namespace: openAireAuthToken (key: 'openaire_access_token')
 */
class OpenAireTokenProvider
{
    private const CACHE_KEY = 'openaire_access_token';
    private const DEFAULT_AUTH_URL = 'https://aai.openaire.eu/oidc/token';
    private const SAFETY_MARGIN_SECONDS = 120; // renew 2 minutes before actual expiry
    private const MIN_TTL_SECONDS = 60;
    private const JSON_MAX_DEPTH = 512;

    private Client $client;
    private CacheItemPoolInterface $cache;
    private LoggerInterface $logger;
    private string $clientId;
    private string $clientSecret;
    private string $authUrl;

    public function __construct(
        Client $client,
        CacheItemPoolInterface $cache,
        LoggerInterface $logger,
        ?string $clientId = null,
        ?string $clientSecret = null,
        ?string $authUrl = null
    ) {
        $this->client       = $client;
        $this->cache        = $cache;
        $this->logger       = $logger;
        $this->clientId     = $clientId ?? '';
        $this->clientSecret = $clientSecret ?? '';
        $this->authUrl      = ($authUrl !== null && $authUrl !== '') ? $authUrl : self::DEFAULT_AUTH_URL;
    }

    /**
     * True when both a client ID and a client secret are configured.
     */
    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    /**
     * Return a cached or freshly acquired Bearer token, or null when unconfigured
     * or when the AAI request failed.
     *
     * @throws InvalidArgumentException
     */
    public function getAccessToken(): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $item = $this->cache->getItem(self::CACHE_KEY);
        if ($item->isHit()) {
            $cached = $item->get();
            // A cached `false` is the short-lived AAI-failure marker set by fetchNewToken();
            // it must not be mistaken for an (invalid) empty-string token.
            return is_string($cached) ? $cached : null;
        }

        return $this->fetchNewToken($item);
    }

    /**
     * Invalidate the cached token, forcing a fresh acquisition on the next call.
     *
     * @throws InvalidArgumentException
     */
    public function clearTokenCache(): void
    {
        $this->cache->deleteItem(self::CACHE_KEY);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function fetchNewToken(CacheItemInterface $item): ?string
    {
        $this->logger->info('Requesting new OpenAIRE AAI access token');

        try {
            $response = $this->client->post($this->authUrl, [
                'form_params' => [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
                'headers' => [
                    'Accept'     => 'application/json',
                    'User-Agent' => defined('EPISCIENCES_USER_AGENT') ? (string) constant('EPISCIENCES_USER_AGENT') : 'Episciences',
                ],
                'timeout' => 15,
                'verify'  => true,
            ]);

            $body = json_decode(
                $response->getBody()->getContents(),
                true,
                self::JSON_MAX_DEPTH,
                JSON_THROW_ON_ERROR
            );

            $token     = $body['access_token'] ?? null;
            $expiresIn = (int) ($body['expires_in'] ?? 3600);

            if (!is_string($token) || $token === '') {
                $this->logger->error('OpenAIRE AAI did not return an access_token');
                return null;
            }

            $ttl = max(self::MIN_TTL_SECONDS, $expiresIn - self::SAFETY_MARGIN_SECONDS);
            $item->set($token);
            $item->expiresAfter($ttl);
            $this->cache->save($item);

            $this->logger->info("Acquired new OpenAIRE access token, cached for {$ttl} seconds");
            return $token;
        } catch (GuzzleException|\JsonException $e) {
            $this->logger->error('Failed to acquire OpenAIRE access token: ' . $e->getMessage());
            // Cache a short-lived failure marker to prevent spamming AAI on every item during outages.
            $item->set(false);
            $item->expiresAfter(self::MIN_TTL_SECONDS);
            $this->cache->save($item);
            return null;
        }
    }
}
