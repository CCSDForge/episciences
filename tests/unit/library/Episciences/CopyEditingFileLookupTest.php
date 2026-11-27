<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences_CommentsManager;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Queries behind the checks on the files of the answers to a copy-editing request (see FileController): the
 * request the files are uploaded for, and the files already attached to the request or to its answers.
 * Rows are inserted in a transaction that is rolled back after each test.
 *
 * @covers Episciences_CommentsManager::isCopyEditingRequest
 * @covers Episciences_CommentsManager::isCopyEditingAttachment
 * @covers Episciences_CommentsManager::getCopyEditingAttachments
 */
final class CopyEditingFileLookupTest extends TestCase
{
    private const OWNER_UID = 4242;
    /** Not a paper of the fixtures: the comments are only looked up by docid */
    private const DOCID = 987654;

    private Zend_Db_Adapter_Abstract $db;

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
        $this->db->rollBack();
    }

    private function insertComment(int $docId, int $type, ?string $file = null, ?int $parentId = null): int
    {
        $this->db->insert(T_PAPER_COMMENTS, [
            'PARENTID' => $parentId,
            'TYPE' => $type,
            'DOCID' => $docId,
            'UID' => self::OWNER_UID,
            'MESSAGE' => '',
            'FILE' => $file,
            'WHEN' => '2020-01-02 03:04:05',
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function testCopyEditingRequestOfTheVersionIsFound(): void
    {
        $pcId = $this->insertComment(self::DOCID, Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST);

        self::assertTrue(Episciences_CommentsManager::isCopyEditingRequest(self::DOCID, $pcId));
    }

    public function testRequestOfAnotherVersionIsRefused(): void
    {
        $pcId = $this->insertComment(self::DOCID + 1, Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST);

        self::assertFalse(Episciences_CommentsManager::isCopyEditingRequest(self::DOCID, $pcId));
    }

    public function testAnswerToARequestIsNotARequest(): void
    {
        $requestId = $this->insertComment(self::DOCID, Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST);
        $answerId = $this->insertComment(
            self::DOCID,
            Episciences_CommentsManager::TYPE_AUTHOR_SOURCES_DEPOSED_ANSWER,
            null,
            $requestId
        );

        self::assertFalse(Episciences_CommentsManager::isCopyEditingRequest(self::DOCID, $answerId));
    }

    public function testCommentOutsideCopyEditingIsNotARequest(): void
    {
        $pcId = $this->insertComment(self::DOCID, Episciences_CommentsManager::TYPE_INFO_REQUEST);

        self::assertFalse(Episciences_CommentsManager::isCopyEditingRequest(self::DOCID, $pcId));
    }

    public function testMissingIdsAreRefused(): void
    {
        self::assertFalse(Episciences_CommentsManager::isCopyEditingRequest(0, 1));
        self::assertFalse(Episciences_CommentsManager::isCopyEditingRequest(self::DOCID, 0));
    }

    public function testFileOfTheRequestIsFound(): void
    {
        $pcId = $this->insertComment(
            self::DOCID,
            Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST,
            json_encode(['template.docx', 'guidelines (v2).pdf'])
        );

        self::assertTrue(Episciences_CommentsManager::isCopyEditingAttachment(self::DOCID, $pcId, 'guidelines (v2).pdf'));
        self::assertFalse(Episciences_CommentsManager::isCopyEditingAttachment(self::DOCID, $pcId, 'sources.zip'));
    }

    public function testFileOfASavedAnswerIsFound(): void
    {
        $pcId = $this->insertComment(self::DOCID, Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST);
        $this->insertComment(
            self::DOCID,
            Episciences_CommentsManager::TYPE_AUTHOR_SOURCES_DEPOSED_ANSWER,
            json_encode(['sources.zip']),
            $pcId
        );

        self::assertTrue(Episciences_CommentsManager::isCopyEditingAttachment(self::DOCID, $pcId, 'sources.zip'));
    }

    public function testFileOfAnotherRequestIsNotFound(): void
    {
        $pcId = $this->insertComment(self::DOCID, Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST);
        $otherPcId = $this->insertComment(
            self::DOCID,
            Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_FORMATTING_REQUEST,
            'template.docx'
        );

        self::assertTrue(Episciences_CommentsManager::isCopyEditingAttachment(self::DOCID, $otherPcId, 'template.docx'));
        self::assertFalse(Episciences_CommentsManager::isCopyEditingAttachment(self::DOCID, $pcId, 'template.docx'));
    }

    public function testFilesOfTheRequestAndOfItsAnswersAreListedOnce(): void
    {
        $pcId = $this->insertComment(
            self::DOCID,
            Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST,
            'template.docx'
        );
        $this->insertComment(
            self::DOCID,
            Episciences_CommentsManager::TYPE_AUTHOR_SOURCES_DEPOSED_ANSWER,
            json_encode(['sources.zip', 'figure.png']),
            $pcId
        );
        $this->insertComment(
            self::DOCID,
            Episciences_CommentsManager::TYPE_AUTHOR_SOURCES_DEPOSED_ANSWER,
            json_encode(['sources.zip']),
            $pcId
        );

        $names = Episciences_CommentsManager::getCopyEditingAttachments(self::DOCID, $pcId);
        sort($names);

        self::assertSame(['figure.png', 'sources.zip', 'template.docx'], $names);
    }

    public function testRequestWithoutFileListsNothing(): void
    {
        $pcId = $this->insertComment(self::DOCID, Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST);

        self::assertSame([], Episciences_CommentsManager::getCopyEditingAttachments(self::DOCID, $pcId));
    }
}
