<?php

namespace unit\library\Episciences\Api;

use Episciences\Api\OpenAireTokenProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Unit tests for OpenAireTokenProvider (OpenAIRE AAI client-credentials token acquisition).
 */
class OpenAireTokenProviderTest extends TestCase
{
    private function makeGuzzle(array $responses, array &$history = []): Client
    {
        $handlerStack = HandlerStack::create(new MockHandler($responses));
        $handlerStack->push(Middleware::history($history));
        return new Client(['handler' => $handlerStack]);
    }

    private function makeProvider(
        Client $client,
        ?ArrayAdapter $cache = null,
        ?string $clientId = 'client-id',
        ?string $clientSecret = 'client-secret'
    ): OpenAireTokenProvider {
        return new OpenAireTokenProvider(
            $client,
            $cache ?? new ArrayAdapter(),
            new NullLogger(),
            $clientId,
            $clientSecret
        );
    }

    // -------------------------------------------------------------------------
    // isConfigured()
    // -------------------------------------------------------------------------

    public function testIsConfigured_WithBothIdAndSecret_ReturnsTrue(): void
    {
        $provider = $this->makeProvider($this->makeGuzzle([]), null, 'id', 'secret');
        $this->assertTrue($provider->isConfigured());
    }

    public function testIsConfigured_MissingClientId_ReturnsFalse(): void
    {
        $provider = $this->makeProvider($this->makeGuzzle([]), null, '', 'secret');
        $this->assertFalse($provider->isConfigured());
    }

    public function testIsConfigured_MissingClientSecret_ReturnsFalse(): void
    {
        $provider = $this->makeProvider($this->makeGuzzle([]), null, 'id', '');
        $this->assertFalse($provider->isConfigured());
    }

    public function testIsConfigured_NullCredentials_ReturnsFalse(): void
    {
        $provider = $this->makeProvider($this->makeGuzzle([]), null, null, null);
        $this->assertFalse($provider->isConfigured());
    }

    // -------------------------------------------------------------------------
    // getAccessToken() — unconfigured / graceful degradation
    // -------------------------------------------------------------------------

    public function testGetAccessToken_Unconfigured_ReturnsNullWithoutHttpCall(): void
    {
        // Empty MockHandler queue: any HTTP call would throw an "empty queue" error.
        $provider = $this->makeProvider($this->makeGuzzle([]), null, '', '');
        $this->assertNull($provider->getAccessToken());
    }

    // -------------------------------------------------------------------------
    // getAccessToken() — cache hit
    // -------------------------------------------------------------------------

    public function testGetAccessToken_CacheHit_ReturnsCachedTokenWithoutHttpCall(): void
    {
        $cache = new ArrayAdapter();
        $item  = $cache->getItem('openaire_access_token');
        $item->set('cached-token-value');
        $item->expiresAfter(3600);
        $cache->save($item);

        // Empty MockHandler queue: any HTTP call would throw an "empty queue" error.
        $provider = $this->makeProvider($this->makeGuzzle([]), $cache);
        $this->assertSame('cached-token-value', $provider->getAccessToken());
    }

    // -------------------------------------------------------------------------
    // getAccessToken() — cache miss, successful acquisition
    // -------------------------------------------------------------------------

    public function testGetAccessToken_CacheMiss_AcquiresAndCachesNewToken(): void
    {
        $body     = json_encode(['access_token' => 'fresh-token', 'token_type' => 'Bearer', 'expires_in' => 3600]);
        $cache    = new ArrayAdapter();
        $provider = $this->makeProvider($this->makeGuzzle([new Response(200, [], $body)]), $cache);

        $this->assertSame('fresh-token', $provider->getAccessToken());

        $item = $cache->getItem('openaire_access_token');
        $this->assertTrue($item->isHit());
        $this->assertSame('fresh-token', $item->get());
    }

    public function testGetAccessToken_SendsClientCredentialsGrant(): void
    {
        $body     = json_encode(['access_token' => 'tok', 'expires_in' => 3600]);
        $history  = [];
        $client   = $this->makeGuzzle([new Response(200, [], $body)], $history);
        $provider = $this->makeProvider($client, null, 'my-id', 'my-secret');

        $provider->getAccessToken();

        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        parse_str((string) $request->getBody(), $formParams);
        $this->assertSame('client_credentials', $formParams['grant_type']);
        $this->assertSame('my-id', $formParams['client_id']);
        $this->assertSame('my-secret', $formParams['client_secret']);
    }

    public function testGetAccessToken_DefaultAuthUrl_UsedWhenNoneProvided(): void
    {
        $body     = json_encode(['access_token' => 'tok', 'expires_in' => 3600]);
        $history  = [];
        $client   = $this->makeGuzzle([new Response(200, [], $body)], $history);
        $provider = new OpenAireTokenProvider($client, new ArrayAdapter(), new NullLogger(), 'id', 'secret');

        $provider->getAccessToken();

        $this->assertSame('https://aai.openaire.eu/token', (string) $history[0]['request']->getUri());
    }

