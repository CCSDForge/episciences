<?php

namespace unit\library\Episciences\Api;

use Episciences\Api\OpenAireApiClient;
use Episciences\Api\OpenAireTokenProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Unit tests for OpenAireApiClient (OpenAire Graph v3 API).
 */
class OpenAireApiClientTest extends TestCase
{
    private function makeClient(): OpenAireApiClient
    {
        return new OpenAireApiClient(
            new Client(),
            new ArrayAdapter(),
            new ArrayAdapter(),
            new ArrayAdapter(),
            new NullLogger()
        );
    }

    // -------------------------------------------------------------------------
    // extractJelCodes()
    // -------------------------------------------------------------------------

    public function testExtractJelCodes_EmptyResponse_ReturnsEmpty(): void
    {
        $this->assertSame([], $this->makeClient()->extractJelCodes([]));
    }

    public function testExtractJelCodes_ResultsEmpty_ReturnsEmpty(): void
    {
        $this->assertSame([], $this->makeClient()->extractJelCodes(['results' => []]));
    }

    public function testExtractJelCodes_SubjectsMissing_ReturnsEmpty(): void
    {
        $response = $this->makeResponseWithSubjects([]);
        $this->assertSame([], $this->makeClient()->extractJelCodes($response));
    }

    public function testExtractJelCodes_SchemeJel_ExtractsCode(): void
    {
        $subjects = [
            ['subject' => ['scheme' => 'jel', 'value' => 'jel:C10']],
        ];
        $response = $this->makeResponseWithSubjects($subjects);

        $this->assertSame(['C10'], $this->makeClient()->extractJelCodes($response));
    }

    public function testExtractJelCodes_SchemeJelWithoutPrefix_ExtractsRawValue(): void
    {
        $subjects = [
            ['subject' => ['scheme' => 'jel', 'value' => 'A10']],
        ];
        $response = $this->makeResponseWithSubjects($subjects);

        $this->assertSame(['A10'], $this->makeClient()->extractJelCodes($response));
    }

    public function testExtractJelCodes_JelPrefixUnderOtherScheme_ExtractsCode(): void
    {
        // some sources tag JEL values under scheme 'keyword' instead of 'jel'
        $subjects = [
            ['subject' => ['scheme' => 'keyword', 'value' => 'jel:B23']],
        ];
        $response = $this->makeResponseWithSubjects($subjects);

        $this->assertSame(['B23'], $this->makeClient()->extractJelCodes($response));
    }

    public function testExtractJelCodes_FiltersOutNonJelSubjects(): void
    {
        $subjects = [
            ['subject' => ['scheme' => 'FOS', 'value' => '0102 computer and information sciences']],
            ['subject' => ['scheme' => 'keyword', 'value' => 'Econometrics']],
            ['subject' => ['scheme' => 'jel', 'value' => 'jel:A10']],
        ];
        $response = $this->makeResponseWithSubjects($subjects);

        $this->assertSame(['A10'], $this->makeClient()->extractJelCodes($response));
    }

    public function testExtractJelCodes_DuplicateCodes_Deduplicated(): void
    {
        $subjects = [
            ['subject' => ['scheme' => 'jel', 'value' => 'jel:A10']],
            ['subject' => ['scheme' => 'jel', 'value' => 'A10']],
            ['subject' => ['scheme' => 'jel', 'value' => 'jel:B01']],
        ];
        $response = $this->makeResponseWithSubjects($subjects);

        $codes = $this->makeClient()->extractJelCodes($response);
        $this->assertCount(2, $codes);
        $this->assertContains('A10', $codes);
        $this->assertContains('B01', $codes);
    }

    public function testExtractJelCodes_MissingValue_SubjectSkipped(): void
    {
        $subjects = [
            ['subject' => ['scheme' => 'jel']], // no 'value' key
            ['subject' => ['scheme' => 'jel', 'value' => 'jel:B01']],
        ];
        $response = $this->makeResponseWithSubjects($subjects);

        $this->assertSame(['B01'], $this->makeClient()->extractJelCodes($response));
    }

    /**
     * Regression: a non-string 'value' (malformed payload) must be skipped rather than
     * crash str_starts_with()/substr() with a TypeError under strict_types.
     */
    public function testExtractJelCodes_NonStringValue_SubjectSkippedWithoutTypeError(): void
    {
        $subjects = [
            ['subject' => ['scheme' => 'jel', 'value' => ['unexpected' => 'array']]],
            ['subject' => ['scheme' => 'jel', 'value' => 42]],
            ['subject' => ['scheme' => 'jel', 'value' => 'jel:B01']],
        ];
        $response = $this->makeResponseWithSubjects($subjects);

        $this->assertSame(['B01'], $this->makeClient()->extractJelCodes($response));
    }

