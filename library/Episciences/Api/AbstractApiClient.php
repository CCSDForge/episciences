<?php
declare(strict_types=1);

namespace Episciences\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Base class for all Episciences API clients.
 *
 * Provides shared caching helpers, default HTTP headers, JSON constants, and the
 * common OpenAIRE-AAI HTTP request logic (bi-mode auth, adaptive throttling,
 * 401/429 retry) reused by every client that talks to a graph.openaire.eu endpoint.
 * Concrete subclasses must inject a Guzzle Client and a PSR-6 cache pool
 * (either real FilesystemAdapter or ArrayAdapter for tests).
 */
abstract class AbstractApiClient
{
    public const ONE_MONTH = 3600 * 24 * 31;
    protected const JSON_MAX_DEPTH = 512;

    // Rate limits per https://graph.openaire.eu/docs/apis/terms#authentication--limits
    protected const AUTH_THROTTLE_MICROSECONDS = 500000; // 500ms (~2 req/s, quota 7200 req/h, authenticated)
    protected const UNAUTH_THROTTLE_SECONDS    = 60;      // 60s (1 req/min, quota 60 req/h, anonymous)
    protected const MAX_RETRIES = 3;

    protected Client $client;
    protected CacheItemPoolInterface $cache;
    protected LoggerInterface $logger;
    protected ?OpenAireTokenProvider $tokenProvider = null;

