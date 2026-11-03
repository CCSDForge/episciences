<?php

declare(strict_types=1);

namespace unit\library\Episciences\Submit;

use Episciences\Paper\LinkedDataNotStoredException;
use Episciences\Paper\PartiallyStoredException;
use Episciences_Paper;
use Episciences_Paper_DatasetMetadata;
use Episciences_Repositories;
use Episciences_Repositories_Common;
use Episciences_Submit;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Statement_Exception;
use Zend_Db_Statement_Interface;
use Zend_Db_Table_Abstract;

/**
 * Episciences_Submit::processDatasets(), run after the save of a paper (repository hooks, enrichment):
 * a related identifier that cannot be stored does not prevent the others, and every failure is thrown at the end,
 * so that the post-save processing reports it instead of taking it for a success.
 */
final class ProcessDatasetsFailureTest extends TestCase
{
    private const DOCID = 1234;
    private const STORED_SWHID = 'swh:1:dir:0000000000000000000000000000000000000001';
    private const FAILING_SWHID = 'swh:1:dir:0000000000000000000000000000000000000002';
    private const OTHER_SWHID = 'swh:1:dir:0000000000000000000000000000000000000003';

    private ?Zend_Db_Adapter_Abstract $previousAdapter = null;

    /** @var list<string> INSERT statements sent to the database */
    private array $inserts = [];

    /** @var list<string> paper lookups (Episciences_PapersManager::get()) */
    private array $paperLookups = [];

    protected function setUp(): void
    {
        $adapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$adapter instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }

        $this->previousAdapter = $adapter;

        /** @var Zend_Db_Adapter_Abstract&MockObject $db */
        $db = $this->getMockBuilder($adapter::class)
            ->setConstructorArgs([$adapter->getConfig()])
            ->onlyMethods(['query', 'lastInsertId'])
            ->getMock();
        $db->method('lastInsertId')->willReturn('77');
        $db->method('query')->willReturnCallback(function (mixed $sql): Zend_Db_Statement_Interface {
            $sql = (string)$sql;

            if (str_starts_with($sql, 'INSERT')) {
                $this->inserts[] = $sql;

                if (str_contains($sql, self::FAILING_SWHID) || str_contains($sql, 'failing metadata')) {
                    throw new Zend_Db_Statement_Exception('insert failed');
                }
            }

            $isPaperLookup = str_starts_with($sql, 'SELECT') && str_contains($sql, '`' . T_PAPERS . '`');
            if ($isPaperLookup) {
                $this->paperLookups[] = $sql;
            }

            // No dataset stored yet (findByValue()), one row per insert; an unpublished paper (not reindexed)
            $statement = $this->createMock(Zend_Db_Statement_Interface::class);
            $statement->method('fetch')->willReturn($isPaperLookup ? self::paperRow() : false);
            $statement->method('fetchAll')->willReturn([]);
            $statement->method('rowCount')->willReturn(1);

            return $statement;
        });