    /**
     * Bug fix regression: substr($value, 4) must be used instead of ltrim($value, 'jel:'),
     * which strips individual characters {j,e,l,:} from the left rather than the prefix string.
     */
    public function testBugFix_JelCodeStartingWithE_CorrectlyExtracted(): void
    {
        $response = $this->makeResponseWithSubjects([
            ['subject' => ['scheme' => 'jel', 'value' => 'jel:e10']],
        ]);
        $this->assertSame(['e10'], $this->makeClient()->extractJelCodes($response));
    }

    public function testBugFix_JelCodeStartingWithJ_CorrectlyExtracted(): void
    {
        $response = $this->makeResponseWithSubjects([
            ['subject' => ['scheme' => 'jel', 'value' => 'jel:jl1']],
        ]);
        $this->assertSame(['jl1'], $this->makeClient()->extractJelCodes($response));
    }

    // -------------------------------------------------------------------------
    // extractCreators()
    // -------------------------------------------------------------------------

    public function testExtractCreators_EmptyResponse_ReturnsNull(): void
    {
        $this->assertNull($this->makeClient()->extractCreators([]));
    }

    public function testExtractCreators_ResultsEmpty_ReturnsNull(): void
    {
        $this->assertNull($this->makeClient()->extractCreators(['results' => []]));
    }

    public function testExtractCreators_NoAuthorsKey_ReturnsNull(): void
    {
        $response = $this->makeResponseWithResult([]);
        $this->assertNull($this->makeClient()->extractCreators($response));
    }

    public function testExtractCreators_EmptyAuthorsArray_ReturnsNull(): void
    {
        $response = $this->makeResponseWithResult(['authors' => []]);
        $this->assertNull($this->makeClient()->extractCreators($response));
    }

    public function testExtractCreators_WithAuthors_ReturnsArray(): void
    {
        $authors = [
            ['id' => null, 'fullName' => 'Bostjan Bresar', 'name' => 'Bostjan', 'surname' => 'Bresar', 'rank' => 1, 'pid' => null],
            ['id' => 'orcid_______::28bda0fd2326ea51cb2e980c9d232397', 'fullName' => 'Babak Samadi', 'name' => 'Babak', 'surname' => 'Samadi', 'rank' => 2, 'pid' => [
                'id' => ['scheme' => 'orcid', 'value' => '0000-0003-0045-1883'],
                'provenance' => null,
            ]],
        ];
        $response = $this->makeResponseWithResult(['authors' => $authors]);

        $this->assertSame($authors, $this->makeClient()->extractCreators($response));
    }

    // -------------------------------------------------------------------------
    // extractFunding()
    // -------------------------------------------------------------------------

    public function testExtractFunding_EmptyResponse_ReturnsNull(): void
    {
        $this->assertNull($this->makeClient()->extractFunding([]));
    }

    public function testExtractFunding_NoProjectsKey_ReturnsNull(): void
    {
        $response = $this->makeResponseWithResult([]);
        $this->assertNull($this->makeClient()->extractFunding($response));
    }

    public function testExtractFunding_EmptyProjectsArray_ReturnsNull(): void
    {
        $response = $this->makeResponseWithResult(['projects' => []]);
        $this->assertNull($this->makeClient()->extractFunding($response));
    }

    public function testExtractFunding_WithProjects_ReturnsProjectsArray(): void
    {
        $projects = [
            [
                'id'      => 'corda_______::824087',
                'code'    => '824087',
                'acronym' => 'EOSC-Pillar',
                'title'   => 'European Open Science Cloud Pillar',
                'funder'  => 'European Commission',
                'pids'    => null,
            ],
        ];
        $response = $this->makeResponseWithResult(['projects' => $projects]);

        $this->assertSame($projects, $this->makeClient()->extractFunding($response));
    }

    // -------------------------------------------------------------------------
    // putCreatorInCache()
    // -------------------------------------------------------------------------

    public function testPutCreatorInCache_ResponseWithCreators_CachesCreatorArray(): void
    {
        $authors  = [['id' => null, 'fullName' => 'Doe, Jane', 'name' => 'Jane', 'surname' => 'Doe', 'rank' => 1, 'pid' => null]];
        $response = $this->makeResponseWithResult(['authors' => $authors]);

        $authorsCache = new ArrayAdapter();
        $client = $this->makeClientWithCaches(new ArrayAdapter(), $authorsCache, new ArrayAdapter());
        $doi = '10.1234/test';

        $client->putCreatorInCache($response, $doi);

        $item = $authorsCache->getItem(md5($doi) . '_creator.json');
        $this->assertTrue($item->isHit());
        $this->assertSame($authors, json_decode($item->get(), true));
    }

