<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Repositories_ArXiv_DataciteFallback and the arXiv fallback hook (no network).
 *
 * @covers Episciences_Repositories_ArXiv_DataciteFallback
 * @covers Episciences_Repositories_ArXiv_SubmissionHooks
 */
class Episciences_Repositories_ArXiv_DataciteFallbackTest extends TestCase
{
    private const ARXIV_ID = '2602.07473';

    /** @var array<int, array<string, mixed>> requests sent to the mocked DataCite API */
    private array $history = [];

    protected function tearDown(): void
    {
        Episciences_Repositories_ArXiv_DataciteFallback::setHttpClient(null);
        Episciences_Repositories_ArXiv_DataciteFallback::setEnabled(null);
    }

    public function testFetchBuildsAnArxivLikeRecord(): void
    {
        $this->dataciteResponds(new Response(200, [], json_encode(['data' => ['attributes' => self::attributes()]])));

        $fetched = Episciences_Repositories_ArXiv_DataciteFallback::fetch(self::ARXIV_ID);

        $xml = new DOMDocument();
        self::assertTrue($xml->loadXML($fetched['record']));
        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');

        self::assertSame('oai:arXiv.org:' . self::ARXIV_ID, $xpath->evaluate('string(/record/header/identifier)'));
        self::assertSame('2026-11-09', $xpath->evaluate('string(/record/header/datestamp)'));
        self::assertSame('A study of posterior-deterministic models & their values', $xpath->evaluate('string(//dc:title)'));
        self::assertSame(['Doe, Jane', 'Roe, Richard', 'Example Consortium'], $this->values($xpath, '//dc:creator'));
        self::assertSame(['Artificial Intelligence', 'Formal Languages and Automata Theory'], $this->values($xpath, '//dc:subject'));
        self::assertSame(['We study a class of models.'], $this->values($xpath, '//dc:description'));
        self::assertSame(['2026-02-07', '2026-11-06'], $this->values($xpath, '//dc:date'));
        self::assertSame(['text'], $this->values($xpath, '//dc:type'));
        self::assertSame(
            ['https://arxiv.org/abs/' . self::ARXIV_ID, 'http://arxiv.org/abs/' . self::ARXIV_ID],
            $this->values($xpath, '//dc:identifier')
        );

        self::assertSame([1, 2, 3], $fetched['versions']);
        self::assertSame(3, $fetched['latestVersion']);
    }

    public function testFetchRequestsTheArxivDoi(): void
    {
        $this->dataciteResponds(new Response(200, [], json_encode(['data' => ['attributes' => self::attributes()]])));

        Episciences_Repositories_ArXiv_DataciteFallback::fetch(self::ARXIV_ID);

        self::assertCount(1, $this->history);
        /** @var Request $request */
        $request = $this->history[0]['request'];
        self::assertSame('https://api.datacite.org/dois/10.48550/arxiv.' . self::ARXIV_ID, (string)$request->getUri());
        self::assertSame(Episciences_Repositories_ArXiv_DataciteFallback::REQUEST_TIMEOUT, $this->history[0]['options']['timeout']);
    }

    public function testFetchAcceptsAnOldStyleIdentifier(): void
    {
        $this->dataciteResponds(new Response(200, [], json_encode(['data' => ['attributes' => self::attributes()]])));

        $fetched = Episciences_Repositories_ArXiv_DataciteFallback::fetch('math/0601001');

        self::assertStringContainsString('<identifier>oai:arXiv.org:math/0601001</identifier>', $fetched['record']);
    }

    public function testUnknownDoiMeansUnknownDocument(): void
    {
        $this->dataciteResponds(new Response(404, [], '{"errors":[{"status":"404","title":"The resource you are looking for doesn\'t exist."}]}'));

        $this->expectException(Ccsd_Error::class);
        $this->expectExceptionMessage(Ccsd_Error::ID_DOES_NOT_EXIST_CODE);
        Episciences_Repositories_ArXiv_DataciteFallback::fetch(self::ARXIV_ID);
    }

