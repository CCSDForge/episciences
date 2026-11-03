<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Ccsd_Error;
use Episciences_Paper;
use Episciences_Repositories;
use Episciences_Repositories_NewVersionSpy_Hooks;
use Episciences_Submit;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PaperController;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use unit\modules\ControllerTestCase;
use Zend_Registry;

/**
 * Repository hooks run when a new version is saved (PaperController::processEnrichmentAndFiles(),
 * which delegates to Episciences_Submit::processSavedPaper(), as an initial submission).
 *
 * An earlier version passed the result of hookFilesProcessing as 'response' to
 * hookLinkedDataProcessing. That result is not the repository record, so the linked data hook
 * read no related identifiers and the new version of a Zenodo paper got no linked data (BAOBAB
 * related identifiers also come with the submitted enrichment, stored by enrichmentProcess()).
 */
final class PaperControllerNewVersionLinkedDataTest extends ControllerTestCase
{
    private const REPO_ID = 9998;
    /** Fake HAL-like repository (the label contains "HAL"), without hooks, with an API that refuses connections */
    private const HAL_REPO_ID = 9997;
    private const HAL_API_URL = 'http://127.0.0.1:1';
    private const DOCID = 1234;
    private const IDENTIFIER = '42';

    private PaperController $controller;

    /** @var array<mixed> */
    private array $previousRepositories;

    private bool $hadLogger = false;
    private mixed $previousLogger = null;

    public static function setUpBeforeClass(): void
    {
        self::loadController('journal', 'PaperController');
        require_once dirname(__DIR__, 2) . '/support/NewVersionSpyHooks.php';
    }

    protected function setUp(): void
    {
        $this->controller = (new ReflectionClass(PaperController::class))->newInstanceWithoutConstructor();

        $repositories = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $repositories->setAccessible(true);
        $this->previousRepositories = $repositories->getValue();
        $repositories->setValue(null, [
            self::REPO_ID => [
                Episciences_Repositories::REPO_LABEL => 'New Version Spy',
                Episciences_Repositories::REPO_TYPE => Episciences_Repositories::TYPE_PAPERS_REPOSITORY,
            ],
            self::HAL_REPO_ID => [
                Episciences_Repositories::REPO_LABEL => 'HAL Spy',
                Episciences_Repositories::REPO_TYPE => Episciences_Repositories::TYPE_PAPERS_REPOSITORY,
                Episciences_Repositories::REPO_API_URL => self::HAL_API_URL,
            ],
        ]);

        Episciences_Repositories_NewVersionSpy_Hooks::$calls = [];
        Episciences_Repositories_NewVersionSpy_Hooks::$exceptions = [];
        Episciences_Repositories_NewVersionSpy_Hooks::$loadedRecord = [];

        // The cover letter upload (Uploader) reports a missing application logger as a notice:
        // these tests count the notices, they must not depend on what an earlier test left in the registry
        $this->hadLogger = Zend_Registry::isRegistered('appLogger');
        $this->previousLogger = $this->hadLogger ? Zend_Registry::get('appLogger') : null;
        Zend_Registry::set('appLogger', new Logger('test', [new NullHandler()]));
    }

    protected function tearDown(): void
    {
        $repositories = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $repositories->setAccessible(true);
        $repositories->setValue(null, $this->previousRepositories);

        if ($this->hadLogger) {
            Zend_Registry::set('appLogger', $this->previousLogger);
        } else {
            Zend_Registry::getInstance()->offsetUnset('appLogger');
        }

        Episciences_Repositories_NewVersionSpy_Hooks::$calls = [];
        Episciences_Repositories_NewVersionSpy_Hooks::$exceptions = [];
        Episciences_Repositories_NewVersionSpy_Hooks::$loadedRecord = [];
    }

    public function testLinkedDataHookLoadsTheRecordItself(): void
    {
        $this->processRepositoryHooks([]);

        $linkedDataParams = Episciences_Repositories_NewVersionSpy_Hooks::$calls['hookLinkedDataProcessing'] ?? null;

        self::assertNotNull($linkedDataParams, 'the linked data hook runs');
        self::assertArrayNotHasKey('response', $linkedDataParams, 'the files hook result is not passed as the repository record');
        self::assertSame(self::REPO_ID, $linkedDataParams['repoId']);
        self::assertSame(self::IDENTIFIER, $linkedDataParams['identifier']);
        self::assertSame(self::DOCID, $linkedDataParams['docId']);
    }

    public function testFilesHookLoadsTheFilesFromTheRepository(): void
    {
        // The hook loads the files from the repository
        $this->processRepositoryHooks(['files' => [['key' => 'article.pdf', 'links' => ['self' => 'https://example.test/a.pdf']]]]);

        $filesParams = Episciences_Repositories_NewVersionSpy_Hooks::$calls['hookFilesProcessing'] ?? null;

        self::assertNotNull($filesParams, 'the files hook runs');
        self::assertArrayNotHasKey('files', $filesParams);
        self::assertEquals(2, $filesParams['version'], 'the version of the record to load');
    }

