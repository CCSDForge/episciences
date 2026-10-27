<?php

declare(strict_types=1);

namespace unit\scripts;

use DeletePapersCommand;
use Episciences_Paper;
use Episciences_Review;
use Episciences_ReviewsManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;

require_once __DIR__ . '/../../../scripts/DeletePapersCommand.php';

/**
 * papers:delete deletes nothing until the operator types the confirmation phrase.
 * The journal is loaded without bootstrapping, and the deletion is replaced by a spy
 * (Episciences_PapersManager::deletePaper() has its own test): rows are rolled back after each test.
 *
 * @covers DeletePapersCommand
 */
final class DeletePapersCommandTest extends TestCase
{
    private Zend_Db_Adapter_Abstract $db;

    private Episciences_Review $journal;

    /** @var int[] */
    private array $deleted = [];

    protected function setUp(): void
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$db instanceof Zend_Db_Adapter_Abstract) {
            self::markTestSkipped('No database adapter available.');
        }
        $journal = Episciences_ReviewsManager::find(RVID);
        if (!$journal instanceof Episciences_Review) {
            self::markTestSkipped('No journal available.');
        }
        $this->db = $db;
        $this->journal = $journal;
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
    }

    public function testDeletesThePapersOnceTheConfirmationPhraseIsTyped(): void
    {
        $first = $this->insertPaper(RVID);
        $second = $this->insertPaper(RVID);

        $tester = $this->tester();
        $tester->setInputs([DeletePapersCommand::CONFIRMATION_PHRASE]);
        $status = $tester->execute(['docids' => ["{$first},{$second}"], '--rvcode' => $this->journal->getCode()]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame([$first, $second], $this->deleted);
    }

    public function testDeletesNothingWhenTheConfirmationPhraseIsWrong(): void
    {
        $docId = $this->insertPaper(RVID);

        $tester = $this->tester();
        $tester->setInputs(['yes']);
        $status = $tester->execute(['docids' => [(string)$docId], '--rvcode' => $this->journal->getCode()]);

        self::assertSame(Command::FAILURE, $status);
        self::assertSame([], $this->deleted);
        self::assertStringContainsString('Aborted', $tester->getDisplay());
    }

    public function testDeletesNothingInNonInteractiveMode(): void
    {
        $docId = $this->insertPaper(RVID);

        $status = $this->tester()->execute(
            ['docids' => [(string)$docId], '--rvcode' => $this->journal->getCode()],
            ['interactive' => false]
        );

        self::assertSame(Command::FAILURE, $status);
        self::assertSame([], $this->deleted);
    }

    public function testDryRunDeletesNothingWithoutAsking(): void
    {
        $docId = $this->insertPaper(RVID);

        $tester = $this->tester();
        $status = $tester->execute(
            ['docids' => [(string)$docId], '--rvcode' => $this->journal->getCode(), '--dry-run' => true],
            ['interactive' => false]
        );

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame([], $this->deleted);
        self::assertStringContainsString('would be deleted', $tester->getDisplay());
    }

    public function testFailsWhenAPaperBelongsToAnotherJournal(): void
    {
        $ownPaper = $this->insertPaper(RVID);
        $otherJournalPaper = $this->insertPaper(RVID + 1);

        $tester = $this->tester();
        $tester->setInputs([DeletePapersCommand::CONFIRMATION_PHRASE]);
        $status = $tester->execute(['docids' => [(string)$ownPaper, (string)$otherJournalPaper], '--rvcode' => $this->journal->getCode()]);

        self::assertSame(Command::FAILURE, $status);
        self::assertSame([$ownPaper], $this->deleted);
        self::assertStringContainsString('belongs to another journal', $tester->getDisplay());
    }

    public function testFailsWhenTheDeletionFails(): void
    {
        $docId = $this->insertPaper(RVID);

        $failingDeletion = static function (Episciences_Paper $paper): void {
            throw new RuntimeException('Lock wait timeout exceeded');
        };
        $tester = new CommandTester(new DeletePapersCommand($this->journalLoader(), $failingDeletion));
        $tester->setInputs([DeletePapersCommand::CONFIRMATION_PHRASE]);
        $status = $tester->execute(['docids' => [(string)$docId], '--rvcode' => $this->journal->getCode()]);

        self::assertSame(Command::FAILURE, $status);
        // The operator sees why the deletion failed
        self::assertStringContainsString('deletion failed', $tester->getDisplay());
        self::assertStringContainsString('Lock wait timeout exceeded', $tester->getDisplay());
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidInputProvider(): array
    {
        return [
            'missing rvcode' => [['docids' => ['1']]],
            'docid not a number' => [['docids' => ['12a'], '--rvcode' => 'any']],
            'docid zero' => [['docids' => ['0'], '--rvcode' => 'any']],
            'unknown journal' => [['docids' => ['1'], '--rvcode' => 'no-such-journal']],
        ];
    }

    /**
     * @dataProvider invalidInputProvider
     * @param array<string, mixed> $input
     */
    public function testRefusesAnInvalidInput(array $input): void
    {
        $status = $this->tester()->execute($input, ['interactive' => false]);

        self::assertSame(Command::FAILURE, $status);
        self::assertSame([], $this->deleted);
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new DeletePapersCommand($this->journalLoader(), function (Episciences_Paper $paper): void {
            $this->deleted[] = (int)$paper->getDocid();
        }));
    }

    /**
     * @return \Closure(string): ?Episciences_Review
     */
    private function journalLoader(): \Closure
    {
        return static function (string $rvcode): ?Episciences_Review {
            $journal = Episciences_ReviewsManager::find($rvcode);
            return $journal instanceof Episciences_Review ? $journal : null;
        };
    }

    private function insertPaper(int $rvid): int
    {
        $this->db->insert(T_PAPERS, [
            'PAPERID' => 999401,
            'RVID' => $rvid,
            'UID' => 1,
            'STATUS' => Episciences_Paper::STATUS_SUBMITTED,
            'IDENTIFIER' => 'delete-papers-command-test',
            'VERSION' => 1,
            'REPOID' => 1,
            'RECORD' => '<record/>',
            'WHEN' => '2020-01-02 03:04:05',
            'SUBMISSION_DATE' => '2020-01-02 03:04:05',
        ]);

        return (int)$this->db->lastInsertId();
    }
}
