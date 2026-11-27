<?php

declare(strict_types=1);

namespace unit\library\Episciences\Submit;

use Ccsd_Error;
use Episciences\Hal\HalTeiSource;
use Episciences_Hal_TeiCacheManager;
use Episciences_Paper;
use Episciences_Repositories;
use Episciences_Repositories_NewVersionSpy_Hooks;
use Episciences_Submit;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Repository processing of an initial submission, after the paper is saved and the notifications are sent:
 * the repository hooks (Episciences_Submit::processRepositoryHooks(), run by processSavedPaper()) and the HAL datasets request
 * (Episciences_Submit::datasetsProcessing()).
 */
final class RepositoryHooksTest extends TestCase
{
    private const SPY_REPO_ID = 9998;
    /** Fake HAL repository: the label contains "HAL" */
    private const HAL_REPO_ID = 9997;
    private const DOCID = 1234;

    /** @var array<mixed> */
    private array $previousRepositories;

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 3) . '/modules/support/NewVersionSpyHooks.php';
    }

    protected function setUp(): void
    {
        $repositories = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $repositories->setAccessible(true);
        $this->previousRepositories = $repositories->getValue();
        $repositories->setValue(null, [
            self::SPY_REPO_ID => [
                Episciences_Repositories::REPO_LABEL => 'New Version Spy',
                Episciences_Repositories::REPO_TYPE => Episciences_Repositories::TYPE_PAPERS_REPOSITORY,
            ],
            // The files and the linked data hooks of Zenodo use the same repository record
            (int)Episciences_Repositories::ZENODO_REPO_ID => [
                Episciences_Repositories::REPO_LABEL => 'New Version Spy',
                Episciences_Repositories::REPO_TYPE => Episciences_Repositories::TYPE_PAPERS_REPOSITORY,
            ],
            self::HAL_REPO_ID => [
                Episciences_Repositories::REPO_LABEL => 'HAL Spy',
                Episciences_Repositories::REPO_TYPE => Episciences_Repositories::TYPE_PAPERS_REPOSITORY,
                Episciences_Repositories::REPO_BASEURL => 'https://api.hal.test/oai/hal/',
                Episciences_Repositories::REPO_IDENTIFIER => 'oai:HAL:%%IDv%%VERSION',
            ],
        ]);

        Episciences_Repositories_NewVersionSpy_Hooks::$calls = [];
        Episciences_Repositories_NewVersionSpy_Hooks::$exceptions = [];
        Episciences_Repositories_NewVersionSpy_Hooks::$loadedRecord = [];
        Episciences_Hal_TeiCacheManager::setCachePool(new ArrayAdapter(0, false));
    }

    protected function tearDown(): void
    {
        Episciences_Hal_TeiCacheManager::setCachePool(null);
        Episciences_Hal_TeiCacheManager::setHttpClient(null);
        $repositories = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $repositories->setAccessible(true);
        $repositories->setValue(null, $this->previousRepositories);

        Episciences_Repositories_NewVersionSpy_Hooks::$calls = [];
        Episciences_Repositories_NewVersionSpy_Hooks::$exceptions = [];
        Episciences_Repositories_NewVersionSpy_Hooks::$loadedRecord = [];
    }

    public function testFilesFetchFailureDoesNotSkipTheLinkedData(): void
    {
        Episciences_Repositories_NewVersionSpy_Hooks::$exceptions['hookFilesProcessing'] = new Ccsd_Error('files unavailable');

        $errors = $this->collectErrors(fn() => self::assertFalse($this->processRepositoryHooks(), 'the failure is returned'));

        self::assertArrayHasKey('hookLinkedDataProcessing', Episciences_Repositories_NewVersionSpy_Hooks::$calls, 'the linked data hook still runs');
        self::assertReported('hookFilesProcessing', 'Ccsd_Error: files unavailable', $errors);
    }

    /**
     * Zenodo, BAOBAB: the linked data hook would request the same record again, and the author wait for it twice
     */
    public function testUnloadedRecordIsNotRequestedAgainForTheLinkedData(): void
    {
        Episciences_Repositories_NewVersionSpy_Hooks::$exceptions['hookFilesProcessing'] = new Ccsd_Error('Operation timed out');

        $errors = $this->collectErrors(
            fn() => self::assertFalse(Episciences_Submit::processRepositoryHooks($this->spyPaper((int)Episciences_Repositories::ZENODO_REPO_ID)))
        );

        self::assertArrayNotHasKey('hookLinkedDataProcessing', Episciences_Repositories_NewVersionSpy_Hooks::$calls);
        self::assertCount(2, $errors, 'both steps are reported as failed');
        self::assertStringContainsString('hookFilesProcessing', $errors[0]);
        self::assertStringContainsString('hookLinkedDataProcessing', $errors[1]);
        self::assertStringContainsString('Not requested again', $errors[1]);
    }

    /**
     * The record was loaded, the files could not be stored: the linked data hook still runs
     */
    public function testFilesStorageFailureDoesNotSkipTheLinkedDataOfTheSameRecord(): void
    {
        Episciences_Repositories_NewVersionSpy_Hooks::$exceptions['hookFilesProcessing'] = new \RuntimeException('Files synchronization error');

        $errors = $this->collectErrors(
            fn() => self::assertFalse(Episciences_Submit::processRepositoryHooks($this->spyPaper((int)Episciences_Repositories::ZENODO_REPO_ID)))
        );

        self::assertArrayHasKey('hookLinkedDataProcessing', Episciences_Repositories_NewVersionSpy_Hooks::$calls);
        self::assertCount(1, $errors);
    }

    public function testLinkedDataFetchFailureIsReportedNotThrown(): void
    {
        Episciences_Repositories_NewVersionSpy_Hooks::$exceptions['hookLinkedDataProcessing'] = new Ccsd_Error('linked data unavailable');

        $errors = $this->collectErrors(fn() => self::assertFalse($this->processRepositoryHooks()));

        self::assertArrayHasKey('hookFilesProcessing', Episciences_Repositories_NewVersionSpy_Hooks::$calls);
        self::assertReported('hookLinkedDataProcessing', 'Ccsd_Error: linked data unavailable', $errors);
    }

    public function testHookTypeErrorIsReportedNotThrown(): void
    {
        // As Zenodo reading $file['checksum'] on a string of a tampered submitted enrichment
        Episciences_Repositories_NewVersionSpy_Hooks::$exceptions['hookFilesProcessing'] = new \TypeError('Cannot access offset of type string on string');

        $errors = $this->collectErrors(fn() => self::assertFalse($this->processRepositoryHooks()));

        self::assertArrayHasKey('hookLinkedDataProcessing', Episciences_Repositories_NewVersionSpy_Hooks::$calls, 'the linked data hook still runs');
        // The class tells a programming error from a repository failure
        self::assertReported('hookFilesProcessing', 'TypeError: Cannot access offset of type string on string', $errors);
    }

    public function testSuccessfulHooksReturnTrueAndReportNothing(): void
    {
        $errors = $this->collectErrors(fn() => self::assertTrue($this->processRepositoryHooks()));

        self::assertSame([], $errors);
    }

    public function testSavedPaperWithoutEnrichmentRunsTheHooks(): void
    {
        $errors = $this->collectErrors(fn() => self::assertTrue(Episciences_Submit::processSavedPaper($this->spyPaper(), [])));

        self::assertSame([], $errors);
        self::assertArrayHasKey('hookFilesProcessing', Episciences_Repositories_NewVersionSpy_Hooks::$calls);
        self::assertArrayHasKey('hookLinkedDataProcessing', Episciences_Repositories_NewVersionSpy_Hooks::$calls);
    }

    public function testSavedPaperHookFailureIsReturned(): void
    {
        Episciences_Repositories_NewVersionSpy_Hooks::$exceptions['hookLinkedDataProcessing'] = new Ccsd_Error('linked data unavailable');

        $errors = $this->collectErrors(fn() => self::assertFalse(Episciences_Submit::processSavedPaper($this->spyPaper(), [])));

        self::assertReported('hookLinkedDataProcessing', 'Ccsd_Error: linked data unavailable', $errors);
    }

    public function testSavedPaperEnrichmentFailureIsReturned(): void
    {
        // Not valid UTF-8: the citations cannot be encoded, before any database access
        $enrichment = [\Episciences_Repositories_Common::CITATIONS => "\xB1\x31"];

        $errors = $this->collectErrors(fn() => self::assertFalse(Episciences_Submit::processSavedPaper($this->spyPaper(), $enrichment)));

        self::assertReported('enrichmentProcess', 'citations (JsonException', $errors);
        self::assertArrayHasKey('hookLinkedDataProcessing', Episciences_Repositories_NewVersionSpy_Hooks::$calls, 'the hooks still run');
    }

    public function testEnrichmentProcessReportsAFailedEnrichmentWithoutThrowing(): void
    {
        // Also called outside the submission (updateRecordData(), inbox): a warning per failed enrichment, no exception
        $enrichment = [\Episciences_Repositories_Common::CITATIONS => "\xB1\x31"];

        $errors = $this->collectErrors(
            fn() => self::assertSame(0, Episciences_Submit::enrichmentProcess($this->spyPaper(), $enrichment))
        );

        self::assertCount(1, $errors);
        self::assertStringContainsString('Enrichment citations failed for docid ' . self::DOCID, $errors[0]);
        self::assertStringContainsString('JsonException', $errors[0]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unexpectedHalPayloadProvider(): iterable
    {
        yield 'empty body' => [''];
        yield 'not XML' => ['{"response":{"docs":[{"swhidId_s":"swh:1:dir:abc"}]}}'];
        yield 'truncated XML' => ['<TEI xmlns="http://www.tei-c.org/ns/1.0"><text><body><listBibl><biblFull>'];
        yield 'no document' => ['<TEI xmlns="http://www.tei-c.org/ns/1.0"><text><body><listBibl/></body></text></TEI>'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unexpectedHalPayloadProvider')]
    public function testUnexpectedHalPayloadYieldsNoDatasets(string $body): void
    {
        $requests = [];
        $this->halServes($requests, new Response(200, [], $body));

        // Not a TypeError: datasetsProcessing() is also called outside processRepositoryHooks() (inbox, PapersManager)
        $errors = $this->collectErrors(
            fn() => self::assertSame(0, Episciences_Submit::datasetsProcessing($this->halPaper('hal-01234567')))
        );

        self::assertSame([], $errors);
    }

    /**
     * The HAL search index only holds the latest version of a document: the TEI of the paper version is read
     * from the OAI-PMH server of its repository, so that the linked data are those of the published version
     */
    public function testHalRequestAsksTheTeiOfThePaperVersionWithAnEncodedIdentifier(): void
    {
        $requests = [];
        $this->halServes($requests, new Response(200, [], '<TEI xmlns="http://www.tei-c.org/ns/1.0"/>'));

        $affectedRows = Episciences_Submit::datasetsProcessing($this->halPaper('hal-01234567&rows=1000'));

        self::assertSame(0, $affectedRows);
        self::assertCount(1, $requests);
        self::assertSame(
            'https://api.hal.test/oai/hal?verb=GetRecord&metadataPrefix=xml-tei&identifier=oai%3AHAL%3Ahal-01234567%26rows%3D1000v1',
            (string)$requests[0]['request']->getUri(),
            'the identifier is encoded'
        );
    }

    /**
     * Update of a record to a newer version of the archive: the linked data are those of that version,
     * not of the version the paper still holds
     */
    public function testHalRequestAsksTheVersionGivenInsteadOfThePaperVersion(): void
    {
        $requests = [];
        $this->halServes($requests, new Response(200, [], '<TEI xmlns="http://www.tei-c.org/ns/1.0"/>'));

        Episciences_Submit::datasetsProcessing(
            $this->halPaper('hal-01234567'),
            false,
            new HalTeiSource(self::HAL_REPO_ID, 'hal-01234567', 3)
        );

        self::assertCount(1, $requests);
        self::assertStringEndsWith('identifier=oai%3AHAL%3Ahal-01234567v3', (string)$requests[0]['request']->getUri());
    }

    public function testHalResponseWithoutDocsDoesNotFail(): void
    {
        $requests = [];
        $this->halServes($requests, new Response(200, [], '<TEI xmlns="http://www.tei-c.org/ns/1.0"><text><body><listBibl/></body></text></TEI>'));

        $errors = $this->collectErrors(
            fn() => self::assertSame(0, Episciences_Submit::datasetsProcessing($this->halPaper('hal-01234567')))
        );

        self::assertSame([], $errors);
    }

    public function testHalApiFailureIsReported(): void
    {
        $requests = [];
        $this->halServes($requests, new Response(503, [], 'Service Unavailable'));

        $errors = $this->collectErrors(
            fn() => self::assertSame(0, Episciences_Submit::datasetsProcessing($this->halPaper('hal-01234567')))
        );

        self::assertCount(1, $errors);
        self::assertStringContainsString('503', $errors[0]);
    }

    public function testNonHalRepositoryDoesNotQueryTheApi(): void
    {
        $requests = [];
        $this->halServes($requests);
        $paper = new Episciences_Paper(['docid' => self::DOCID, 'repoid' => self::SPY_REPO_ID, 'identifier' => '42']);

        self::assertSame(0, Episciences_Submit::datasetsProcessing($paper));
        self::assertSame([], $requests);
    }

    /**
     * @param array<mixed> $requests receives the Guzzle history of the requests sent
     * @param-out array<mixed>|\ArrayAccess<int, array<mixed>> $requests
     */
    private function halServes(array &$requests, Response ...$responses): void
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($requests));

        Episciences_Hal_TeiCacheManager::setHttpClient(new Client(['handler' => $stack]));
    }

    private function halPaper(string $identifier): Episciences_Paper
    {
        return new Episciences_Paper([
            'docid' => self::DOCID,
            'repoid' => self::HAL_REPO_ID,
            'identifier' => $identifier,
            'version' => 1,
        ]);
    }

    private function processRepositoryHooks(): bool
    {
        return Episciences_Submit::processRepositoryHooks($this->spyPaper());
    }

    private function spyPaper(int $repoId = self::SPY_REPO_ID): Episciences_Paper
    {
        return new Episciences_Paper([
            'docid' => self::DOCID,
            'paperid' => self::DOCID,
            'repoid' => $repoId,
            'identifier' => '42',
            'version' => 1,
        ]);
    }

    /**
     * The failure is reported once, with the step and the paper (to re-run its enrichment)
     *
     * @param list<string> $errors
     */
    private static function assertReported(string $step, string $message, array $errors): void
    {
        self::assertCount(1, $errors, 'the failure is reported, not thrown');
        self::assertStringContainsString($step, $errors[0]);
        self::assertStringContainsString('docid ' . self::DOCID, $errors[0]);
        self::assertStringContainsString('repository ' . self::SPY_REPO_ID, $errors[0]);
        self::assertStringContainsString($message, $errors[0]);
    }

    /**
     * @return list<string> messages of the errors triggered by $run
     */
    private function collectErrors(callable $run): array
    {
        $errors = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$errors): bool {
            $errors[] = $errstr;
            return true;
        });

        try {
            $run();
        } finally {
            restore_error_handler();
        }

        return $errors;
    }
}
