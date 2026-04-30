<?php

namespace unit\library\Episciences\Api;

use Episciences\Api\OpenAireApiClient;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Unit tests for OpenAireApiClient.
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

    public function testExtractJelCodes_ResultKeyAbsent_ReturnsEmpty(): void
    {
        $response = ['response' => ['results' => []]];
        $this->assertSame([], $this->makeClient()->extractJelCodes($response));
    }

    public function testExtractJelCodes_SubjectMissing_ReturnsEmpty(): void
    {
        $response = ['response' => ['results' => ['result' => [
            ['metadata' => ['oaf:entity' => ['oaf:result' => []]]],
        ]]]];
        $this->assertSame([], $this->makeClient()->extractJelCodes($response));
    }

    public function testExtractJelCodes_SubjectNull_ReturnsEmpty(): void
    {
        $response = ['response' => ['results' => ['result' => [
            ['metadata' => ['oaf:entity' => ['oaf:result' => ['subject' => null]]]],
        ]]]];
        $this->assertSame([], $this->makeClient()->extractJelCodes($response));
    }

    /**
     * When the API returns a single subject as an associative array (not wrapped in a list),
     * it must still be processed correctly.
     */
    public function testExtractJelCodes_SingleSubjectObject_ExtractsCode(): void
    {
        $subject  = ['@classid' => 'jel', '$' => 'jel:A10'];
        $response = $this->makeResponseWithSubject($subject);

        $this->assertSame(['A10'], $this->makeClient()->extractJelCodes($response));
    }

    public function testExtractJelCodes_MultipleSubjectsArray_FiltersJelOnly(): void
    {
        $subjects = [
            ['@classid' => 'ddc',  '$' => 'ddc:330'],
            ['@classid' => 'jel',  '$' => 'jel:B23'],
            ['@classid' => 'jel',  '$' => 'jel:C10'],
        ];
        $response = $this->makeResponseWithSubject($subjects);

        $codes = $this->makeClient()->extractJelCodes($response);
        $this->assertSame(['B23', 'C10'], $codes);
    }

    public function testExtractJelCodes_NoJelSubjects_ReturnsEmpty(): void
    {
        $subjects = [
            ['@classid' => 'ddc', '$' => 'ddc:330'],
        ];
        $response = $this->makeResponseWithSubject($subjects);

        $this->assertSame([], $this->makeClient()->extractJelCodes($response));
    }

    /**
     * Bug A fix: ltrim($value, 'jel:') strips individual characters {j,e,l,:} from the left,
     * so a code like 'jel:e10' would become '10' with ltrim, but correctly 'e10' with substr.
     */
    public function testBugFix_JelCodeStartingWithE_CorrectlyExtracted(): void
    {
        // ltrim('jel:e10', 'jel:') → strips 'j','e','l',':','e' → '10'
        // substr('jel:e10', 4)    → 'e10'  ← correct
        $response = $this->makeResponseWithSubject(['@classid' => 'jel', '$' => 'jel:e10']);
        $this->assertSame(['e10'], $this->makeClient()->extractJelCodes($response));
    }

    public function testBugFix_JelCodeStartingWithJ_CorrectlyExtracted(): void
    {
        // ltrim('jel:jl1', 'jel:') → strips 'j','l' → '1'
        // substr('jel:jl1', 4)    → 'jl1' ← correct
        $response = $this->makeResponseWithSubject(['@classid' => 'jel', '$' => 'jel:jl1']);
        $this->assertSame(['jl1'], $this->makeClient()->extractJelCodes($response));
    }

    public function testExtractJelCodes_DuplicateCodes_Deduplicated(): void
    {
        $subjects = [
            ['@classid' => 'jel', '$' => 'jel:A10'],
            ['@classid' => 'jel', '$' => 'jel:A10'],
            ['@classid' => 'jel', '$' => 'jel:B01'],
        ];
        $response = $this->makeResponseWithSubject($subjects);
        $codes    = $this->makeClient()->extractJelCodes($response);

        $this->assertCount(2, $codes);
        $this->assertContains('A10', $codes);
        $this->assertContains('B01', $codes);
    }

    public function testExtractJelCodes_MissingDollarSign_SubjectSkipped(): void
    {
        $subjects = [
            ['@classid' => 'jel'],  // no '$' key
            ['@classid' => 'jel', '$' => 'jel:B01'],
        ];
        $response = $this->makeResponseWithSubject($subjects);

        $this->assertSame(['B01'], $this->makeClient()->extractJelCodes($response));
    }

    public function testExtractJelCodes_ValueNotStartingWithJelPrefix_Skipped(): void
    {
        $subjects = [
            ['@classid' => 'jel', '$' => 'A10'],     // missing 'jel:' prefix
            ['@classid' => 'jel', '$' => 'jel:B01'],
        ];
        $response = $this->makeResponseWithSubject($subjects);

        $this->assertSame(['B01'], $this->makeClient()->extractJelCodes($response));
    }

    public function testExtractJelCodes_MultipleResults_CodesAggregatedAcrossAll(): void
    {
        $response = ['response' => ['results' => ['result' => [
            $this->makeResult([['@classid' => 'jel', '$' => 'jel:A10']]),
            $this->makeResult([['@classid' => 'jel', '$' => 'jel:B01']]),
        ]]]];

        $codes = $this->makeClient()->extractJelCodes($response);
        $this->assertContains('A10', $codes);
        $this->assertContains('B01', $codes);
    }

    // -------------------------------------------------------------------------
    // extractCreators()
    // -------------------------------------------------------------------------

    public function testExtractCreators_EmptyResponse_ReturnsNull(): void
    {
        $this->assertNull($this->makeClient()->extractCreators([]));
    }

    public function testExtractCreators_ResultKeyAbsent_ReturnsNull(): void
    {
        $this->assertNull($this->makeClient()->extractCreators(['response' => ['results' => []]]));
    }

    public function testExtractCreators_NoCreatorKey_ReturnsNull(): void
    {
        $response = $this->makeResponseWithResult(['metadata' => ['oaf:entity' => ['oaf:result' => []]]]);
        $this->assertNull($this->makeClient()->extractCreators($response));
    }

    public function testExtractCreators_WithCreators_ReturnsArray(): void
    {
        $creators = [['$' => 'Doe, Jane', '@orcid' => '0000-0001-2345-6789']];
        $response = $this->makeResponseWithResult([
            'metadata' => ['oaf:entity' => ['oaf:result' => ['creator' => $creators]]],
        ]);
        $this->assertSame($creators, $this->makeClient()->extractCreators($response));
    }

    // -------------------------------------------------------------------------
    // extractFunding()
    // -------------------------------------------------------------------------

    public function testExtractFunding_EmptyResponse_ReturnsNull(): void
    {
        $this->assertNull($this->makeClient()->extractFunding([]));
    }

    public function testExtractFunding_NoRelsKey_ReturnsNull(): void
    {
        $response = $this->makeResponseWithResult([
            'metadata' => ['oaf:entity' => ['oaf:result' => []]],
        ]);
        $this->assertNull($this->makeClient()->extractFunding($response));
    }

    public function testExtractFunding_RelsWithNoRelKey_ReturnsNull(): void
    {
        $response = $this->makeResponseWithResult([
            'metadata' => ['oaf:entity' => ['oaf:result' => ['rels' => []]]],
        ]);
        $this->assertNull($this->makeClient()->extractFunding($response));
    }

    public function testExtractFunding_WithFunding_ReturnsRelArray(): void
    {
        $rel      = [['projectTitle' => 'My Project', 'grantId' => '12345']];
        $response = $this->makeResponseWithResult([
            'metadata' => ['oaf:entity' => ['oaf:result' => ['rels' => ['rel' => $rel]]]],
        ]);
        $this->assertSame($rel, $this->makeClient()->extractFunding($response));
    }

    // -------------------------------------------------------------------------
    // putCreatorInCache()
    // -------------------------------------------------------------------------

    public function testPutCreatorInCache_ResponseWithCreators_CachesCreatorArray(): void
    {
        $creators = [['$' => 'Doe, Jane', '@orcid' => '0000-0001-2345-6789']];
        $response = $this->makeResponseWithResult([
            'metadata' => ['oaf:entity' => ['oaf:result' => ['creator' => $creators]]],
        ]);

        $authorsCache = new ArrayAdapter();
        $client = $this->makeClientWithCaches(new ArrayAdapter(), $authorsCache, new ArrayAdapter());
        $doi = '10.1234/test';

        $client->putCreatorInCache($response, $doi);

        $item = $authorsCache->getItem(md5($doi) . '_creator.json');
        $this->assertTrue($item->isHit());
        $this->assertSame($creators, json_decode($item->get(), true));
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
        $rel      = [['projectTitle' => 'My Project', 'grantId' => '12345']];
        $response = $this->makeResponseWithResult([
            'metadata' => ['oaf:entity' => ['oaf:result' => ['rels' => ['rel' => $rel]]]],
        ]);

        $fundingCache = new ArrayAdapter();
        $client = $this->makeClientWithCaches(new ArrayAdapter(), new ArrayAdapter(), $fundingCache);
        $doi = '10.1234/test';

        $client->putFundingInCache($response, $doi);

        $item = $fundingCache->getItem(md5($doi) . '_funding.json');
        $this->assertTrue($item->isHit());
        $this->assertSame($rel, json_decode($item->get(), true));
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

    public function testFindOrcidForAuthor_ExactMatch_ReturnsCleanedOrcid(): void
    {
        $apiData = [
            ['$' => 'Doe, Jane', '@orcid' => 'https://orcid.org/0000-0001-2345-6789'],
        ];
        $result = $this->makeClient()->findOrcidForAuthor('Doe, Jane', $apiData);
        // cleanLowerCaseOrcid strips URL prefix and lowercases
        $this->assertStringContainsString('0000-0001-2345-6789', $result);
    }

    public function testFindOrcidForAuthor_NoMatch_ReturnsNull(): void
    {
        $apiData = [
            ['$' => 'Smith, John', '@orcid' => 'https://orcid.org/0000-0002-0000-0000'],
        ];
        $this->assertNull($this->makeClient()->findOrcidForAuthor('Doe, Jane', $apiData));
    }

    public function testFindOrcidForAuthor_MatchWithoutOrcid_ReturnsNull(): void
    {
        $apiData = [
            ['$' => 'Doe, Jane'], // no @orcid key
        ];
        $this->assertNull($this->makeClient()->findOrcidForAuthor('Doe, Jane', $apiData));
    }

    public function testFindOrcidForAuthor_EmptyApiData_ReturnsNull(): void
    {
        $this->assertNull($this->makeClient()->findOrcidForAuthor('Doe, Jane', []));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @param mixed $subject single subject dict or array of dicts */
    private function makeResponseWithSubject(mixed $subject): array
    {
        return ['response' => ['results' => ['result' => [
            $this->makeResult($subject),
        ]]]];
    }

    /** @param mixed $subject */
    private function makeResult(mixed $subject): array
    {
        return ['metadata' => ['oaf:entity' => ['oaf:result' => ['subject' => $subject]]]];
    }

    /** @param array<string, mixed> $resultEntry */
    private function makeResponseWithResult(array $resultEntry): array
    {
        return ['response' => ['results' => ['result' => [$resultEntry]]]];
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
