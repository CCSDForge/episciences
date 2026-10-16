<?php

declare(strict_types=1);

namespace unit\library\Episciences\submit;

use Episciences_Paper;
use Episciences_Paper_Dataset;
use Episciences_Paper_DatasetsManager;
use Episciences_Submit;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Values whose DOI lookup failed are stored as is, each with the relation type
 * it was found with.
 */
final class Episciences_Submit_ForceAddingDatasetsTest extends TestCase
{
    private Zend_Db_Adapter_Abstract $db;

    private ?int $docId = null;

    protected function setUp(): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$db instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }
        $this->db = $db;

        $this->db->insert(T_PAPERS, [
            'PAPERID' => 1,
            'RVID' => RVID,
            'UID' => 4242,
            'STATUS' => Episciences_Paper::STATUS_SUBMITTED,
            'IDENTIFIER' => 'force-adding-datasets-test',
            'VERSION' => 1,
            'REPOID' => 1,
            'RECORD' => '<record/>',
            'WHEN' => '2020-01-02 03:04:05',
            'SUBMISSION_DATE' => '2020-01-02 03:04:05',
        ]);
        $this->docId = (int)$this->db->lastInsertId();
    }

    protected function tearDown(): void
    {
        if ($this->docId !== null) {
            $this->db->delete(T_PAPER_DATASETS, ['doc_id = ?' => $this->docId]);
            $this->db->delete(T_PAPERS, ['DOCID = ?' => $this->docId]);
        }
    }

    private function paper(): Episciences_Paper
    {
        return (new Episciences_Paper())->setDocid($this->docId)->setRepoid(1);
    }

    public function testKeepsTheRelationOfEachValue(): void
    {
        $inserted = Episciences_Submit::forceAddingDatasets($this->paper(), [
            Episciences_Paper_Dataset::DOI_CODE => [
                ['value' => '10.1234/first', 'relation' => 'IsSupplementTo'],
                ['value' => '10.1234/second', 'relation' => 'References'],
            ],
        ]);

        self::assertSame(2, $inserted);
        self::assertSame('IsSupplementTo', Episciences_Paper_DatasetsManager::findByValue((int)$this->docId, '10.1234/first')?->getRelationship());
        self::assertSame('References', Episciences_Paper_DatasetsManager::findByValue((int)$this->docId, '10.1234/second')?->getRelationship());
    }

    public function testPlainValuesUseTheTypeLevelRelation(): void
    {
        Episciences_Submit::forceAddingDatasets($this->paper(), [
            Episciences_Paper_Dataset::DOI_CODE => ['10.1234/plain', 'relation' => 'IsCitedBy'],
        ]);

        self::assertSame('IsCitedBy', Episciences_Paper_DatasetsManager::findByValue((int)$this->docId, '10.1234/plain')?->getRelationship());
        // The type-level relation is not itself stored as a value
        self::assertNull(Episciences_Paper_DatasetsManager::findByValue((int)$this->docId, 'IsCitedBy'));
    }
}
