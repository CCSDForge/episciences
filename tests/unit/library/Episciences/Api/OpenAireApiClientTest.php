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
}