    public function testPutCreatorInCache_NullResponse_StoresEmptyMarker(): void
    {
        $authorsCache = new ArrayAdapter();
        $client = $this->makeClientWithCaches(new ArrayAdapter(), $authorsCache, new ArrayAdapter());
        $doi = '10.1234/test';

        $client->putCreatorInCache(null, $doi);

        $item = $authorsCache->getItem(md5($doi) . '_creator.json');
        $this->assertTrue($item->isHit());
        $this->assertSame([''], json_decode($item->get(), true));
    }

    public function testPutCreatorInCache_ResponseWithNoCreators_StoresEmptyMarker(): void
    {
        $authorsCache = new ArrayAdapter();
        $client = $this->makeClientWithCaches(new ArrayAdapter(), $authorsCache, new ArrayAdapter());
        $doi = '10.1234/test';

        $client->putCreatorInCache([], $doi); // empty response → extractCreators returns null

        $item = $authorsCache->getItem(md5($doi) . '_creator.json');
        $this->assertTrue($item->isHit());
        $this->assertSame([''], json_decode($item->get(), true));
    }

    // -------------------------------------------------------------------------
    // putFundingInCache()
    // -------------------------------------------------------------------------

    public function testPutFundingInCache_ResponseWithFunding_CachesFundingArray(): void
    {
        $projects = [
            ['id' => 'corda_______::824087', 'code' => '824087', 'acronym' => 'EOSC-Pillar', 'title' => 'European Open Science Cloud Pillar', 'funder' => 'European Commission', 'pids' => null],
        ];
        $response = $this->makeResponseWithResult(['projects' => $projects]);

        $fundingCache = new ArrayAdapter();
        $client = $this->makeClientWithCaches(new ArrayAdapter(), new ArrayAdapter(), $fundingCache);
        $doi = '10.1234/test';

        $client->putFundingInCache($response, $doi);

        $item = $fundingCache->getItem(md5($doi) . '_funding.json');
        $this->assertTrue($item->isHit());
        $this->assertSame($projects, json_decode($item->get(), true));
    }

    public function testPutFundingInCache_NullResponse_StoresEmptyMarker(): void
    {
        $fundingCache = new ArrayAdapter();
        $client = $this->makeClientWithCaches(new ArrayAdapter(), new ArrayAdapter(), $fundingCache);
        $doi = '10.1234/test';

        $client->putFundingInCache(null, $doi);

        $item = $fundingCache->getItem(md5($doi) . '_funding.json');
        $this->assertTrue($item->isHit());
        $this->assertSame([''], json_decode($item->get(), true));
    }

    public function testPutFundingInCache_ResponseWithNoFunding_StoresEmptyMarker(): void
    {
        $fundingCache = new ArrayAdapter();
        $client = $this->makeClientWithCaches(new ArrayAdapter(), new ArrayAdapter(), $fundingCache);
        $doi = '10.1234/test';

        $client->putFundingInCache([], $doi); // empty response → extractFunding returns null

        $item = $fundingCache->getItem(md5($doi) . '_funding.json');
        $this->assertTrue($item->isHit());
        $this->assertSame([''], json_decode($item->get(), true));
    }

    // -------------------------------------------------------------------------
    // insertOrcidAuthorFromCache()
    // -------------------------------------------------------------------------

    public function testInsertOrcidAuthorFromCache_CacheMiss_ReturnsZero(): void
    {
        $authorsCache = new ArrayAdapter();
        $client = $this->makeClientWithCaches(new ArrayAdapter(), $authorsCache, new ArrayAdapter());

        // Item never set → cache miss
        $item = $authorsCache->getItem('nonexistent_creator.json');
        $this->assertFalse($item->isHit());

        $this->assertSame(0, $client->insertOrcidAuthorFromCache($item, 42));
    }

    public function testInsertOrcidAuthorFromCache_EmptyMarker_ReturnsZero(): void
    {
        $authorsCache = new ArrayAdapter();
        $client = $this->makeClientWithCaches(new ArrayAdapter(), $authorsCache, new ArrayAdapter());

        $item = $authorsCache->getItem('test_creator.json');
        $item->set(json_encode(['']));
        $authorsCache->save($item);

        $this->assertSame(0, $client->insertOrcidAuthorFromCache($item, 42));
    }

    public function testInsertOrcidAuthorFromCache_MalformedJson_ReturnsZero(): void
    {
        $authorsCache = new ArrayAdapter();
        $client = $this->makeClientWithCaches(new ArrayAdapter(), $authorsCache, new ArrayAdapter());

        $item = $authorsCache->getItem('test_creator.json');
        $item->set('not valid json {{{');
        $authorsCache->save($item);

        $this->assertSame(0, $client->insertOrcidAuthorFromCache($item, 42));
    }

    // -------------------------------------------------------------------------
    // findOrcidForAuthor()
    // -------------------------------------------------------------------------

