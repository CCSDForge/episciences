<?php

declare(strict_types=1);

namespace unit\scripts;

require_once __DIR__ . '/../../../scripts/ProcessInboxNotificationsCommand.php';

use Episciences_Comment;
use Episciences_CommentsManager;
use PHPUnit\Framework\TestCase;
use ProcessInboxNotificationsCommand;
use ReflectionMethod;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Revision request answered by a new version received through the inbox.
 * Rows are inserted in a transaction that is rolled back after each test.
 *
 * @covers ProcessInboxNotificationsCommand
 */
final class ProcessInboxNotificationsRevisionRequestTest extends TestCase
{
    private const AUTHOR_UID = 990000001;
    private const EDITOR_UID = 990000002;

    private Zend_Db_Adapter_Abstract $db;

    protected function setUp(): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$db instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }
        $this->db = $db;
        $this->db->beginTransaction();

        // The comments are read with their author's profile
        $this->insertUser(self::AUTHOR_UID);
        $this->insertUser(self::EDITOR_UID);
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
    }

    private function insertUser(int $uid): void
    {
        $this->db->insert(T_USERS, [
            'UID' => $uid,
            'uuid' => sprintf('00000000-0000-4000-8000-%012d', $uid),
            'SCREEN_NAME' => "User $uid",
            'USERNAME' => "user$uid",
            'API_PASSWORD' => '',
            'EMAIL' => "user$uid@example.org",
            'LASTNAME' => "User $uid",
        ]);
    }

    private function insertPaper(): int
    {
        $this->db->insert(T_PAPERS, [
            'PAPERID' => 0,
            'RVID' => RVID,
            'UID' => self::AUTHOR_UID,
            'STATUS' => 0,
            'IDENTIFIER' => 'inbox-revision-request-test',
            'VERSION' => 1,
            'REPOID' => 1,
            'RECORD' => '<record/>',
            'WHEN' => '2020-01-01 00:00:00',
            'SUBMISSION_DATE' => '2020-01-01 00:00:00',
        ]);
        $docId = (int)$this->db->lastInsertId();
        $this->db->update(T_PAPERS, ['PAPERID' => $docId], ['DOCID = ?' => $docId]);

        return $docId;
    }

    private function insertComment(int $docId, int $type, int $uid, string $when, ?array $options = null): int
    {
        $this->db->insert(T_PAPER_COMMENTS, [
            'TYPE' => $type,
            'DOCID' => $docId,
            'UID' => $uid,
            'MESSAGE' => 'message',
            'OPTIONS' => $options === null ? null : json_encode($options),
            'WHEN' => $when,
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function findLatestRevisionRequest(int $docId): ?Episciences_Comment
    {
        $method = new ReflectionMethod(ProcessInboxNotificationsCommand::class, 'findLatestRevisionRequest');

        return $method->invoke(new ProcessInboxNotificationsCommand(), $docId);
    }

    public function testCommentsOfOtherTypesAreIgnoredEvenWhenMoreRecent(): void
    {
        $docId = $this->insertPaper();
        $requestId = $this->insertComment(
            $docId,
            Episciences_CommentsManager::TYPE_REVISION_REQUEST,
            self::EDITOR_UID,
            '2020-02-01 00:00:00',
            ['reassign_reviewers' => true]
        );
        $this->insertComment($docId, Episciences_CommentsManager::TYPE_AUTHOR_COMMENT, self::AUTHOR_UID, '2020-03-01 00:00:00');

        $request = $this->findLatestRevisionRequest($docId);

        self::assertNotNull($request);
        self::assertSame($requestId, (int)$request->getPcid());
        self::assertSame(Episciences_CommentsManager::TYPE_REVISION_REQUEST, (int)$request->getType());
        self::assertTrue($request->getOption('reassign_reviewers'));
    }

    public function testTheLatestRevisionRequestIsAnswered(): void
    {
        $docId = $this->insertPaper();
        $this->insertComment($docId, Episciences_CommentsManager::TYPE_REVISION_REQUEST, self::EDITOR_UID, '2020-02-01 00:00:00');
        $latestId = $this->insertComment($docId, Episciences_CommentsManager::TYPE_REVISION_REQUEST, self::EDITOR_UID, '2020-04-01 00:00:00');

        self::assertSame($latestId, (int)$this->findLatestRevisionRequest($docId)?->getPcid());
    }

    public function testNoRevisionRequestGivesNull(): void
    {
        $docId = $this->insertPaper();
        $this->insertComment($docId, Episciences_CommentsManager::TYPE_AUTHOR_COMMENT, self::AUTHOR_UID, '2020-03-01 00:00:00');

        self::assertNull($this->findLatestRevisionRequest($docId));
    }
}
