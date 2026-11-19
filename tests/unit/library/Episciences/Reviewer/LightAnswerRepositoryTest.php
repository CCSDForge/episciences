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
                created_at TEXT NOT NULL,
                updated_at TEXT NULL
            )'
        );
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