    public function __construct(Client $client, CacheItemPoolInterface $cache, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * True when a token provider is set up with client credentials (authenticated mode).
     */
    public function isAuthenticated(): bool
    {
        return $this->tokenProvider?->isConfigured() ?? false;
    }

    // -------------------------------------------------------------------------
    // HTTP request with bi-mode auth, adaptive throttling, and 401/429 retry
    // -------------------------------------------------------------------------

    /**
     * Issue the GET request, handling authentication, throttling, and 401/429 retry.
     * Returns the raw response body, or null on unrecoverable error / exhausted retries.
     *
     * $serviceLabel is used only to prefix log messages (e.g. "OpenAIRE", "Scholexplorer").
     */
    protected function requestWithRetry(string $url, int $paperId, string $serviceLabel): ?string
    {
        $token   = $this->tokenProvider?->getAccessToken();
        $headers = $this->defaultHeaders();

        if ($token !== null) {
            $headers['Authorization'] = 'Bearer ' . $token;
            $this->throttleAuthenticated();
        } else {
            $this->logger->warning(
                "{$serviceLabel} unauthenticated mode active: throttling for " . static::UNAUTH_THROTTLE_SECONDS . 's (rate limit: 60 req/h)'
            );
            $this->throttleUnauthenticated();
        }

        $attempt = 0;
        $tokenRefreshed = false;
        while ($attempt < static::MAX_RETRIES) {
            $attempt++;
            try {
                $response = $this->client->get($url, [
                    'headers'         => $headers,
                    'timeout'         => 30,
                    'allow_redirects' => [
                        'max'       => 2,
                        'strict'    => true,
                        'referer'   => true,
                        'protocols' => ['https'],
                    ],
                    'verify'          => true,
                ]);

                $this->logRateLimitStatus($response, $serviceLabel);
                return $response->getBody()->getContents();
            } catch (ClientException $e) {
                $statusCode = $e->getResponse()->getStatusCode();

                // 401 Unauthorized: token revoked/expired in authenticated mode -> refresh and
                // retry once. Gated on a dedicated flag (not the attempt counter) so a 401
                // occurring after a prior 429 backoff still gets a token refresh attempt.
                if ($statusCode === 401 && $token !== null && $this->tokenProvider !== null && !$tokenRefreshed) {
                    $tokenRefreshed = true;
                    $this->logger->warning("{$serviceLabel} 401 Unauthorized received, refreshing token...");
                    $this->tokenProvider->clearTokenCache();
                    $token = $this->tokenProvider->getAccessToken();
                    if ($token !== null) {
                        $headers['Authorization'] = 'Bearer ' . $token;
                        continue;
                    }
                }

                // 429 Too Many Requests: back off and retry, up to MAX_RETRIES attempts.
                if ($statusCode === 429) {
                    // Never block the request thread outside CLI execution (e.g. a request
                    // triggered synchronously from a controller action).
                    if (!$this->isCliContext()) {
                        $this->logger->warning(
                            "{$serviceLabel} 429 Too Many Requests for paper {$paperId}; not retrying outside CLI execution (would block the request)"
                        );
                        return null;
                    }

                    if ($attempt >= static::MAX_RETRIES) {
                        $this->logger->critical(
                            "{$serviceLabel} API: rate limit (429) exhausted after {$attempt} attempts for paper {$paperId}, giving up"
                        );
                        return null;
                    }

                    $retryAfter   = (int) $e->getResponse()->getHeaderLine('Retry-After');
                    $sleepSeconds = $token !== null
                        ? ($retryAfter > 0 ? $retryAfter : ($attempt * 3))
                        : max(static::UNAUTH_THROTTLE_SECONDS, $retryAfter);

                    $this->logger->warning(
                        "{$serviceLabel} 429 Too Many Requests for paper {$paperId} (attempt {$attempt}/" . static::MAX_RETRIES . "). Waiting {$sleepSeconds}s..."
                    );
                    $this->backoff($sleepSeconds);
                    continue;
                }

                $this->logger->error("{$serviceLabel} API error for paper {$paperId} (HTTP {$statusCode}): " . $e->getMessage());
                return null;
            } catch (GuzzleException $e) {
                $this->logger->error("{$serviceLabel} API connection error for paper {$paperId}: " . $e->getMessage());
                return null;
            }
        }

        return null;
    }

    /**
     * Log rate-limit response headers (x-ratelimit-used / x-ratelimit-limit), if present.
     */
    protected function logRateLimitStatus(ResponseInterface $response, string $serviceLabel): void
    {
        $used  = $response->getHeaderLine('x-ratelimit-used');
        $limit = $response->getHeaderLine('x-ratelimit-limit');
        if ($used === '' || $limit === '') {
            return;
        }

        $this->logger->debug("{$serviceLabel} Rate Limit status: {$used}/{$limit}");
        if ((int) $limit > 0 && (int) $used > ((int) $limit * 0.85)) {
            $this->logger->warning("{$serviceLabel} Rate Limit threshold > 85%: {$used}/{$limit}");
        }
    }

    /**
     * Proactive throttle before an authenticated request (~2 req/s, quota 7200 req/h).
     * Extracted as an overridable seam so tests can skip the real delay.
     */
    protected function throttleAuthenticated(): void
    {
        if (!$this->isCliContext()) {
            $this->logger->debug('authenticated throttle skipped outside CLI execution');
            return;
        }
        usleep(static::AUTH_THROTTLE_MICROSECONDS);
    }

    /**
     * Proactive throttle before an anonymous request (1 req/min, quota 60 req/h).
     * Extracted as an overridable seam so tests can skip the real delay.
     */
    protected function throttleUnauthenticated(): void
    {
        if (!$this->isCliContext()) {
            $this->logger->debug('unauthenticated throttle skipped outside CLI execution');
            return;
        }
        sleep(static::UNAUTH_THROTTLE_SECONDS);
    }

    /**
     * Reactive backoff wait after an HTTP 429 response.
     * Extracted as an overridable seam so tests can skip the real delay.
     */
    protected function backoff(int $seconds): void
    {
        sleep($seconds);
    }

    /**
     * True when running under the CLI SAPI (batch commands), false for an HTTP request.
     * Extracted as an overridable seam so tests can simulate the HTTP path.
     */
    protected function isCliContext(): bool
    {
        return PHP_SAPI === 'cli';
    }

    /**
     * Default HTTP headers shared by all API clients.
     *
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return [
            'User-Agent'   => EPISCIENCES_USER_AGENT,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
    }

    /**
     * Return cached string value for key, or null on cache miss.
     *
     * @throws InvalidArgumentException
     */
    protected function getCached(string $key): ?string
    {
        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            return (string) $item->get();
        }
        return null;
    }

    /**
     * Store a string value in cache with the default TTL (ONE_MONTH).
     *
     * @throws InvalidArgumentException
     */
    protected function saveToCache(string $key, string $data): void
    {
        $item = $this->cache->getItem($key);
        $item->set($data);
        $item->expiresAfter(self::ONE_MONTH);
        $this->cache->save($item);
    }
}