        Zend_Db_Table_Abstract::setDefaultAdapter($db);
    }

    protected function tearDown(): void
    {
        if ($this->previousAdapter !== null) {
            Zend_Db_Table_Abstract::setDefaultAdapter($this->previousAdapter);
        }
    }

    public function testEveryIdentifierIsStoredAndTheFailuresAreThrownTogether(): void
    {
        $relatedIdentifiers = [
            ['identifier' => self::STORED_SWHID, 'relation' => 'isSupplementedBy'],
            ['identifier' => self::FAILING_SWHID, 'relation' => 'isSupplementedBy'],
            // Client controlled (submitted enrichment): an unexpected value
            ['identifier' => ['not', 'a', 'string'], 'relation' => 'isSupplementedBy'],
            ['identifier' => self::OTHER_SWHID, 'relation' => 'isSupplementedBy'],
        ];

        try {
            Episciences_Submit::processDatasets($this->zenodoPaper(), $relatedIdentifiers);
            self::fail('the failures are thrown');
        } catch (LinkedDataNotStoredException $e) {
            self::assertSame(2, $e->getAffectedRows(), 'the identifiers stored are counted');
            self::assertStringContainsString(self::FAILING_SWHID . ' (Zend_Db_Statement_Exception: insert failed)', $e->getMessage());
            self::assertStringContainsString('UnexpectedValueException: Unexpected related identifier: array', $e->getMessage());
            self::assertStringNotContainsString(self::STORED_SWHID, $e->getMessage());
            self::assertStringNotContainsString(self::OTHER_SWHID, $e->getMessage());
        }

        self::assertCount(3, $this->inserts, 'the identifiers after a failure are still stored');
        self::assertStringContainsString(self::OTHER_SWHID, $this->inserts[2]);
    }

    /**
     * The linked data hooks (Zenodo, BAOBAB, ARCHE): the paper is looked up for its reindex after the identifiers
     * that could be stored, also when another one could not; the failure and the stored rows are thrown after it.
     */
    public function testPaperIsLookedUpForItsReindexAlsoWhenAnIdentifierFails(): void
    {
        try {
            Episciences_Submit::processLinkedDataAndReindex(self::DOCID, [
                ['identifier' => self::STORED_SWHID, 'relation' => 'isSupplementedBy'],
                ['identifier' => self::FAILING_SWHID, 'relation' => 'isSupplementedBy'],
            ]);
            self::fail('the failure is thrown');
        } catch (LinkedDataNotStoredException $e) {
            self::assertSame(1, $e->getAffectedRows());
        }

        // processDatasets() loads the paper, then the reindex looks it up again
        self::assertCount(2, $this->paperLookups);
    }

    public function testPaperIsNotLookedUpForItsReindexWhenNothingIsStored(): void
    {
        try {
            Episciences_Submit::processLinkedDataAndReindex(self::DOCID, [
                ['identifier' => self::FAILING_SWHID, 'relation' => 'isSupplementedBy'],
            ]);
            self::fail('the failure is thrown');
        } catch (LinkedDataNotStoredException $e) {
            self::assertSame(0, $e->getAffectedRows());
        }

        self::assertCount(1, $this->paperLookups, 'only the lookup of processDatasets()');
    }

    public function testStoredIdentifiersAreCountedWithoutFailure(): void
    {
        $affectedRows = Episciences_Submit::processDatasets($this->zenodoPaper(), [
            ['identifier' => self::STORED_SWHID, 'relation' => 'isSupplementedBy'],
            ['identifier' => self::OTHER_SWHID, 'relation' => 'isSupplementedBy'],
        ]);

        self::assertSame(2, $affectedRows);
    }

    public function testEntryThatIsNotAListIsIgnored(): void
    {
        $affectedRows = Episciences_Submit::processDatasets($this->zenodoPaper(), [
            'not a list',
            ['identifier' => self::STORED_SWHID, 'relation' => 'isSupplementedBy'],
        ]);

        self::assertSame(1, $affectedRows);
    }

    /**
     * Episciences_Submit::storeEnrichmentOrFail(): the rows stored by the other enrichments, and by the related
     * identifiers stored before one failed, are counted in the failure (Episciences_Submit::runPostSaveSteps())
     */
    public function testEnrichmentStoredBeforeAFailureIsCounted(): void
    {
        try {
            Episciences_Submit::storeEnrichmentOrFail($this->zenodoPaper(), [
                Episciences_Repositories_Common::CITATIONS => [['doi' => '10.1234/citing']],
                Episciences_Repositories_Common::RELATED_IDENTIFIERS => [
                    ['identifier' => self::STORED_SWHID, 'relation' => 'isSupplementedBy'],
                    ['identifier' => self::FAILING_SWHID, 'relation' => 'isSupplementedBy'],
                ],
            ]);
            self::fail('the failure is thrown');
        } catch (PartiallyStoredException $e) {
            self::assertNotInstanceOf(LinkedDataNotStoredException::class, $e);
            self::assertSame(2, $e->getAffectedRows(), 'the citations and the related identifier stored are counted');
            self::assertStringContainsString('Enrichment not stored: ' . Episciences_Repositories_Common::RELATED_IDENTIFIERS, $e->getMessage());
        }
    }

    public function testFailedEnrichmentIsCountedByThePostSaveSteps(): void
    {
        $failedSteps = [];

        set_error_handler(static fn(): bool => true);
        try {
            $affectedRows = Episciences_Submit::runPostSaveSteps($this->zenodoPaper(), [
                'enrichmentProcess' => fn(): int => Episciences_Submit::storeEnrichmentOrFail($this->zenodoPaper(), [
                    Episciences_Repositories_Common::RELATED_IDENTIFIERS => [
                        ['identifier' => self::STORED_SWHID, 'relation' => 'isSupplementedBy'],
                        ['identifier' => self::FAILING_SWHID, 'relation' => 'isSupplementedBy'],
                    ],
                ]),
            ], $failedSteps);
        } finally {
            restore_error_handler();
        }

        self::assertSame(1, $affectedRows);
        self::assertSame(['enrichmentProcess'], $failedSteps);
    }

    public function testMetadataFailureIsReportedAndTheDatasetKeptWithoutIt(): void
    {
        $failures = [];
        $method = new ReflectionMethod(Episciences_Submit::class, 'insertDatasetMetadata');
        $method->setAccessible(true);

        $failing = new Episciences_Paper_DatasetMetadata(['metatext' => 'failing metadata']);
        $metadataId = $method->invokeArgs(null, [$failing, '10.1234/abc', &$failures]);

        self::assertNull($metadataId, 'the dataset is stored without metadata');
        self::assertSame(['metadata of 10.1234/abc (Zend_Db_Statement_Exception: insert failed)'], $failures);

        $stored = new Episciences_Paper_DatasetMetadata(['metatext' => '{}']);
        self::assertSame(77, $method->invokeArgs(null, [$stored, '10.1234/def', &$failures]));
        self::assertCount(1, $failures);
    }

    /**
     * @return array<string, mixed>
     */
    private static function paperRow(): array
    {
        return [
            'DOCID' => self::DOCID,
            'PAPERID' => self::DOCID,
            'RVID' => 1,
            'REPOID' => (int)Episciences_Repositories::ZENODO_REPO_ID,
            'IDENTIFIER' => '123456',
            'VERSION' => 1,
            'STATUS' => Episciences_Paper::STATUS_SUBMITTED,
        ];
    }

    private function zenodoPaper(): Episciences_Paper
    {
        return new Episciences_Paper([
            'docid' => self::DOCID,
            'paperid' => self::DOCID,
            'repoid' => (int)Episciences_Repositories::ZENODO_REPO_ID,
            'identifier' => '123456',
            'version' => 1,
        ]);
    }
}
