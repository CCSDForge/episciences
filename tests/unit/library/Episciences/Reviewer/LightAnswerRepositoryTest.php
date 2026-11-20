<?php

declare(strict_types=1);

namespace unit\library\Episciences\Reviewer;

use DateTimeImmutable;
use Episciences\Reviewer\LightAnswer;
use Episciences\Reviewer\LightAnswerRepository;
use PHPUnit\Framework\TestCase;
use Zend_Db_Adapter_Pdo_Sqlite;

/**
 * Answers given without an account (#319), run against an in-memory SQLite copy of the table
 *
 * @covers \Episciences\Reviewer\LightAnswer
 * @covers \Episciences\Reviewer\LightAnswerRepository
 */
final class LightAnswerRepositoryTest extends TestCase
{
    private Zend_Db_Adapter_Pdo_Sqlite $db;
    private LightAnswerRepository $repository;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required');
        }

        $this->db = new Zend_Db_Adapter_Pdo_Sqlite(['dbname' => ':memory:']);
        $this->db->query(
            'CREATE TABLE review_light_answer (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                aid INTEGER NOT NULL UNIQUE,
                rvid INTEGER NOT NULL,
                docid INTEGER NOT NULL,
                request_type_id INTEGER NOT NULL,
                uid INTEGER NOT NULL,
                tmp_user INTEGER NOT NULL DEFAULT 0,
                linked_uid INTEGER NULL,
                linked_at TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NULL
            )'
        );
        $this->db->query('CREATE TABLE USER_INVITATION (ID INTEGER PRIMARY KEY AUTOINCREMENT, AID INTEGER NOT NULL)');
        $this->repository = new LightAnswerRepository($this->db);
    }

    private function answer(int $aid = 10, int $docid = 5): LightAnswer
    {
        return new LightAnswer(null, $aid, 3, $docid, 11, 42, true, new DateTimeImmutable('2026-01-01 10:00:00'));
    }

    public function testAnAnswerIsSavedThenEditedWithoutChangingItsInvitation(): void
    {
        $saved = $this->repository->save($this->answer());
        self::assertNotNull($saved->getId());

        $this->repository->save($saved->edited(new DateTimeImmutable('2026-01-02 09:00:00')));

        $reloaded = $this->repository->findByAid(10);
        self::assertNotNull($reloaded);
        self::assertSame(10, $reloaded->getAid());
        self::assertSame(11, $reloaded->getRequestTypeId());
        self::assertSame('2026-01-01 10:00:00', $reloaded->getCreatedAt()->format('Y-m-d H:i:s'));
        self::assertSame('2026-01-02 09:00:00', $reloaded->getUpdatedAt()?->format('Y-m-d H:i:s'));
        self::assertTrue($reloaded->isTmpUser());
        self::assertSame('reports/light_10/', $reloaded->folder());
    }

    public function testTheAnswersOfAPaperAreKeyedByInvitation(): void
    {
        $this->repository->save($this->answer(10));
        $this->repository->save($this->answer(11));
        $this->repository->save($this->answer(12, 6));

        self::assertSame([10, 11], array_keys($this->repository->findByDocid(5)));
        self::assertNull($this->repository->findByAid(99));
    }

    public function testTheFilledGridOfAnAnswerIsInTheFolderOfItsInvitation(): void
    {
        self::assertSame('reports/light_10/', LightAnswer::folderOf(10));
        self::assertSame(LightAnswer::folderOf(10), $this->answer(10)->folder());
    }

    /**
     * An answer of journal 3 with its invitation rows (the first one is the row of the link)
     */
    private function saveWithInvitation(int $aid, int $uid, bool $tmpUser, string $createdAt = '2026-01-01 10:00:00'): LightAnswer
    {
        $this->db->insert('USER_INVITATION', ['AID' => $aid]);
        $this->db->insert('USER_INVITATION', ['AID' => $aid]);

        return $this->repository->save(new LightAnswer(null, $aid, 3, 5, 11, $uid, $tmpUser, new DateTimeImmutable($createdAt)));
    }

    public function testAnAnswerIsAssociatedWithAnAccountAndKeepsItWhenEdited(): void
    {
        $saved = $this->repository->save($this->answer());
        self::assertFalse($saved->isOfAccount(77));

        $linked = $this->repository->link($saved, 77, new DateTimeImmutable('2026-01-03 08:00:00'));
        self::assertTrue($linked->isOfAccount(77));
        $this->repository->save($linked->edited(new DateTimeImmutable('2026-01-04 08:00:00')));

        $reloaded = $this->repository->findByAid(10);
        self::assertNotNull($reloaded);
        self::assertSame(77, $reloaded->getLinkedUid());
        self::assertSame('2026-01-03 08:00:00', $reloaded->getLinkedAt()?->format('Y-m-d H:i:s'));
        // still an answer of the temporary person: nothing else changes
        self::assertTrue($reloaded->isTmpUser());
        self::assertSame(42, $reloaded->getUid());
    }

    public function testAnAnswerBelongsToTheInvitedAccountOrToTheAccountItIsAssociatedWith(): void
    {
        $byAccount = new LightAnswer(null, 10, 3, 5, 11, 42, false, new DateTimeImmutable());
        $byTemporaryPerson = new LightAnswer(null, 11, 3, 5, 11, 42, true, new DateTimeImmutable());

        self::assertTrue($byAccount->isOfAccount(42));
        // a temporary id is not an account
        self::assertFalse($byTemporaryPerson->isOfAccount(42));
        self::assertTrue($byTemporaryPerson->linkedTo(43, new DateTimeImmutable())->isOfAccount(43));
        self::assertFalse($byAccount->isOfAccount(0));
    }

    public function testTheAnswersOfAnAccountAreListedWithTheRowOfTheirInvitationLink(): void
    {
        $this->saveWithInvitation(20, 77, false, '2026-01-01 10:00:00');
        $linked = $this->saveWithInvitation(21, 500, true, '2026-01-02 10:00:00');
        $this->repository->link($linked, 77, new DateTimeImmutable('2026-01-05 10:00:00'));
        // a temporary person whose id is the same number as the account, and the answer of someone else
        $this->saveWithInvitation(22, 77, true);
        $this->saveWithInvitation(23, 78, false);
        // another journal
        $this->db->insert('USER_INVITATION', ['AID' => 24]);
        $this->repository->save(new LightAnswer(null, 24, 4, 5, 11, 77, false, new DateTimeImmutable()));

        $found = $this->repository->findForAccount(77, 3);

        self::assertSame([21, 20], array_map(static fn(array $row): int => $row['answer']->getAid(), $found));
        self::assertSame([3, 1], array_column($found, 'invitation_id'));
    }

    public function testAnAnswerCanBeEditedUntilTheEndOfTheDueDate(): void
    {
        $now = new DateTimeImmutable('2026-03-10 18:00:00');

        self::assertTrue(LightAnswer::isEditable('2026-03-10 00:00:00', $now));
        self::assertTrue(LightAnswer::isEditable('2026-03-11', $now));
        self::assertFalse(LightAnswer::isEditable('2026-03-09 23:59:59', $now));
        // no due date: always
        self::assertTrue(LightAnswer::isEditable(null, $now));
        self::assertTrue(LightAnswer::isEditable('', $now));
    }
}
