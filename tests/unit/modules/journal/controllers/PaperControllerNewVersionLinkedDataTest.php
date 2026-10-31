<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Ccsd_Error;
use Episciences_Paper;
use Episciences_Repositories;
use Episciences_Repositories_NewVersionSpy_Hooks;
use PaperController;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use unit\modules\ControllerTestCase;

/**
 * Repository hooks run when a new version is saved (PaperController::processRepositoryHooks()).
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
    }

    protected function tearDown(): void
    {
        $repositories = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $repositories->setAccessible(true);
        $repositories->setValue(null, $this->previousRepositories);

        Episciences_Repositories_NewVersionSpy_Hooks::$calls = [];
        Episciences_Repositories_NewVersionSpy_Hooks::$exceptions = [];
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

    public function testFilesHookStillGetsTheEnrichmentFiles(): void
    {
        $files = [['key' => 'article.pdf']];

        $this->processRepositoryHooks(['files' => $files]);

        $filesParams = Episciences_Repositories_NewVersionSpy_Hooks::$calls['hookFilesProcessing'] ?? null;

        self::assertNotNull($filesParams, 'the files hook runs');
        self::assertSame($files, $filesParams['files']);
        self::assertArrayNotHasKey('files', Episciences_Repositories_NewVersionSpy_Hooks::$calls['hookLinkedDataProcessing']);
    }

    public function testLinkedDataFetchFailureDoesNotStopTheSave(): void
    {
        Episciences_Repositories_NewVersionSpy_Hooks::$exceptions['hookLinkedDataProcessing'] = new Ccsd_Error('linked data unavailable');

        $notices = $this->collectNotices(fn() => $this->processRepositoryHooks([]));

        self::assertArrayHasKey('hookFilesProcessing', Episciences_Repositories_NewVersionSpy_Hooks::$calls);
        self::assertArrayHasKey('hookLinkedDataProcessing', Episciences_Repositories_NewVersionSpy_Hooks::$calls);
        self::assertSame(['linked data unavailable'], $notices, 'the failure is reported, not thrown');
    }

    public function testFilesFetchFailureDoesNotSkipTheLinkedData(): void
    {
        Episciences_Repositories_NewVersionSpy_Hooks::$exceptions['hookFilesProcessing'] = new Ccsd_Error('files unavailable');

        $notices = $this->collectNotices(fn() => $this->processRepositoryHooks([]));

        self::assertArrayHasKey('hookLinkedDataProcessing', Episciences_Repositories_NewVersionSpy_Hooks::$calls, 'the linked data hook still runs');
        self::assertSame(['files unavailable'], $notices, 'the failure is reported, not thrown');
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
        $notices = $this->collectNotices(fn() => $this->processRepositoryHooks([]));

        self::assertSame([], $notices);
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
     * @param array<string, mixed> $enrichment
     */
    private function processRepositoryHooks(array $enrichment, int $repoId = self::REPO_ID): void
    {
        $newPaper = new Episciences_Paper([
            'docid' => self::DOCID,
            'paperid' => self::DOCID,
            'repoid' => $repoId,
            'version' => 2,
            'identifier' => self::IDENTIFIER,
            'rvid' => RVID,
        ]);

        $method = new ReflectionMethod(PaperController::class, 'processRepositoryHooks');
        $method->setAccessible(true);
        $method->invoke($this->controller, $newPaper, $enrichment);
    }
}
