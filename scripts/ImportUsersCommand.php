<?php
declare(strict_types=1);

use Episciences\Translation\TranslatorFactory;
use Episciences\User\Import\UserAccountStore;
use Episciences\User\Import\UserCsvReader;
use Episciences\User\Import\UserImporter;
use Episciences\User\Import\UserImportMailer;
use Episciences\User\Import\UserImportResult;
use Episciences\User\Token\AccountTokenRepository;
use scripts\Command\Bootstrapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: add people to a journal from a CSV file (firstname;lastname;email;role;lang).
 *
 * The account using the email is reused (the most recently modified valid one, otherwise the most recently modified
 * one not activated yet), or created not activated. Roles are only added. The whole file is checked first: nothing
 * is written when a line is invalid. See docs/console-commands.md and scripts/importSamples/sample-users.csv.
 */
#[AsCommand(name: 'journal:import-users', description: 'Add people to a journal from a CSV file, creating the accounts that do not exist.')]
class ImportUsersCommand extends Command
{
    /** @var Closure(string, InputInterface, OutputInterface): ?Episciences_Review */
    private readonly Closure $journalLoader;

    /** @var Closure(Episciences_Review, bool, string): UserImporter */
    private readonly Closure $importerFactory;

    /**
     * @param (Closure(string, InputInterface, OutputInterface): ?Episciences_Review)|null $journalLoader loads the journal
     *        from its RVCODE and prepares the application for it; bootstraps the application by default
     * @param (Closure(Episciences_Review, bool, string): UserImporter)|null $importerFactory importer for the journal,
     *        with or without mails, and the language of the new accounts
     */
    public function __construct(?Closure $journalLoader = null, ?Closure $importerFactory = null)
    {
        parent::__construct();
        $this->journalLoader = $journalLoader ?? $this->loadJournal(...);
        $this->importerFactory = $importerFactory ?? self::createImporter(...);
    }

    protected function configure(): void
    {
        $this
            ->addOption('rvcode', null, InputOption::VALUE_REQUIRED, 'Code of the journal')
            ->addOption('csv', null, InputOption::VALUE_REQUIRED, 'CSV file: firstname;lastname;email (required columns);role;lang (optional columns)')
            ->addOption('lang', null, InputOption::VALUE_REQUIRED, 'Language of the new accounts without a language in the file (default: the journal default language)')
            ->addOption('no-mail', null, InputOption::VALUE_NONE, 'Do not send any mail')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Check the file and show what would be done, without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Users import');

        $rvcode = trim((string)($input->getOption('rvcode') ?? ''));
        $csv = trim((string)($input->getOption('csv') ?? ''));
        if ($rvcode === '' || $csv === '') {
            $io->error('Missing required option: --rvcode and --csv');
            return Command::FAILURE;
        }

        $journal = ($this->journalLoader)($rvcode, $input, $output);
        if (!$journal instanceof Episciences_Review) {
            $io->error("Unknown journal: $rvcode");
            return Command::FAILURE;
        }

        // the language of an account is used in every journal: the platform languages are accepted
        $langs = Episciences_Translation_Plugin::getAvailableLanguages();
        $lang = strtolower(trim((string)($input->getOption('lang') ?? ''))) ?: (string)Episciences_Review::getDefaultLanguage();
        if (!in_array($lang, $langs, true)) {
            $io->error(sprintf('Unavailable language "%s" (available: %s)', $lang, implode(', ', $langs)));
            return Command::FAILURE;
        }

        $roles = array_values(array_map('strval', (new Episciences_Acl())->getRolesCodes()));
        ['rows' => $rows, 'errors' => $errors] = (new UserCsvReader($roles, $langs))->read($csv);
        if ($errors !== []) {
            $io->error(array_merge(['Invalid file: nothing was imported.'], $errors));
            return Command::FAILURE;
        }

        $dryRun = (bool)$input->getOption('dry-run');
        if ($dryRun) {
            $io->note('Dry-run mode enabled: nothing will be written, no mail will be sent.');
        }

        $importer = ($this->importerFactory)($journal, !$input->getOption('no-mail'), $lang);
        $results = $importer->import($rows, $dryRun);

        return $this->report($io, $journal, $results, $dryRun);
    }

    /**
     * @param list<UserImportResult> $results
     */
    private function report(SymfonyStyle $io, Episciences_Review $journal, array $results, bool $dryRun): int
    {
        $io->table(
            ['Line', 'Email', 'UID', 'Account', 'Roles added', 'Mail'],
            array_map(static fn(UserImportResult $r): array => [
                $r->line,
                $r->email,
                $r->uid ?? '-',
                $r->action,
                $r->addedRoles === [] ? '-' : implode(', ', $r->addedRoles),
                $r->mail->value,
            ], $results)
        );

        foreach ($results as $result) {
            foreach ($result->warnings as $warning) {
                $io->warning(sprintf('line %d (%s): %s', $result->line, $result->email, $warning));
            }
        }

        $failed = count(array_filter($results, static fn(UserImportResult $r): bool => $r->action === UserImportResult::ACTION_FAILED));
        if ($failed > 0) {
            $io->error(sprintf('%d of %d lines failed.', $failed, count($results)));
            return Command::FAILURE;
        }

        $io->success(sprintf(
            '%d lines %s for journal "%s".',
            count($results),
            $dryRun ? 'checked' : 'imported',
            $journal->getCode()
        ));

        return Command::SUCCESS;
    }

    /**
     * Bootstraps the application for the journal: mails need its constants (URL, name), translations and languages
     */
    private function loadJournal(string $rvcode, InputInterface $input, OutputInterface $output): ?Episciences_Review
    {
        // No $_SERVER['HTTPS'] in CLI: defineProtocol() alone would build http:// links in mails
        defined('SERVER_PROTOCOL') || define('SERVER_PROTOCOL', 'https');

        $bootstrapper = new Bootstrapper(null, false);
        $bootstrapper->initialize($input, $output, 'import_users');

        $journal = Episciences_ReviewsManager::find($rvcode);
        if (!$journal instanceof Episciences_Review) {
            return null;
        }

        // Journal constants are defined once the journal is known: they cannot be redefined afterwards
        defined('RVID') || define('RVID', $journal->getRvid());
        defined('RVNAME') || define('RVNAME', $journal->getName());
        defineJournalConstants($journal->getCode());

        $languages = (new Ccsd_Website_Common($journal->getRvid(), ['sidField' => 'SID']))->getLanguages();
        Zend_Registry::set('languages', $languages ?: Episciences_Translation_Plugin::getAvailableLanguages());
        Zend_Registry::set('Zend_Translate', TranslatorFactory::create(APPLICATION_PATH . '/languages', null, Episciences_Review::DEFAULT_LANG));

        return $journal;
    }

    /**
     * Application path of a route, like the view url() helper: /controller/action/name/value
     *
     * @param array<string, string> $params
     */
    public static function buildPath(array $params): string
    {
        $path = '/' . rawurlencode($params['controller'] ?? 'index') . '/' . rawurlencode($params['action'] ?? 'index');
        foreach (array_diff_key($params, ['controller' => true, 'action' => true]) as $name => $value) {
            $path .= '/' . rawurlencode((string)$name) . '/' . rawurlencode($value);
        }

        return $path;
    }

    private static function createImporter(Episciences_Review $journal, bool $withMails, string $lang): UserImporter
    {
        $mailer = $withMails
            ? new UserImportMailer(new AccountTokenRepository(), self::buildPath(...))
            : null;

        return new UserImporter(new UserAccountStore(), $mailer, $journal->getRvid(), $lang);
    }
}