    public function testFindOrcidForAuthor_OrcidScheme_ReturnsCleanedOrcid(): void
    {
        $apiData = [
            ['fullName' => 'Babak Samadi', 'pid' => ['id' => ['scheme' => 'orcid', 'value' => 'https://orcid.org/0000-0003-0045-1883']]],
        ];
        $result = $this->makeClient()->findOrcidForAuthor('Babak Samadi', $apiData);
        $this->assertSame('0000-0003-0045-1883', $result);
    }

    public function testFindOrcidForAuthor_OrcidPendingScheme_ReturnsCleanedOrcid(): void
    {
        $apiData = [
            ['fullName' => 'Matti Picus', 'pid' => ['id' => ['scheme' => 'orcid_pending', 'value' => '0000-0002-1771-9949']]],
        ];
        $result = $this->makeClient()->findOrcidForAuthor('Matti Picus', $apiData);
        $this->assertSame('0000-0002-1771-9949', $result);
    }

    public function testFindOrcidForAuthor_CaseAndAccentInsensitiveMatch(): void
    {
        $apiData = [
            ['fullName' => 'Bosstjan Brešar', 'pid' => ['id' => ['scheme' => 'orcid', 'value' => '0000-0001-1111-1111']]],
        ];
        $result = $this->makeClient()->findOrcidForAuthor('bosstjan bresar', $apiData);
        $this->assertSame('0000-0001-1111-1111', $result);
    }

    public function testFindOrcidForAuthor_NoMatch_ReturnsNull(): void
    {
        $apiData = [
            ['fullName' => 'Smith, John', 'pid' => ['id' => ['scheme' => 'orcid', 'value' => '0000-0002-0000-0000']]],
        ];
        $this->assertNull($this->makeClient()->findOrcidForAuthor('Doe, Jane', $apiData));
    }

    public function testFindOrcidForAuthor_MatchWithoutPid_ReturnsNull(): void
    {
        $apiData = [
            ['fullName' => 'Sandi Klavzar', 'pid' => null],
        ];
        $this->assertNull($this->makeClient()->findOrcidForAuthor('Sandi Klavzar', $apiData));
    }

    public function testFindOrcidForAuthor_UnsupportedScheme_ReturnsNull(): void
    {
        $apiData = [
            ['fullName' => 'Doe, Jane', 'pid' => ['id' => ['scheme' => 'ror', 'value' => '05dxps055']]],
        ];
        $this->assertNull($this->makeClient()->findOrcidForAuthor('Doe, Jane', $apiData));
    }

    /**
     * Regression: a non-string 'fullName' (malformed payload) must be skipped rather than
     * crash mb_strtolower() with a TypeError under strict_types.
     */
    public function testFindOrcidForAuthor_NonStringFullName_EntrySkippedWithoutTypeError(): void
    {
        $apiData = [
            ['fullName' => ['unexpected' => 'array'], 'pid' => null],
            ['fullName' => 'Doe, Jane', 'pid' => ['id' => ['scheme' => 'orcid', 'value' => '0000-0001-2345-6789']]],
        ];
        $result = $this->makeClient()->findOrcidForAuthor('Doe, Jane', $apiData);
        $this->assertSame('0000-0001-2345-6789', $result);
    }

    /**
     * Regression: a non-string 'pid.id.value' must be skipped rather than crash
     * cleanLowerCaseOrcid() (strictly typed `string $orcid`) with a TypeError.
     */
    public function testFindOrcidForAuthor_NonStringPidValue_ReturnsNullWithoutTypeError(): void
    {
        $apiData = [
            ['fullName' => 'Doe, Jane', 'pid' => ['id' => ['scheme' => 'orcid', 'value' => ['unexpected' => 'array']]]],
        ];
        $this->assertNull($this->makeClient()->findOrcidForAuthor('Doe, Jane', $apiData));
    }

    /**
     * Golden rule: 'id' (an OpenAire-internal hash, e.g. 'orcid_______::28bda0fd...') must
     * never be used as an ORCID, only 'pid.id.value'.
     */
    public function testFindOrcidForAuthor_NeverUsesInternalIdHash(): void
    {
        $apiData = [
            ['id' => 'orcid_______::28bda0fd2326ea51cb2e980c9d232397', 'fullName' => 'Babak Samadi', 'pid' => null],
        ];
        $this->assertNull($this->makeClient()->findOrcidForAuthor('Babak Samadi', $apiData));
    }

    public function testFindOrcidForAuthor_EmptyApiData_ReturnsNull(): void
    {
        $this->assertNull($this->makeClient()->findOrcidForAuthor('Doe, Jane', []));
    }

    // -------------------------------------------------------------------------
    // isAuthenticated()
    // -------------------------------------------------------------------------

    public function testIsAuthenticated_NoTokenProvider_ReturnsFalse(): void
    {
        $client = $this->makeBiModeClient($this->makeGuzzle([]));
        $this->assertFalse($client->isAuthenticated());
    }