    public function testMalformedIdentifierIsNotRequested(): void
    {
        $this->dataciteResponds();

        try {
            Episciences_Repositories_ArXiv_DataciteFallback::fetch('../../heartbeat');
            self::fail('A malformed identifier must raise a Ccsd_Error');
        } catch (Ccsd_Error $e) {
            self::assertSame(Ccsd_Error::ID_DOES_NOT_EXIST_CODE, $e->getMessage());
        }

        self::assertSame([], $this->history);
    }

    public function testUnavailableDataciteIsReported(): void
    {
        $this->dataciteResponds(new Response(503, [], 'Service Unavailable'));

        try {
            Episciences_Repositories_ArXiv_DataciteFallback::fetch(self::ARXIV_ID);
            self::fail('An HTTP 503 answer must raise a Ccsd_Error');
        } catch (Ccsd_Error $e) {
            self::assertNotSame(Ccsd_Error::ID_DOES_NOT_EXIST_CODE, $e->getMessage());
            self::assertSame(503, $e->getCode());
        }
    }

    public function testUnreachableDataciteIsReported(): void
    {
        $this->dataciteResponds(new ConnectException('Connection timed out', new Request('GET', 'https://api.datacite.org/')));

        $this->expectException(Ccsd_Error::class);
        $this->expectExceptionMessage('DataCite request failed');
        Episciences_Repositories_ArXiv_DataciteFallback::fetch(self::ARXIV_ID);
    }

    public function testInvalidJsonIsReported(): void
    {
        $this->dataciteResponds(new Response(200, [], '<html>maintenance</html>'));

        $this->expectException(Ccsd_Error::class);
        $this->expectExceptionMessage('Invalid DataCite response');
        Episciences_Repositories_ArXiv_DataciteFallback::fetch(self::ARXIV_ID);
    }

    public function testRecordWithoutTitleIsRejected(): void
    {
        $attributes = self::attributes();
        $attributes['titles'] = [];
        $this->dataciteResponds(new Response(200, [], json_encode(['data' => ['attributes' => $attributes]])));

        $this->expectException(Ccsd_Error::class);
        $this->expectExceptionMessage('Invalid DataCite response');
        Episciences_Repositories_ArXiv_DataciteFallback::fetch(self::ARXIV_ID);
    }

    public function testMinimalRecordFallsBackToThePublicationYear(): void
    {
        $elements = Episciences_Repositories_ArXiv_DataciteFallback::toOaiDcElements(
            ['titles' => [['title' => 'Only a title']], 'publicationYear' => 2024],
            self::ARXIV_ID
        );

        self::assertSame(['2024'], $elements['body']['date']);
        self::assertArrayNotHasKey('creator', $elements['body']);
        self::assertArrayNotHasKey('description', $elements['body']);
        self::assertArrayNotHasKey('datestamp', $elements['headers']);
    }

    public function testVersionsFallBackToTheVersionField(): void
    {
        self::assertSame([1], Episciences_Repositories_ArXiv_DataciteFallback::extractVersions(['version' => '1']));
        self::assertSame([], Episciences_Repositories_ArXiv_DataciteFallback::extractVersions(['version' => 'draft']));
    }

    public function testHookReturnsTheRecordAndItsSource(): void
    {
        Episciences_Repositories_ArXiv_DataciteFallback::setEnabled(true);
        $this->dataciteResponds(new Response(200, [], json_encode(['data' => ['attributes' => self::attributes()]])));

        $fallback = Episciences_Repositories_ArXiv_SubmissionHooks::hookFallbackRecord(['identifier' => self::ARXIV_ID]);

        self::assertSame(Episciences_Repositories_ArXiv_DataciteFallback::SOURCE, $fallback['source']);
        self::assertStringContainsString('oai_dc:dc', $fallback['record']);
        self::assertSame(3, $fallback['latestVersion']);
    }

