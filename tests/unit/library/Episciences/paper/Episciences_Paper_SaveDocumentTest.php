<?php

declare(strict_types=1);

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Episciences_Paper::save() (update) must keep the stored DOCUMENT when toJson() fails.
 * Rows are inserted in a transaction that is rolled back after each test.
 *
 * @covers Episciences_Paper::save
 */
final class Episciences_Paper_SaveDocumentTest extends TestCase
{
    private const PAPER_ID = 999004;
    private const STORED_DOCUMENT = ['stored' => true];

    private ?Zend_Db_Adapter_Abstract $db = null;

    protected function setUp(): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$db instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }
        $this->db = $db;
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->db?->rollBack();
    }

    public function testFailedJsonGenerationKeepsTheStoredDocument(): void
    {
        $docId = $this->insertPaper();
        $paper = $this->paperReturning($docId, null);
        $paper->setStatus(Episciences_Paper::STATUS_ACCEPTED);

        self::assertTrue($paper->save());

        $row = $this->fetchRow($docId);
        self::assertSame(Episciences_Paper::STATUS_ACCEPTED, (int)$row['STATUS']);
        self::assertSame(self::STORED_DOCUMENT, json_decode((string)$row['DOCUMENT'], true));
    }

    public function testSuccessfulJsonGenerationReplacesTheStoredDocument(): void
    {
        $docId = $this->insertPaper();
        $paper = $this->paperReturning($docId, '{"fresh":true}');

        self::assertTrue($paper->save());

        self::assertSame(['fresh' => true], json_decode((string)$this->fetchRow($docId)['DOCUMENT'], true));
    }

    private function insertPaper(): int
    {
        $this->db?->insert(T_PAPERS, [
            'PAPERID' => self::PAPER_ID,
            'RVID' => RVID,
            'UID' => 1,
            'STATUS' => Episciences_Paper::STATUS_SUBMITTED,
            'IDENTIFIER' => 'save-document-test',
            'VERSION' => 1,
            'REPOID' => 1,
            'RECORD' => '<record/>',
            'DOCUMENT' => json_encode(self::STORED_DOCUMENT),
            'WHEN' => '2020-01-02 03:04:05',
            'SUBMISSION_DATE' => '2020-01-02 03:04:05',
        ]);

        return (int)$this->db?->lastInsertId();
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchRow(int $docId): array
    {
        return $this->db?->fetchRow(
            $this->db->select()->from(T_PAPERS, ['STATUS', 'DOCUMENT'])->where('DOCID = ?', $docId)
        ) ?: [];
    }

    private function paperReturning(int $docId, ?string $json): Episciences_Paper&MockObject
    {
        $paper = $this->getMockBuilder(Episciences_Paper::class)
            ->setConstructorArgs([[
                'docid' => $docId,
                'paperid' => self::PAPER_ID,
                'rvid' => RVID,
                'uid' => 1,
                'status' => Episciences_Paper::STATUS_SUBMITTED,
                'identifier' => 'save-document-test',
                'version' => 1,
                'repoid' => 1,
                'record' => '<record/>',
                'submission_date' => '2020-01-02 03:04:05',
            ]])
            ->onlyMethods(['toJson'])
            ->getMock();
        $paper->method('toJson')->willReturn($json);

        return $paper;
    }
}
