<?php

namespace unit\library\Episciences\Api;

use Episciences\Api\OpenAireApiClient;
use GuzzleHttp\Client;
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
        $this->assertStringContainsString('0000-0003-0045-1883', $result);
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
    // Helpers
    // -------------------------------------------------------------------------

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
