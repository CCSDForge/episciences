<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use Episciences_Acl;
use Episciences_CommentsManager;
use Episciences_PapersManager;
use Episciences_User_Assignment;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

/**
 * Queries behind the access checks on the files of the temporary versions (see FileController): the versions
 * of a paper in a journal, the roles of the user on them, and the files of the answers already saved with a
 * temporary version.
 * Rows are inserted in a transaction that is rolled back after each test.
 *
 * @covers Episciences_PapersManager::getDocIdsByPaperIdInJournal
 * @covers Episciences_PapersManager::getUserRolesOnVersions
 * @covers Episciences_CommentsManager::isTemporaryVersionAnswerAttachment
 */
final class TemporaryVersionFileLookupTest extends TestCase
{
    private const OWNER_UID = 4242;
    /** User whose roles are looked up */
    private const UID = 4243;
    private const OTHER_UID = 4244;

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

    /**
     * @param array<string, mixed> $columns columns overriding the defaults
     */
    private function insertVersion(int $paperId, string $when, array $columns = []): int
    {
        $this->db->insert(T_PAPERS, $columns + [
            'PAPERID' => $paperId,
            'RVID' => RVID,
            'UID' => self::OWNER_UID,
            'STATUS' => 0,
            'IDENTIFIER' => 'temporary-version-file-lookup-test',
            'VERSION' => 1,
            'REPOID' => 1,
            'RECORD' => '<record/>',
            'WHEN' => $when,
            'SUBMISSION_DATE' => '2020-01-02 03:04:05',
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * First version of a new paper: its docid is the paper id
     */
    private function insertPaper(string $when): int
    {
        $docId = $this->insertVersion(0, $when);
        $this->db->update(T_PAPERS, ['PAPERID' => $docId], ['DOCID = ?' => $docId]);

        return $docId;
    }

    private function insertComment(int $docId, int $type, ?string $file): void
    {
        $this->db->insert(T_PAPER_COMMENTS, [
            'TYPE' => $type,
            'DOCID' => $docId,
            'UID' => self::OWNER_UID,
            'MESSAGE' => '',
            'FILE' => $file,
            'WHEN' => '2020-01-02 03:04:05',
        ]);
    }

    public function testVersionsOfThePaperAreListedTheLatestFirst(): void
    {
        $paperId = $this->insertPaper('2020-01-01 00:00:00');
        $temporaryVersion = $this->insertVersion($paperId, '2020-03-01 00:00:00', ['VERSION' => 1.01]);
        $secondVersion = $this->insertVersion($paperId, '2020-02-01 00:00:00', ['VERSION' => 2]);

        self::assertSame(
            [$temporaryVersion, $secondVersion, $paperId],
            Episciences_PapersManager::getDocIdsByPaperIdInJournal($paperId, RVID)
        );
    }

    public function testVersionsInAnotherJournalAreNotListed(): void
    {
        $paperId = $this->insertPaper('2020-01-01 00:00:00');
        $this->insertVersion($paperId, '2020-02-01 00:00:00', ['RVID' => RVID + 1]);

        self::assertSame([$paperId], Episciences_PapersManager::getDocIdsByPaperIdInJournal($paperId, RVID));
        self::assertSame([], Episciences_PapersManager::getDocIdsByPaperIdInJournal($paperId, RVID + 2));
    }

    public function testVersionsOfAnotherPaperAreNotListed(): void
    {
        $paperId = $this->insertPaper('2020-01-01 00:00:00');
        $otherPaperId = $this->insertPaper('2020-02-01 00:00:00');

        self::assertSame([$paperId], Episciences_PapersManager::getDocIdsByPaperIdInJournal($paperId, RVID));
        self::assertSame([$otherPaperId], Episciences_PapersManager::getDocIdsByPaperIdInJournal($otherPaperId, RVID));
    }

    public function testFileListedAmongTheFilesOfAnAnswerIsFound(): void
    {
        $docId = $this->insertPaper('2020-01-01 00:00:00');
        $this->insertComment(
            $docId,
            Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION,
            json_encode(['article.pdf', 'résumé (v2).pdf'])
        );

        self::assertTrue(Episciences_CommentsManager::isTemporaryVersionAnswerAttachment([$docId], 'résumé (v2).pdf'));
        self::assertFalse(Episciences_CommentsManager::isTemporaryVersionAnswerAttachment([$docId], 'résumé.pdf'));
    }

    public function testSingleFileOfAnAnswerIsFound(): void
    {
        $docId = $this->insertPaper('2020-01-01 00:00:00');
        $this->insertComment($docId, Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION, 'article.pdf');

        self::assertTrue(Episciences_CommentsManager::isTemporaryVersionAnswerAttachment([$docId], 'article.pdf'));
    }

    public function testAnswerSavedOnAnyVersionOfThePaperIsLookedUp(): void
    {
        $paperId = $this->insertPaper('2020-01-01 00:00:00');
        $secondVersion = $this->insertVersion($paperId, '2020-02-01 00:00:00', ['VERSION' => 2]);
        $this->insertComment(
            $secondVersion,
            Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION,
            json_encode(['article.pdf'])
        );

        self::assertTrue(Episciences_CommentsManager::isTemporaryVersionAnswerAttachment([$paperId, $secondVersion], 'article.pdf'));
        self::assertFalse(Episciences_CommentsManager::isTemporaryVersionAnswerAttachment([$paperId], 'article.pdf'));
    }

    public function testFilesOfOtherCommentsAreIgnored(): void
    {
        $docId = $this->insertPaper('2020-01-01 00:00:00');
        $this->insertComment($docId, Episciences_CommentsManager::TYPE_REVISION_ANSWER_COMMENT, json_encode(['article.pdf']));
        $this->insertComment($docId, Episciences_CommentsManager::TYPE_INFO_REQUEST, 'article.pdf');

        self::assertFalse(Episciences_CommentsManager::isTemporaryVersionAnswerAttachment([$docId], 'article.pdf'));
    }

    public function testAnswerWithoutFileIsIgnored(): void
    {
        $docId = $this->insertPaper('2020-01-01 00:00:00');
        $this->insertComment($docId, Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION, null);
        $this->insertComment($docId, Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION, '');

        self::assertFalse(Episciences_CommentsManager::isTemporaryVersionAnswerAttachment([$docId], ''));
    }

    public function testNoVersionMeansNoAnswer(): void
    {
        self::assertFalse(Episciences_CommentsManager::isTemporaryVersionAnswerAttachment([], 'article.pdf'));
    }

    /**
     * Local account, required for the editors and copy editors (see Episciences_PapersManager::getEditors())
     */
    private function insertUser(int $uid): void
    {
        $this->db->insert(T_USERS, [
            'UID' => $uid,
            'uuid' => sprintf('00000000-0000-4000-8000-%012d', $uid),
            'SCREEN_NAME' => 'user ' . $uid,
            'USERNAME' => 'temporary-version-file-lookup-' . $uid,
            'API_PASSWORD' => '',
            'EMAIL' => $uid . '@example.org',
            'LASTNAME' => 'user ' . $uid,
        ]);
    }

    private function assign(int $docId, string $role, string $status, string $when, int $uid = self::UID, int $tmpUser = 0): void
    {
        $this->db->insert(T_ASSIGNMENTS, [
            'RVID' => RVID,
            'ITEMID' => $docId,
            'ITEM' => 'paper',
            'UID' => $uid,
            'TMP_USER' => $tmpUser,
            'ROLEID' => $role,
            'STATUS' => $status,
            'WHEN' => $when,
        ]);
    }

    /**
     * @param int[] $docIds
     * @param int[]|null $authorUids the user by default
     * @return string[] roles held by the user
     */
    private static function rolesOf(array $docIds, int $uid = self::UID, ?array $authorUids = null): array
    {
        return array_keys(array_filter(Episciences_PapersManager::getUserRolesOnVersions($docIds, $uid, $authorUids ?? [$uid])));
    }

    public function testNoVersionMeansNoRole(): void
    {
        self::assertSame([], self::rolesOf([]));
    }

    public function testOwnerOfAnyVersionIsFound(): void
    {
        $paperId = $this->insertPaper('2020-01-01 00:00:00');
        $secondVersion = $this->insertVersion($paperId, '2020-02-01 00:00:00', ['UID' => self::UID]);

        self::assertSame(['owner'], self::rolesOf([$secondVersion, $paperId]));
        self::assertSame([], self::rolesOf([$paperId]));
        // The user signed in as another one (see Episciences_Paper::isOwner())
        self::assertSame(['owner'], self::rolesOf([$paperId], self::UID, [self::UID, self::OWNER_UID]));
    }

    public function testCoAuthorOfAnyVersionIsFound(): void
    {
        $paperId = $this->insertPaper('2020-01-01 00:00:00');
        $secondVersion = $this->insertVersion($paperId, '2020-02-01 00:00:00');
        $this->assign($paperId, Episciences_Acl::ROLE_CO_AUTHOR, Episciences_User_Assignment::STATUS_ACTIVE, '2020-01-02 00:00:00');

        self::assertSame(['coAuthor'], self::rolesOf([$secondVersion, $paperId]));
        self::assertSame([], self::rolesOf([$secondVersion]));
        self::assertSame([], self::rolesOf([$paperId], self::UID, [self::OTHER_UID]));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function editorialRoleProvider(): array
    {
        return [
            'editor' => [Episciences_Acl::ROLE_EDITOR, 'editor'],
            'copy editor' => [Episciences_Acl::ROLE_COPY_EDITOR, 'copyEditor'],
        ];
    }

    /**
     * @dataProvider editorialRoleProvider
     */
    public function testEditorialStaffWhoseLatestAssignmentIsActiveIsFound(string $role, string $key): void
    {
        $this->insertUser(self::UID);
        $docId = $this->insertPaper('2020-01-01 00:00:00');
        $this->assign($docId, $role, Episciences_User_Assignment::STATUS_INACTIVE, '2020-01-02 00:00:00');
        $this->assign($docId, $role, Episciences_User_Assignment::STATUS_ACTIVE, '2020-01-03 00:00:00');

        self::assertSame([$key], self::rolesOf([$docId]));
        self::assertTrue($this->isAssigned($role, $docId));
    }

    /**
     * @dataProvider editorialRoleProvider
     */
    public function testEditorialStaffWhoseLatestAssignmentIsInactiveIsNotFound(string $role): void
    {
        $this->insertUser(self::UID);
        $docId = $this->insertPaper('2020-01-01 00:00:00');
        $this->assign($docId, $role, Episciences_User_Assignment::STATUS_ACTIVE, '2020-01-02 00:00:00');
        $this->assign($docId, $role, Episciences_User_Assignment::STATUS_INACTIVE, '2020-01-03 00:00:00');

        self::assertSame([], self::rolesOf([$docId]));
        self::assertFalse($this->isAssigned($role, $docId));
    }

    /**
     * @dataProvider editorialRoleProvider
     */
    public function testEditorialStaffWithoutLocalAccountIsNotFound(string $role): void
    {
        $docId = $this->insertPaper('2020-01-01 00:00:00');
        $this->assign($docId, $role, Episciences_User_Assignment::STATUS_ACTIVE, '2020-01-02 00:00:00');

        self::assertSame([], self::rolesOf([$docId]));
        self::assertFalse($this->isAssigned($role, $docId));
    }

    public function testAssignmentToAnOlderVersionIsFound(): void
    {
        // The latest assignment is read for each version: unassigned from the latest one only
        $this->insertUser(self::UID);
        $paperId = $this->insertPaper('2020-01-01 00:00:00');
        $secondVersion = $this->insertVersion($paperId, '2020-02-01 00:00:00');
        $this->assign($paperId, Episciences_Acl::ROLE_EDITOR, Episciences_User_Assignment::STATUS_ACTIVE, '2020-01-02 00:00:00');
        $this->assign($secondVersion, Episciences_Acl::ROLE_EDITOR, Episciences_User_Assignment::STATUS_ACTIVE, '2020-02-02 00:00:00');
        $this->assign($secondVersion, Episciences_Acl::ROLE_EDITOR, Episciences_User_Assignment::STATUS_INACTIVE, '2020-03-02 00:00:00');

        self::assertSame(['editor'], self::rolesOf([$secondVersion, $paperId]));
        self::assertSame([], self::rolesOf([$secondVersion]));
    }

    public function testAssignmentToAnotherPaperOrOfAnotherUserOrRoleIsNotFound(): void
    {
        $this->insertUser(self::UID);
        $this->insertUser(self::OTHER_UID);
        $docId = $this->insertPaper('2020-01-01 00:00:00');
        $otherPaperId = $this->insertPaper('2020-02-01 00:00:00');
        $this->assign($otherPaperId, Episciences_Acl::ROLE_EDITOR, Episciences_User_Assignment::STATUS_ACTIVE, '2020-02-02 00:00:00');
        $this->assign($docId, Episciences_Acl::ROLE_EDITOR, Episciences_User_Assignment::STATUS_ACTIVE, '2020-01-02 00:00:00', self::OTHER_UID);
        $this->assign($docId, Episciences_Acl::ROLE_GUEST_EDITOR, Episciences_User_Assignment::STATUS_ACTIVE, '2020-01-02 00:00:00');

        self::assertSame([], self::rolesOf([$docId]));
        self::assertSame(['editor'], self::rolesOf([$otherPaperId]));
    }

    public function testReviewerWhoseLatestAssignmentIsActiveIsFound(): void
    {
        // No local account required for the reviewers (see Episciences_PapersManager::getReviewers())
        $docId = $this->insertPaper('2020-01-01 00:00:00');
        $this->assign($docId, Episciences_Acl::ROLE_REVIEWER, Episciences_User_Assignment::STATUS_PENDING, '2020-01-02 00:00:00');
        $this->assign($docId, Episciences_Acl::ROLE_REVIEWER, Episciences_User_Assignment::STATUS_ACTIVE, '2020-01-03 00:00:00');

        self::assertSame(['reviewer'], self::rolesOf([$docId]));
        self::assertArrayHasKey(self::UID, Episciences_PapersManager::getReviewers($docId));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function reviewerLatestStatusProvider(): array
    {
        return [
            'pending' => [Episciences_User_Assignment::STATUS_PENDING],
            'declined' => [Episciences_User_Assignment::STATUS_DECLINED],
            'inactive' => [Episciences_User_Assignment::STATUS_INACTIVE],
            'cancelled' => [Episciences_User_Assignment::STATUS_CANCELLED],
        ];
    }

    /**
     * @dataProvider reviewerLatestStatusProvider
     */
    public function testReviewerWhoseLatestAssignmentIsNotActiveIsNotFound(string $status): void
    {
        $docId = $this->insertPaper('2020-01-01 00:00:00');
        $this->assign($docId, Episciences_Acl::ROLE_REVIEWER, Episciences_User_Assignment::STATUS_ACTIVE, '2020-01-02 00:00:00');
        $this->assign($docId, Episciences_Acl::ROLE_REVIEWER, $status, '2020-01-03 00:00:00');

        self::assertSame([], self::rolesOf([$docId]));
        self::assertArrayNotHasKey(self::UID, Episciences_PapersManager::getReviewers($docId));
    }

    public function testReviewerInvitedWithoutAccountIsLeftAside(): void
    {
        // Same number, but in the table of the invited users: another person
        $docId = $this->insertPaper('2020-01-01 00:00:00');
        $otherDocId = $this->insertPaper('2020-02-01 00:00:00');
        $this->assign($docId, Episciences_Acl::ROLE_REVIEWER, Episciences_User_Assignment::STATUS_ACTIVE, '2020-01-02 00:00:00', self::UID, 1);
        $this->assign($otherDocId, Episciences_Acl::ROLE_REVIEWER, Episciences_User_Assignment::STATUS_ACTIVE, '2020-02-02 00:00:00');
        $this->assign($otherDocId, Episciences_Acl::ROLE_REVIEWER, Episciences_User_Assignment::STATUS_DECLINED, '2020-02-03 00:00:00', self::UID, 1);

        self::assertSame([], self::rolesOf([$docId]));
        self::assertSame(['reviewer'], self::rolesOf([$otherDocId]));
        self::assertArrayNotHasKey(self::UID, Episciences_PapersManager::getReviewers($docId));
        self::assertArrayHasKey(self::UID, Episciences_PapersManager::getReviewers($otherDocId));
    }

    /**
     * The same assignment as read on the version itself (Episciences_Paper::getEditor(), getCopyEditor())
     */
    private function isAssigned(string $role, int $docId): bool
    {
        $assigned = $role === Episciences_Acl::ROLE_EDITOR
            ? Episciences_PapersManager::getEditors($docId, true, false)
            : Episciences_PapersManager::getCopyEditors($docId, true, false);

        return is_array($assigned) && array_key_exists(self::UID, $assigned);
    }
}