    public function testLinkedDataHookReusesTheRecordTheFilesHookLoaded(): void
    {
        $record = ['metadata' => ['related_identifiers' => []]];
        Episciences_Repositories_NewVersionSpy_Hooks::$loadedRecord = $record;

        $this->processRepositoryHooks([]);

        $linkedDataParams = Episciences_Repositories_NewVersionSpy_Hooks::$calls['hookLinkedDataProcessing'];
        self::assertSame($record, $linkedDataParams['response'], 'the record only, not the files hook parameters');
        self::assertArrayNotHasKey('affectedRows', $linkedDataParams);
    }

    public function testSubmittedRelatedIdentifiersThatAreNotAListAreIgnored(): void
    {
        // processDatasets() takes an array: a string used to end in a TypeError after the save
        $notices = $this->collectNotices(
            fn() => self::assertTrue($this->processRepositoryHooks(['relatedIdentifiers' => 'x']))
        );

        self::assertSame([], $notices);
    }

    public function testLinkedDataFetchFailureDoesNotStopTheSave(): void
    {
        Episciences_Repositories_NewVersionSpy_Hooks::$exceptions['hookLinkedDataProcessing'] = new Ccsd_Error('linked data unavailable');

        $notices = $this->collectNotices(fn() => self::assertFalse($this->processRepositoryHooks([]), 'the failure is returned'));

        self::assertArrayHasKey('hookFilesProcessing', Episciences_Repositories_NewVersionSpy_Hooks::$calls);
        self::assertArrayHasKey('hookLinkedDataProcessing', Episciences_Repositories_NewVersionSpy_Hooks::$calls);
        self::assertReported('hookLinkedDataProcessing', 'linked data unavailable', $notices);
    }

    public function testFilesFetchFailureDoesNotSkipTheLinkedData(): void
    {
        Episciences_Repositories_NewVersionSpy_Hooks::$exceptions['hookFilesProcessing'] = new Ccsd_Error('files unavailable');

        $notices = $this->collectNotices(fn() => self::assertFalse($this->processRepositoryHooks([]), 'the failure is returned'));

        self::assertArrayHasKey('hookLinkedDataProcessing', Episciences_Repositories_NewVersionSpy_Hooks::$calls, 'the linked data hook still runs');
        self::assertReported('hookFilesProcessing', 'files unavailable', $notices);
    }

    public function testHalNewVersionQueriesTheDatasets(): void
    {
        // datasetsProcessing() reports the refused connection: it did query the HAL API
        $notices = $this->collectNotices(fn() => $this->processRepositoryHooks([], self::HAL_REPO_ID));

        self::assertCount(1, $notices);
        self::assertStringContainsString('127.0.0.1', $notices[0]);
    }

    public function testRepositoryWithoutApiDoesNotQueryTheDatasets(): void
    {
        $notices = $this->collectNotices(fn() => self::assertTrue($this->processRepositoryHooks([])));

        self::assertSame([], $notices);
    }

    public function testEnrichmentThatIsNotAnArrayIsIgnored(): void
    {
        $newPaper = new Episciences_Paper([
            'docid' => self::DOCID,
            'paperid' => self::DOCID,
            'repoid' => self::REPO_ID,
            'version' => 2,
            'identifier' => self::IDENTIFIER,
            'rvid' => RVID,
        ]);

        $method = new ReflectionMethod(PaperController::class, 'processEnrichmentAndFiles');
        $method->setAccessible(true);
        // Valid JSON, but a scalar: it must not reach enrichmentProcess(), typed array
        $method->invoke($this->controller, $newPaper, [Episciences_Submit::ENRICHMENT_ELEMENT_NAME => '42']);

        $filesParams = Episciences_Repositories_NewVersionSpy_Hooks::$calls['hookFilesProcessing'] ?? null;
        self::assertNotNull($filesParams, 'the repository hooks still run');
        self::assertArrayNotHasKey('files', $filesParams);
    }

    /**
     * @return list<string> messages of the errors triggered by $run
     */
    private function collectNotices(callable $run): array
    {
        $notices = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$notices): bool {
            $notices[] = $errstr;
            return true;
        });

        try {
            $run();
        } finally {
            restore_error_handler();
        }

        return $notices;
    }

    /**
     * Runs the post-save repository processing of a new version, with $enrichment submitted as h_enrichment
     *
     * @param array<string, mixed> $enrichment
     */
    private function processRepositoryHooks(array $enrichment, int $repoId = self::REPO_ID): bool
    {
        $newPaper = new Episciences_Paper([
            'docid' => self::DOCID,
            'paperid' => self::DOCID,
            'repoid' => $repoId,
            'version' => 2,
            'identifier' => self::IDENTIFIER,
            'rvid' => RVID,
        ]);

        $method = new ReflectionMethod(PaperController::class, 'processEnrichmentAndFiles');
        $method->setAccessible(true);
        return $method->invoke($this->controller, $newPaper, [
            Episciences_Submit::ENRICHMENT_ELEMENT_NAME => json_encode($enrichment, JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * The failure is reported once, with the step and the paper (to re-run its enrichment), not thrown
     *
     * @param list<string> $notices
     */
    private static function assertReported(string $step, string $message, array $notices): void
    {
        self::assertCount(1, $notices, 'the failure is reported, not thrown');
        self::assertStringContainsString($step, $notices[0]);
        self::assertStringContainsString('docid ' . self::DOCID, $notices[0]);
        self::assertStringContainsString($message, $notices[0]);
    }
}