    public function testIsAuthenticated_UnconfiguredTokenProvider_ReturnsFalse(): void
    {
        $client = $this->makeBiModeClient($this->makeGuzzle([]), $this->makeUnconfiguredTokenProvider());
        $this->assertFalse($client->isAuthenticated());
    }

    public function testIsAuthenticated_ConfiguredTokenProvider_ReturnsTrue(): void
    {
        $client = $this->makeBiModeClient($this->makeGuzzle([]), $this->makeAuthenticatedTokenProvider());
        $this->assertTrue($client->isAuthenticated());
    }

    // -------------------------------------------------------------------------
    // fetchPublication() — bi-mode auth & throttling
    // -------------------------------------------------------------------------

    public function testFetchPublication_CacheHit_NoHttpCallNoThrottle(): void
    {
        $cache = new ArrayAdapter();
        $item  = $cache->getItem(md5('10.1234/cached') . '.json');
        $item->set(json_encode(['results' => [['mainTitle' => 'Cached']]]));
        $cache->save($item);

        // Empty MockHandler queue: any HTTP call would throw an "empty queue" error.
        $client = new OpenAireApiClientNoSleep(
            $this->makeGuzzle([]),
            $cache,
            new ArrayAdapter(),
            new ArrayAdapter(),
            new NullLogger()
        );

        $result = $client->fetchPublication('10.1234/cached', 1);

        $this->assertSame('Cached', $result['results'][0]['mainTitle']);
        $this->assertSame([], $client->sleepLog);
    }

    public function testFetchPublication_Authenticated_SendsBearerTokenAndThrottles500ms(): void
    {
        $history = [];
        $guzzle  = $this->makeGuzzle([new Response(200, [], json_encode(['results' => []]))], $history);
        $client  = $this->makeBiModeClient($guzzle, $this->makeAuthenticatedTokenProvider('the-bearer-token'));

        $client->fetchPublication('10.1234/authed', 1);

        $this->assertCount(1, $history);
        $this->assertSame('Bearer the-bearer-token', $history[0]['request']->getHeaderLine('Authorization'));
        $this->assertSame(['auth:500000us'], $client->sleepLog);
    }

    public function testFetchPublication_Unauthenticated_NoAuthorizationHeaderAndThrottles60s(): void
    {
        $history = [];
        $guzzle  = $this->makeGuzzle([new Response(200, [], json_encode(['results' => []]))], $history);
        $client  = $this->makeBiModeClient($guzzle, null);

        $client->fetchPublication('10.1234/anon', 1);

        $this->assertCount(1, $history);
        $this->assertFalse($history[0]['request']->hasHeader('Authorization'));
        $this->assertSame(['unauth:60s'], $client->sleepLog);
    }

    public function testFetchPublication_Unauthenticated_LogsWarning(): void
    {
        $testHandler = new TestHandler();
        $logger      = new Logger('test');
        $logger->pushHandler($testHandler);

        $guzzle = $this->makeGuzzle([new Response(200, [], json_encode(['results' => []]))]);
        $client = $this->makeBiModeClient($guzzle, null, $logger);

        $client->fetchPublication('10.1234/anon', 1);

        $this->assertTrue($testHandler->hasWarningThatContains('unauthenticated mode'));
    }

    // -------------------------------------------------------------------------
    // fetchPublication() — HTTP 401 retry (authenticated mode only)
    // -------------------------------------------------------------------------

