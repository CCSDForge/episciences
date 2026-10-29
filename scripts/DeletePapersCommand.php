<?php
declare(strict_types=1);

use Episciences\AppRegistry;
use Episciences\Paper\Delete\DeletionOutcome;
use Episciences\Paper\Delete\PaperDeleter;
use scripts\Command\Bootstrapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: delete paper versions of one journal, with their files and every related row
 * (see Episciences_PapersManager::delete()).
 *
 * Deleting cannot be undone: the operator must type CONFIRMATION_PHRASE, so the command always refuses
 * to delete in non-interactive mode. --dry-run only checks the papers, without asking.
 *
 * Replaces: scripts/DeletePaper.php (JournalScript, interactive)
 */
#[AsCommand(name: 'papers:delete', description: 'Delete paper versions of a journal, with their files and every related row.')]
class DeletePapersCommand extends Command
{
    public const CONFIRMATION_PHRASE = 'Yes delete I understand the consequences';

    /** @var Closure(string, InputInterface, OutputInterface): ?Episciences_Review */
    private readonly Closure $journalLoader;

    /**
     * @param (Closure(string, InputInterface, OutputInterface): ?Episciences_Review)|null $journalLoader loads the journal
     *        from its RVCODE and prepares the application for it; bootstraps the application by default
     * @param (Closure(Episciences_Paper): void)|null $deleteVersion deletes a paper version, see PaperDeleter
     */
    public function __construct(?Closure $journalLoader = null, private readonly ?Closure $deleteVersion = null)
    {
        parent::__construct();
        $this->journalLoader = $journalLoader ?? $this->loadJournal(...);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('docids', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'DOCIDs of the paper versions to delete, separated by spaces or commas')
            ->addOption('rvcode', null, InputOption::VALUE_REQUIRED, 'Code of the journal the papers belong to')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Check the papers without deleting anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Papers deletion');

        $rvcode = trim((string)($input->getOption('rvcode') ?? ''));
        if ($rvcode === '') {
            $io->error('Missing required option: --rvcode');
            return Command::FAILURE;
        }

        $docIds = self::parseDocIds((array)$input->getArgument('docids'));
        if ($docIds === null) {
            $io->error('DOCIDs must be positive integers.');
            return Command::FAILURE;
        }

        $journal = ($this->journalLoader)($rvcode, $input, $output);
        if (!$journal instanceof Episciences_Review) {
            $io->error("Unknown journal: {$rvcode}");
            return Command::FAILURE;
        }

        $dryRun = (bool)$input->getOption('dry-run');
        if ($dryRun) {
            $io->note('Dry-run mode enabled: nothing will be deleted.');
        } elseif (!$this->confirm($io, $journal, $docIds)) {
            $io->warning('Aborted: nothing was deleted.');
            return Command::FAILURE;
        }

        return $this->deletePapers($io, new PaperDeleter($journal->getRvid(), $this->deleteVersion), $journal, $docIds, $dryRun);
    }

    /**
     * @param string[] $arguments
     * @return int[]|null null when a DOCID is not a positive integer
     */
    private static function parseDocIds(array $arguments): ?array
    {
        $values = preg_split('/[\s,;]+/', implode(',', $arguments), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $docIds = [];

        foreach ($values as $value) {
            if (!ctype_digit($value) || (int)$value === 0) {
                return null;
            }
            $docIds[] = (int)$value;
        }

        return $docIds === [] ? null : array_values(array_unique($docIds));
    }

    /**
     * @param int[] $docIds
     */
    private function confirm(SymfonyStyle $io, Episciences_Review $journal, array $docIds): bool
    {
        $io->caution(sprintf(
            'You are about to delete %d paper version(s) of %s (%s): %s. Their files and every related row ' .
            '(assignments, reports, comments, logs...) are deleted too. This cannot be undone.',
            count($docIds),
            $journal->getCode(),
            $journal->getName(),
            implode(', ', $docIds)
        ));

        // In non-interactive mode the answer is null: deleting is refused
        $answer = $io->ask(sprintf('To confirm, type "%s"', self::CONFIRMATION_PHRASE));

        return is_string($answer) && trim($answer) === self::CONFIRMATION_PHRASE;
    }

    /**
     * @param int[] $docIds
     */
    private function deletePapers(SymfonyStyle $io, PaperDeleter $deleter, Episciences_Review $journal, array $docIds, bool $dryRun): int
    {
        $rows = [];
        $failures = 0;

        foreach ($docIds as $docId) {
            try {
                $outcome = $deleter->delete($docId, $dryRun);
                $result = $outcome->value;
            } catch (Throwable $e) {
                $outcome = DeletionOutcome::Failed;
                $result = sprintf('%s: %s', $outcome->value, $e->getMessage());
                AppRegistry::getMonoLogger()?->error(sprintf('papers:delete: paper #%d of %s not deleted: %s', $docId, $journal->getCode(), $e->getMessage()));
            }
            $rows[] = [$docId, $result];

            if (!$outcome->isSuccess()) {
                $failures++;
            } elseif (!$dryRun) {
                AppRegistry::getMonoLogger()?->info(sprintf('papers:delete: paper #%d of %s deleted', $docId, $journal->getCode()));
            }
        }

        $io->table(['DOCID', 'Result'], $rows);

        if ($failures > 0) {
            $io->error(sprintf('%d paper version(s) out of %d %s.', $failures, count($docIds), $dryRun ? 'cannot be deleted' : 'could not be deleted'));
            return Command::FAILURE;
        }

        $io->success($dryRun ? 'Every paper version can be deleted.' : 'Paper versions deleted.');

        return Command::SUCCESS;
    }

    /**
     * Bootstraps the application for the journal: the files of a paper are deleted from the journal directory,
     * which the journal constants point to. Registers the command logger as the application logger,
     * so that deletions are logged.
     */
    private function loadJournal(string $rvcode, InputInterface $input, OutputInterface $output): ?Episciences_Review
    {
        $bootstrapper = new Bootstrapper(null, false);
        $bootstrapper->initialize($input, $output, 'papers_delete');
        AppRegistry::set('appLogger', $bootstrapper->getLogger());

        $journal = Episciences_ReviewsManager::find($rvcode);
        if (!$journal instanceof Episciences_Review) {
            return null;
        }

        // Journal constants are defined once the journal is known: they cannot be redefined afterwards
        defineJournalConstants($journal->getCode());

        return $journal;
    }
}
