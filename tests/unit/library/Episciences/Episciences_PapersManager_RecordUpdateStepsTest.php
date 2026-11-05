<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Ccsd_Error;
use Episciences\Paper\LinkedDataNotStoredException;
use Episciences_Paper;
use Episciences_PapersManager;
use Episciences_Repositories;
use Episciences_Repositories_NewVersionSpy_Hooks;
use Episciences_Submit;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

/**
 * Steps of Episciences_PapersManager::updateRecordData():
 * the files and the linked data hooks get the record already loaded, each step returns its own rows,
 * and a failed step is reported and named without stopping the following ones (Episciences_Submit::runPostSaveSteps()).
 */
final class Episciences_PapersManager_RecordUpdateStepsTest extends TestCase
{
    private const SPY_REPO_ID = 9998;
    private const DOCID = 1234;

    /** @var array<mixed> */
    private array $previousRepositories;

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 3) . '/unit/modules/support/NewVersionSpyHooks.php';
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
        ]);

        Episciences_Repositories_NewVersionSpy_Hooks::reset();
    }

    protected function tearDown(): void
    {
        $repositories = new ReflectionProperty(Episciences_Repositories::class, '_repositories');
        $repositories->setAccessible(true);
        $repositories->setValue(null, $this->previousRepositories);

        Episciences_Repositories_NewVersionSpy_Hooks::reset();
    }

    public function testFilesHookGetsTheVersionTheLoadedRecordAndItsFiles(): void
    {
        $loadedRecord = ['record' => '<record/>', 'enrichment' => []];
        $files = [['key' => 'article.pdf']];

        $affectedRows = $this->invoke('updateRecordDataProcessFilesHook', self::context(), ['files' => $files], $loadedRecord);

        $hookParams = Episciences_Repositories_NewVersionSpy_Hooks::$calls['hookFilesProcessing'];
        // The Dataverse hook requests the record of this version when it has to load it
        self::assertSame(2.0, $hookParams['version']);
        self::assertSame($loadedRecord, $hookParams['response'], 'the record is not requested again');
        self::assertSame($files, $hookParams['files'], 'the files of the record loaded from the repository');
        self::assertSame(1, $affectedRows, 'the rows of this step only');
    }

    public function testFilesHookWithoutLoadedRecordGetsNoResponse(): void
    {
        $this->invoke('updateRecordDataProcessFilesHook', self::context(), [], []);

        $hookParams = Episciences_Repositories_NewVersionSpy_Hooks::$calls['hookFilesProcessing'];
        self::assertArrayNotHasKey('response', $hookParams);
        self::assertArrayNotHasKey('files', $hookParams);
    }

    public function testLinkedDataHookGetsTheLoadedRecordAndReturnsItsOwnRows(): void
    {
        $loadedRecord = ['record' => '<record/>', 'enrichment' => []];

        $affectedRows = $this->invoke('processLinkedDataOrDatasets', $this->paper(), self::context(), $loadedRecord);

        self::assertSame(
            $loadedRecord,
            Episciences_Repositories_NewVersionSpy_Hooks::$calls['hookLinkedDataProcessing']['response'],
            'the record is not requested again'
        );
        self::assertSame(1, $affectedRows, 'the rows of this step only, not added to the rows of the previous steps');
    }

    public function testFailedStepIsReportedNamedAndTheNextStepsRun(): void
    {
        $failedSteps = [];
        $ran = [];

        $errors = $this->collectErrors(function () use (&$failedSteps, &$ran, &$affectedRows): void {
            $affectedRows = Episciences_Submit::runPostSaveSteps(
                $this->paper(),
                [
                    'hookFilesProcessing' => static function () use (&$ran): int {
                        $ran[] = 'hookFilesProcessing';
                        return 2;
                    },
                    'linkedData' => static function () use (&$ran): int {
                        $ran[] = 'linkedData';
                        throw new Ccsd_Error('linked data unavailable');
                    },
                    'enrichmentProcess' => static function () use (&$ran): int {
                        $ran[] = 'enrichmentProcess';
                        throw new RuntimeException('Enrichment not stored: citations');
                    },
                    'licence' => static function () use (&$ran): int {
                        $ran[] = 'licence';
                        return 3;
                    },
                ],
                $failedSteps,
            );
        });

        self::assertSame(['hookFilesProcessing', 'linkedData', 'enrichmentProcess', 'licence'], $ran);
        self::assertSame(['linkedData', 'enrichmentProcess'], $failedSteps);
        self::assertSame(5, $affectedRows);
        self::assertCount(2, $errors);
        self::assertStringContainsString('linkedData', $errors[0]);
        self::assertStringContainsString('docid ' . self::DOCID, $errors[0]);
        self::assertStringContainsString('Ccsd_Error: linked data unavailable', $errors[0]);
        self::assertStringContainsString('RuntimeException: Enrichment not stored: citations', $errors[1]);
    }

    /**
     * The linked data stored before another one failed are counted, and the step is reported as failed
     */
    public function testLinkedDataStoredBeforeAFailureAreCounted(): void
    {
        $failedSteps = [];

        $errors = $this->collectErrors(function () use (&$failedSteps, &$affectedRows): void {
            $affectedRows = Episciences_Submit::runPostSaveSteps($this->paper(), [
                'linkedData' => static fn(): int => throw new LinkedDataNotStoredException('Linked data not stored: x', 4),
                'licence' => static fn(): int => 1,
            ], $failedSteps);
        });

        self::assertSame(5, $affectedRows);
        self::assertSame(['linkedData'], $failedSteps);
        self::assertCount(1, $errors);
    }

    /**
     * @return array<string, mixed>
     */
    private static function context(): array
    {
        return [
            'docId' => self::DOCID,
            'identifier' => '42',
            'repoId' => self::SPY_REPO_ID,
            'version' => 2.0,
            'paperId' => self::DOCID,
        ];
    }

    private function paper(): Episciences_Paper
    {
        return new Episciences_Paper([
            'docid' => self::DOCID,
            'paperid' => self::DOCID,
            'repoid' => self::SPY_REPO_ID,
            'identifier' => '42',
            'version' => 2,
        ]);
    }

    public function testDocumentThatCannotBeRebuiltIsReportedAsAFailedStep(): void
    {
        // no paper with this docid: PAPERS.DOCUMENT cannot be rebuilt
        $failedSteps = ['licence'];
        $args = [999999999, &$failedSteps];

        $reflection = new ReflectionMethod(Episciences_PapersManager::class, 'refreshJsonDocumentAfterRecordUpdate');
        $reflection->setAccessible(true);
        $reflection->invokeArgs(null, $args);

        self::assertSame(['licence', 'jsonDocument'], $failedSteps);
    }

    public function testHalStepFailsWhenTheTeiCouldNotBeReadAgainFromHal(): void
    {
        // the user asked for the metadata edited on HAL: a cached TEI is no answer, they must try again
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('could not be read again from HAL');

        $this->invoke('processHalData', ['repoId' => (int) Episciences_Repositories::HAL_REPO_ID] + self::context(), true);
    }

    private function invoke(string $method, mixed ...$args): mixed
    {
        $reflection = new ReflectionMethod(Episciences_PapersManager::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(null, ...$args);
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