    public function testFetchPublication_401ThenSuccess_RefreshesTokenAndRetriesOnce(): void
    {
        $history = [];
        $guzzle  = $this->makeGuzzle([
            new Response(401, [], 'Unauthorized'),
            new Response(200, [], json_encode(['results' => [['mainTitle' => 'Recovered']]])),
        ], $history);

        // Token cache pre-seeded with a stale token; clearTokenCache() + getAccessToken()
        // must yield a (mocked) fresh one from the same cache-hit path.
        $tokenCache = new ArrayAdapter();
        $tokenItem  = $tokenCache->getItem('openaire_access_token');
        $tokenItem->set('stale-token');
        $tokenItem->expiresAfter(3600);
        $tokenCache->save($tokenItem);

        // A second getItem() after clearTokenCache() would miss and try a real AAI call;
        // to keep this a pure unit test we re-seed via a token provider whose AAI mock
        // returns a fresh token on that fallback call.
        $aaiClient  = new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['access_token' => 'refreshed-token', 'expires_in' => 3600])),
        ]))]);
        $tokenProvider = new OpenAireTokenProvider($aaiClient, $tokenCache, new NullLogger(), 'id', 'secret');

        $client = $this->makeBiModeClient($guzzle, $tokenProvider);
        $result = $client->fetchPublication('10.1234/expired-token', 1);

        $this->assertSame('Recovered', $result['results'][0]['mainTitle']);
        $this->assertCount(2, $history);
        $this->assertSame('Bearer stale-token', $history[0]['request']->getHeaderLine('Authorization'));
        $this->assertSame('Bearer refreshed-token', $history[1]['request']->getHeaderLine('Authorization'));
    }

    public function testFetchPublication_401Twice_ReturnsNullAfterSingleRetry(): void
    {
        $history = [];
        $guzzle  = $this->makeGuzzle([
            new Response(401, [], 'Unauthorized'),
            new Response(401, [], 'Unauthorized'),
        ], $history);

        // Token cache pre-seeded; the refresh triggered by the first 401 must still
        // succeed in acquiring *a* (still-rejected) token, so the second GET is attempted.
        $tokenCache = new ArrayAdapter();
        $tokenItem  = $tokenCache->getItem('openaire_access_token');
        $tokenItem->set('stale-token');
        $tokenItem->expiresAfter(3600);
        $tokenCache->save($tokenItem);

        $aaiClient = new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['access_token' => 'still-rejected-token', 'expires_in' => 3600])),
        ]))]);
        $tokenProvider = new OpenAireTokenProvider($aaiClient, $tokenCache, new NullLogger(), 'id', 'secret');

        $client = $this->makeBiModeClient($guzzle, $tokenProvider);
        $result = $client->fetchPublication('10.1234/still-unauthorized', 1);

        $this->assertNull($result);
        // Exactly one retry: the initial attempt + one refreshed-token retry, no more.
        $this->assertCount(2, $history);
    }

    public function testFetchPublication_401_TokenRefreshFails_ReturnsNullWithoutSecondRequest(): void
    {
        $history = [];
        $guzzle  = $this->makeGuzzle([new Response(401, [], 'Unauthorized')], $history);

        $tokenCache = new ArrayAdapter();
        $tokenItem  = $tokenCache->getItem('openaire_access_token');
        $tokenItem->set('stale-token');
        $tokenItem->expiresAfter(3600);
        $tokenCache->save($tokenItem);

        // AAI itself fails on the refresh attempt -> getAccessToken() returns null.
        $aaiClient     = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(500, [], 'error')]))]);
        $tokenProvider = new OpenAireTokenProvider($aaiClient, $tokenCache, new NullLogger(), 'id', 'secret');

        $client = $this->makeBiModeClient($guzzle, $tokenProvider);
        $result = $client->fetchPublication('10.1234/refresh-fails', 1);

        $this->assertNull($result);
        // No second GET: refresh failed, so there is no new token to retry with.
        $this->assertCount(1, $history);
    }

    // -------------------------------------------------------------------------
    // fetchPublication() — HTTP 429 backoff & retry
    // -------------------------------------------------------------------------

    public function testFetchPublication_429ThenSuccess_Authenticated_BacksOffUsingRetryAfter(): void
    {
        $history = [];
        $guzzle  = $this->makeGuzzle([
            new Response(429, ['Retry-After' => '7'], 'Too Many Requests'),
            new Response(200, [], json_encode(['results' => [['mainTitle' => 'OK']]])),
        ], $history);

        $client = $this->makeBiModeClient($guzzle, $this->makeAuthenticatedTokenProvider());
        $result = $client->fetchPublication('10.1234/ratelimited', 1);

        $this->assertSame('OK', $result['results'][0]['mainTitle']);
        $this->assertCount(2, $history);
        $this->assertSame(['auth:500000us', 'backoff:7s'], $client->sleepLog);
    }

    public function testFetchPublication_429ThenSuccess_Authenticated_NoRetryAfterHeader_UsesAttemptBackoff(): void
    {
        $guzzle = $this->makeGuzzle([
            new Response(429, [], 'Too Many Requests'),
            new Response(200, [], json_encode(['results' => []])),
        ]);

        $client = $this->makeBiModeClient($guzzle, $this->makeAuthenticatedTokenProvider());
        $client->fetchPublication('10.1234/ratelimited-no-header', 1);

        // attempt 1 * 3 seconds
        $this->assertSame(['auth:500000us', 'backoff:3s'], $client->sleepLog);
    }

    public function testFetchPublication_429ThenSuccess_Unauthenticated_BacksOffAtLeast60s(): void
    {
        $guzzle = $this->makeGuzzle([
            new Response(429, ['Retry-After' => '5'], 'Too Many Requests'), // below the 60s floor
            new Response(200, [], json_encode(['results' => []])),
        ]);

        $client = $this->makeBiModeClient($guzzle, null);
        $client->fetchPublication('10.1234/ratelimited-anon', 1);

        $this->assertSame(['unauth:60s', 'backoff:60s'], $client->sleepLog);
    }

    public function testFetchPublication_429Exhausted_ReturnsNullAndLogsCritical(): void
    {
        $testHandler = new TestHandler();
        $logger      = new Logger('test');
        $logger->pushHandler($testHandler);

        $guzzle = $this->makeGuzzle([
            new Response(429, [], 'Too Many Requests'),
            new Response(429, [], 'Too Many Requests'),
            new Response(429, [], 'Too Many Requests'),
        ]);

        $client = $this->makeBiModeClient($guzzle, $this->makeAuthenticatedTokenProvider(), $logger);
        $result = $client->fetchPublication('10.1234/always-ratelimited', 1);

        $this->assertNull($result);
        $this->assertTrue($testHandler->hasCriticalThatContains('rate limit (429) exhausted'));
    }

    // -------------------------------------------------------------------------
    // fetchPublication() — never blocks the request thread outside CLI (e.g. a
    // request triggered synchronously from PaperController/AdministratepaperController)
    // -------------------------------------------------------------------------

    public function testFetchPublication_429_OutsideCli_ReturnsNullImmediatelyWithoutRetry(): void
    {
        $testHandler = new TestHandler();
        $logger      = new Logger('test');
        $logger->pushHandler($testHandler);

        $history = [];
        // Only one response queued: a retry attempt would exhaust the mock queue and error out.
        $guzzle = $this->makeGuzzle([new Response(429, ['Retry-After' => '5'], 'Too Many Requests')], $history);

        $client = $this->makeHttpContextClient($guzzle, $this->makeAuthenticatedTokenProvider(), $logger);
        $result = $client->fetchPublication('10.1234/ratelimited-http', 1);

        $this->assertNull($result);
        $this->assertCount(1, $history, 'must not retry outside CLI execution');
        $this->assertTrue($testHandler->hasWarningThatContains('not retrying outside CLI execution'));
    }

    public function testFetchPublication_429_OutsideCli_Unauthenticated_ReturnsNullImmediately(): void
    {
        $history = [];
        $guzzle  = $this->makeGuzzle([new Response(429, [], 'Too Many Requests')], $history);

        $client = $this->makeHttpContextClient($guzzle, null);
        $result = $client->fetchPublication('10.1234/ratelimited-http-anon', 1);

        $this->assertNull($result);
        $this->assertCount(1, $history, 'must not retry outside CLI execution');
    }

    public function testThrottleAuthenticated_OutsideCli_SkipsDelayAndStillSendsRequest(): void
    {
        $testHandler = new TestHandler();
        $logger      = new Logger('test');
        $logger->pushHandler($testHandler);

        $history = [];
        $guzzle  = $this->makeGuzzle([new Response(200, [], json_encode(['results' => []]))], $history);

        $client = $this->makeHttpContextClient($guzzle, $this->makeAuthenticatedTokenProvider('http-token'), $logger);
        $client->fetchPublication('10.1234/authed-http', 1);

        $this->assertCount(1, $history);
        $this->assertSame('Bearer http-token', $history[0]['request']->getHeaderLine('Authorization'));
        $this->assertTrue($testHandler->hasDebugThatContains('authenticated throttle skipped outside CLI execution'));
    }

    public function testThrottleUnauthenticated_OutsideCli_SkipsDelayAndStillSendsRequest(): void
    {
        $testHandler = new TestHandler();
        $logger      = new Logger('test');
        $logger->pushHandler($testHandler);

        $history = [];
        $guzzle  = $this->makeGuzzle([new Response(200, [], json_encode(['results' => []]))], $history);

        $client = $this->makeHttpContextClient($guzzle, null, $logger);
        $client->fetchPublication('10.1234/anon-http', 1);

        $this->assertCount(1, $history);
        $this->assertFalse($history[0]['request']->hasHeader('Authorization'));
        $this->assertTrue($testHandler->hasDebugThatContains('unauthenticated throttle skipped outside CLI execution'));
    }

    // -------------------------------------------------------------------------
    // fetchPublication() — rate-limit header logging
    // -------------------------------------------------------------------------

    public function testFetchPublication_RateLimitHeaders_LoggedAtDebug(): void
    {
        $testHandler = new TestHandler();
        $logger      = new Logger('test');
        $logger->pushHandler($testHandler);

        $guzzle = $this->makeGuzzle([
            new Response(200, ['x-ratelimit-used' => '10', 'x-ratelimit-limit' => '7200'], json_encode(['results' => []])),
        ]);

        $client = $this->makeBiModeClient($guzzle, $this->makeAuthenticatedTokenProvider(), $logger);
        $client->fetchPublication('10.1234/ratelimit-status', 1);

        $this->assertTrue($testHandler->hasDebugThatContains('10/7200'));
    }

    public function testFetchPublication_RateLimitAbove85Percent_LogsWarning(): void
    {
        $testHandler = new TestHandler();
        $logger      = new Logger('test');
        $logger->pushHandler($testHandler);

        $guzzle = $this->makeGuzzle([
            new Response(200, ['x-ratelimit-used' => '6200', 'x-ratelimit-limit' => '7200'], json_encode(['results' => []])),
        ]);

        $client = $this->makeBiModeClient($guzzle, $this->makeAuthenticatedTokenProvider(), $logger);
        $client->fetchPublication('10.1234/ratelimit-high', 1);

        $this->assertTrue($testHandler->hasWarningThatContains('threshold > 85%'));
    }

    public function testFetchPublication_RateLimitHeadersAbsent_NoLogging(): void
    {
        $testHandler = new TestHandler();
        $logger      = new Logger('test');
        $logger->pushHandler($testHandler);

        $guzzle = $this->makeGuzzle([new Response(200, [], json_encode(['results' => []]))]);
        $client = $this->makeBiModeClient($guzzle, $this->makeAuthenticatedTokenProvider(), $logger);
        $client->fetchPublication('10.1234/no-ratelimit-headers', 1);

        $this->assertFalse($testHandler->hasRecordThatContains('Rate Limit', Logger::DEBUG));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeGuzzle(array $responses, array &$history = []): Client
    {
        $handlerStack = HandlerStack::create(new MockHandler($responses));
        $handlerStack->push(Middleware::history($history));
        return new Client(['handler' => $handlerStack]);
    }

    private function makeAuthenticatedTokenProvider(string $token = 'test-access-token'): OpenAireTokenProvider
    {
        $cache = new ArrayAdapter();
        $item  = $cache->getItem('openaire_access_token');
        $item->set($token);
        $item->expiresAfter(3600);
        $cache->save($item);

        return new OpenAireTokenProvider(
            new Client(['handler' => HandlerStack::create(new MockHandler([]))]), // never called: cache hit
            $cache,
            new NullLogger(),
            'client-id',
            'client-secret'
        );
    }

    private function makeUnconfiguredTokenProvider(): OpenAireTokenProvider
    {
        return new OpenAireTokenProvider(
            new Client(['handler' => HandlerStack::create(new MockHandler([]))]),
            new ArrayAdapter(),
            new NullLogger(),
            '',
            ''
        );
    }

    private function makeBiModeClient(
        Client $guzzle,
        ?OpenAireTokenProvider $tokenProvider = null,
        ?Logger $logger = null
    ): OpenAireApiClientNoSleep {
        return new OpenAireApiClientNoSleep(
            $guzzle,
            new ArrayAdapter(),
            new ArrayAdapter(),
            new ArrayAdapter(),
            $logger ?? new NullLogger(),
            $tokenProvider
        );
    }

    private function makeHttpContextClient(
        Client $guzzle,
        ?OpenAireTokenProvider $tokenProvider = null,
        ?Logger $logger = null
    ): OpenAireApiClientHttpContext {
        return new OpenAireApiClientHttpContext(
            $guzzle,
            new ArrayAdapter(),
            new ArrayAdapter(),
            new ArrayAdapter(),
            $logger ?? new NullLogger(),
            $tokenProvider
        );
    }

    /** @param array<int, array<string, mixed>> $subjects */
    private function makeResponseWithSubjects(array $subjects): array
    {
        return $this->makeResponseWithResult(['subjects' => $subjects]);
    }

    /** @param array<string, mixed> $resultEntry */
    private function makeResponseWithResult(array $resultEntry): array
    {
        return ['results' => [$resultEntry]];
    }

    private function makeClientWithCaches(
        ArrayAdapter $globalCache,
        ArrayAdapter $authorsCache,
        ArrayAdapter $fundingCache
    ): OpenAireApiClient {
        return new OpenAireApiClient(
            new Client(),
            $globalCache,
            $authorsCache,
            $fundingCache,
            new NullLogger()
        );
    }
}

/**
 * Test double: records throttle/backoff calls instead of actually sleeping,
 * so 60s-anonymous-mode and 429-backoff tests run instantly.
 */
class OpenAireApiClientNoSleep extends OpenAireApiClient
{
    /** @var array<int, string> */
    public array $sleepLog = [];

    protected function throttleAuthenticated(): void
    {
        $this->sleepLog[] = 'auth:500000us';
    }

    protected function throttleUnauthenticated(): void
    {
        $this->sleepLog[] = 'unauth:60s';
    }

    protected function backoff(int $seconds): void
    {
        $this->sleepLog[] = "backoff:{$seconds}s";
    }
}

/**
 * Test double: simulates the HTTP request path (non-CLI SAPI) by overriding only
 * isCliContext(), while keeping the real throttleAuthenticated()/throttleUnauthenticated()/
 * 429-branch guard logic — so these tests exercise the actual "skip the delay outside
 * CLI" code paths without ever reaching a real sleep()/usleep() call.
 */
class OpenAireApiClientHttpContext extends OpenAireApiClient
{
    protected function isCliContext(): bool
    {
        return false;
    }
}