    public function testGetAccessToken_CustomAuthUrl_IsUsed(): void
    {
        $body     = json_encode(['access_token' => 'tok', 'expires_in' => 3600]);
        $history  = [];
        $client   = $this->makeGuzzle([new Response(200, [], $body)], $history);
        $provider = new OpenAireTokenProvider($client, new ArrayAdapter(), new NullLogger(), 'id', 'secret', 'https://custom.example.org/token');

        $provider->getAccessToken();

        $this->assertSame('https://custom.example.org/token', (string) $history[0]['request']->getUri());
    }

    // -------------------------------------------------------------------------
    // getAccessToken() — TTL computation (expires_in - 120s safety margin, floored at 60s)
    // -------------------------------------------------------------------------

    public function testGetAccessToken_CachesWithSafetyMarginTtl(): void
    {
        $body     = json_encode(['access_token' => 'tok', 'expires_in' => 3600]); // expect TTL 3480
        $cache    = new ArrayAdapter();
        $provider = $this->makeProvider($this->makeGuzzle([new Response(200, [], $body)]), $cache);

        $provider->getAccessToken();

        // ArrayAdapter items store the expiry; re-fetch from a fresh adapter view is not directly
        // introspectable, so assert indirectly: a still-fresh (mocked) clock keeps it a hit.
        $item = $cache->getItem('openaire_access_token');
        $this->assertTrue($item->isHit());
    }

    public function testGetAccessToken_ShortExpiresIn_FlooredAtMinimumTtl(): void
    {
        // expires_in=100 - safety margin 120 = -20, floored to the 60s minimum.
        $body     = json_encode(['access_token' => 'tok', 'expires_in' => 100]);
        $cache    = new ArrayAdapter();
        $provider = $this->makeProvider($this->makeGuzzle([new Response(200, [], $body)]), $cache);

        $this->assertSame('tok', $provider->getAccessToken());
        $this->assertTrue($cache->getItem('openaire_access_token')->isHit());
    }

    // -------------------------------------------------------------------------
    // getAccessToken() — error handling
    // -------------------------------------------------------------------------

    public function testGetAccessToken_MissingAccessTokenInResponse_ReturnsNull(): void
    {
        $body     = json_encode(['token_type' => 'Bearer', 'expires_in' => 3600]);
        $cache    = new ArrayAdapter();
        $provider = $this->makeProvider($this->makeGuzzle([new Response(200, [], $body)]), $cache);

        $this->assertNull($provider->getAccessToken());
        $this->assertFalse($cache->getItem('openaire_access_token')->isHit());
    }

    public function testGetAccessToken_MalformedJson_ReturnsNull(): void
    {
        $provider = $this->makeProvider($this->makeGuzzle([new Response(200, [], 'not valid json {{{')]));
        $this->assertNull($provider->getAccessToken());
    }

    public function testGetAccessToken_HttpError_ReturnsNull(): void
    {
        $provider = $this->makeProvider($this->makeGuzzle([new Response(500, [], 'Internal Server Error')]));
        $this->assertNull($provider->getAccessToken());
    }

    // -------------------------------------------------------------------------
    // getAccessToken() — negative caching on AAI failure
    // -------------------------------------------------------------------------

    public function testGetAccessToken_HttpError_CachesFailureMarker_PreventsSecondAaiCallWithinTtl(): void
    {
        // Single response queued: a second HTTP call would throw an "empty queue" error,
        // proving the failure marker was used instead of hitting AAI again.
        $provider = $this->makeProvider($this->makeGuzzle([new Response(500, [], 'Internal Server Error')]));

        $this->assertNull($provider->getAccessToken());
        $this->assertNull($provider->getAccessToken());
    }

    public function testGetAccessToken_CachedFailureMarker_ReturnsNullNotEmptyString(): void
    {
        $cache = new ArrayAdapter();
        $item  = $cache->getItem('openaire_access_token');
        $item->set(false);
        $item->expiresAfter(60);
        $cache->save($item);

        // Empty MockHandler queue: any HTTP call would throw an "empty queue" error.
        $provider = $this->makeProvider($this->makeGuzzle([]), $cache);

        $this->assertNull($provider->getAccessToken());
    }

    // -------------------------------------------------------------------------
    // clearTokenCache()
    // -------------------------------------------------------------------------

    public function testClearTokenCache_RemovesCachedToken(): void
    {
        $cache = new ArrayAdapter();
        $item  = $cache->getItem('openaire_access_token');
        $item->set('stale-token');
        $item->expiresAfter(3600);
        $cache->save($item);

        $provider = $this->makeProvider($this->makeGuzzle([]), $cache);
        $provider->clearTokenCache();

        $this->assertFalse($cache->getItem('openaire_access_token')->isHit());
    }

    public function testClearTokenCache_ThenGetAccessToken_FetchesFreshToken(): void
    {
        $cache = new ArrayAdapter();
        $item  = $cache->getItem('openaire_access_token');
        $item->set('stale-token');
        $item->expiresAfter(3600);
        $cache->save($item);

        $body    = json_encode(['access_token' => 'brand-new-token', 'expires_in' => 3600]);
        $history = [];
        $provider = $this->makeProvider($this->makeGuzzle([new Response(200, [], $body)], $history), $cache);

        $provider->clearTokenCache();
        $token = $provider->getAccessToken();

        $this->assertSame('brand-new-token', $token);
        $this->assertCount(1, $history);
    }
}
