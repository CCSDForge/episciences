<?php

declare(strict_types=1);

namespace unit\library\Episciences\Reviewer;

use DateTimeImmutable;
use Episciences\Reviewer\LightAnswer;
use Episciences\Reviewer\LightAnswerRepository;
use InvalidArgumentException;
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
                message TEXT,
                file TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NULL
            )'
        );
        $this->repository = new LightAnswerRepository($this->db);
    }

    private function answer(int $aid = 10, string $message = 'Interesting', ?string $file = null, int $docid = 5): LightAnswer
    {
        return new LightAnswer(null, $aid, 3, $docid, 11, 42, true, $message, $file, new DateTimeImmutable('2026-01-01 10:00:00'));
    }

    public function testAnAnswerIsSavedThenEditedWithoutChangingItsInvitation(): void
    {
        $saved = $this->repository->save($this->answer());
        self::assertNotNull($saved->getId());

        $this->repository->save($saved->edited('Even more interesting', 'opinion.pdf', new DateTimeImmutable('2026-01-02 09:00:00')));

        $reloaded = $this->repository->findByAid(10);
        self::assertNotNull($reloaded);
        self::assertSame('Even more interesting', $reloaded->getMessage());
        self::assertSame('opinion.pdf', $reloaded->getFile());
        self::assertSame('2026-01-01 10:00:00', $reloaded->getCreatedAt()->format('Y-m-d H:i:s'));
        self::assertSame('2026-01-02 09:00:00', $reloaded->getUpdatedAt()?->format('Y-m-d H:i:s'));
        self::assertTrue($reloaded->isTmpUser());
        self::assertSame('reports/light_10/', $reloaded->folder());
    }

    public function testTheAnswersOfAPaperAreKeyedByInvitation(): void
    {
        $this->repository->save($this->answer(10));
        $this->repository->save($this->answer(11, 'Another one'));
        $this->repository->save($this->answer(12, 'Other paper', null, 6));

        self::assertSame([10, 11], array_keys($this->repository->findByDocid(5)));
        self::assertNull($this->repository->findByAid(99));
    }

    public function testAnAnswerNeedsAMessageOrAFile(): void
    {
        self::assertSame('opinion.pdf', $this->answer(10, '', 'opinion.pdf')->getFile());

        $this->expectException(InvalidArgumentException::class);
        $this->answer(10, '');
    }

    public function testATooLongMessageIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->answer(10, str_repeat('a', LightAnswer::MESSAGE_MAX_LENGTH + 1));
    }

    public function testThePostedMessageIsKeptAsPlainText(): void
    {
        self::assertSame("Line 1\nLine 2 bold", LightAnswer::cleanMessage("  Line 1\r\nLine 2 <b>bold</b><script>x</script>  "));
        self::assertSame('', LightAnswer::cleanMessage(['not', 'a', 'string']));
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