    public function testHookDoesNothingWhenSwitchedOff(): void
    {
        Episciences_Repositories_ArXiv_DataciteFallback::setEnabled(false);
        $this->dataciteResponds();

        self::assertSame([], Episciences_Repositories_ArXiv_SubmissionHooks::hookFallbackRecord(['identifier' => self::ARXIV_ID]));
        self::assertSame([], $this->history);
    }

    public function testFallbackIsEnabledByDefault(): void
    {
        self::assertTrue(Episciences_Repositories_ArXiv_DataciteFallback::isEnabled());
    }

    public function testOaiRequestOptionsAllowTwoShortAttempts(): void
    {
        self::assertSame(
            ['maxAttempts' => 2, 'requestTimeout' => 15, 'connectTimeout' => 5],
            Episciences_Repositories_ArXiv_SubmissionHooks::hookOaiRequestOptions()
        );
    }

    /**
     * Attributes of a DataCite record of an arXiv document (fictitious), with three versions
     * @return array<string, mixed>
     */
    public static function attributes(): array
    {
        return [
            'titles' => [
                ['title' => 'A study of posterior-deterministic models & their values'],
                ['title' => 'Une étude', 'titleType' => 'TranslatedTitle', 'lang' => 'fr'],
            ],
            'creators' => [
                ['name' => 'Doe, Jane', 'givenName' => 'Jane', 'familyName' => 'Doe', 'nameType' => 'Personal'],
                ['name' => 'Roe, Richard', 'givenName' => 'Richard', 'familyName' => 'Roe', 'nameType' => 'Personal'],
                ['name' => 'Example Consortium', 'nameType' => 'Organizational'],
            ],
            'subjects' => [
                ['subject' => 'Artificial Intelligence (cs.AI)', 'subjectScheme' => 'arXiv', 'lang' => 'en'],
                ['subject' => 'Formal Languages and Automata Theory (cs.FL)', 'subjectScheme' => 'arXiv', 'lang' => 'en'],
                ['subject' => 'Artificial Intelligence (cs.AI)', 'subjectScheme' => 'arXiv', 'lang' => 'en'],
                ['subject' => 'FOS: Computer and information sciences', 'subjectScheme' => 'Fields of Science and Technology (FOS)'],
            ],
            'descriptions' => [
                ['description' => 'Comment: 12 pages', 'descriptionType' => 'Other'],
                ['description' => 'We study a class of models.', 'descriptionType' => 'Abstract'],
            ],
            'dates' => [
                ['date' => '2026-02-07T10:09:41Z', 'dateType' => 'Submitted', 'dateInformation' => 'v1'],
                ['date' => '2026-02-10T01:32:27Z', 'dateType' => 'Updated', 'dateInformation' => 'v1'],
                ['date' => '2026-11-06T14:35:09Z', 'dateType' => 'Submitted', 'dateInformation' => 'v3'],
                ['date' => '2026-04-22T09:29:15Z', 'dateType' => 'Submitted', 'dateInformation' => 'v2'],
                ['date' => '2026-02', 'dateType' => 'Available', 'dateInformation' => 'v1'],
            ],
            'publicationYear' => 2026,
            'version' => '3',
            'updated' => '2026-11-09T05:24:31.000Z',
            'url' => 'https://arxiv.org/abs/' . self::ARXIV_ID,
        ];
    }

    /**
     * @param Response|Throwable ...$responses
     */
    private function dataciteResponds(...$responses): void
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $this->history = [];
        $stack->push(\GuzzleHttp\Middleware::history($this->history));
        Episciences_Repositories_ArXiv_DataciteFallback::setHttpClient(new Client(['handler' => $stack]));
    }

    /**
     * @return string[]
     */
    private function values(DOMXPath $xpath, string $query): array
    {
        $values = [];
        foreach ($xpath->query($query) as $node) {
            $values[] = $node->textContent;
        }
        return $values;
    }
}
