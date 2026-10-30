<?php

declare(strict_types=1);

namespace unit\modules\journal\controllers;

use Episciences_Paper;
use Episciences_Repositories;
use Episciences_Repositories_NewVersionSpy_Hooks;
use PaperController;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use unit\modules\ControllerTestCase;

/**
 * Repository hooks run when a new version is saved (PaperController::processEnrichmentAndFiles()).
 *
 * An earlier version passed the result of hookFilesProcessing as 'response' to
 * hookLinkedDataProcessing. That result is not the repository record, so the linked data hook
 * read no related identifiers and the new version of a Zenodo or BAOBAB paper got no linked data.
 */
final class PaperControllerNewVersionLinkedDataTest extends ControllerTestCase
{
    private const REPO_ID = 9998;
    private const DOCID = 1234;
    private const IDENTIFIER = '10.5281/zenodo.42';

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
        ]);

        Episciences_Repositories_NewVersionSpy_Hooks::$calls = [];
    }

    protected function tearDown(): void
    {
        $repositories = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $repositories->setAccessible(true);
        $repositories->setValue(null, $this->previousRepositories);

        Episciences_Repositories_NewVersionSpy_Hooks::$calls = [];
    }

    public function testLinkedDataHookLoadsTheRecordItself(): void
    {
        $this->processEnrichmentAndFiles([]);

        $linkedDataParams = Episciences_Repositories_NewVersionSpy_Hooks::$calls['hookLinkedDataProcessing'] ?? null;

        self::assertNotNull($linkedDataParams, 'the linked data hook runs');
        self::assertArrayNotHasKey('response', $linkedDataParams, 'the files hook result is not passed as the repository record');
        self::assertSame(
            ['repoId' => self::REPO_ID, 'identifier' => self::IDENTIFIER, 'docId' => self::DOCID],
            $linkedDataParams
        );
    }

    public function testFilesHookStillGetsTheEnrichmentFiles(): void
    {
        $files = [['key' => 'article.pdf']];

        $this->processEnrichmentAndFiles(['h_enrichment' => json_encode(['files' => $files], JSON_THROW_ON_ERROR)]);

        $filesParams = Episciences_Repositories_NewVersionSpy_Hooks::$calls['hookFilesProcessing'] ?? null;

        self::assertNotNull($filesParams, 'the files hook runs');
        self::assertSame($files, $filesParams['files']);
        self::assertArrayNotHasKey('files', Episciences_Repositories_NewVersionSpy_Hooks::$calls['hookLinkedDataProcessing']);
    }

    /**
     * @param array<string, mixed> $post
     */
    private function processEnrichmentAndFiles(array $post): void
    {
        $newPaper = new Episciences_Paper([
            'docid' => self::DOCID,
            'paperid' => self::DOCID,
            'repoid' => self::REPO_ID,
            'identifier' => self::IDENTIFIER,
            'rvid' => RVID,
        ]);

        $method = new ReflectionMethod(PaperController::class, 'processEnrichmentAndFiles');
        $method->setAccessible(true);
        $method->invoke($this->controller, $newPaper, $post);
    }
}
