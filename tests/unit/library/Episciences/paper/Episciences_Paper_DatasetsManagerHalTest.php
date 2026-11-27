<?php

declare(strict_types=1);

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use Episciences_Paper_Dataset;
use Episciences_Paper_DatasetsManager;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * A HAL document linked to a paper is found whatever version suffix it was
 * stored with, so a related identifier never links it a second time.
 */
final class Episciences_Paper_DatasetsManagerHalTest extends TestCase
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
            'IDENTIFIER' => 'datasets-hal-test',
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

    private function linkHal(string $value): void
    {
        Episciences_Paper_DatasetsManager::addDatasetFromSubmission(
            (int)$this->docId,
            Episciences_Paper_Dataset::HAL_CODE,
            $value,
            Episciences_Paper_Dataset::PUBLICATION
        );
    }

    /**
     * @dataProvider storedHalValueProvider
     */
    public function testHasHalDocument(string $stored, string $lookedUp, bool $expected): void
    {
        $this->linkHal($stored);

        self::assertSame($expected, Episciences_Paper_DatasetsManager::hasHalDocument((int)$this->docId, $lookedUp));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function storedHalValueProvider(): array
    {
        return [
            'stored without version' => ['hal-01234567', 'hal-01234567', true],
            'stored with a version' => ['hal-01234567v2', 'hal-01234567', true],
            'another document sharing the prefix' => ['hal-012345678', 'hal-01234567', false],
            'another document' => ['hal-07654321v1', 'hal-01234567', false],
        ];
    }

    public function testHasHalDocumentIgnoresNonHalLinks(): void
    {
        Episciences_Paper_DatasetsManager::addDatasetFromSubmission(
            (int)$this->docId,
            Episciences_Paper_Dataset::HANDLE_CODE,
            'hal-01234567',
            Episciences_Paper_Dataset::HANDLE_CODE
        );

        self::assertFalse(Episciences_Paper_DatasetsManager::hasHalDocument((int)$this->docId, 'hal-01234567'));
    }

    public function testHasHalDocumentOnlyLooksAtThePaper(): void
    {
        $this->linkHal('hal-01234567');

        self::assertFalse(Episciences_Paper_DatasetsManager::hasHalDocument((int)$this->docId + 1000000, 'hal-01234567'));
    }
}
