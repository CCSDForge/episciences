<?php
declare(strict_types=1);

use Episciences\Journal\Demo\DemoSeeder;
use Episciences\Paper\Import\ReviewResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: seed a journal with a small set of real demo submissions for
 * manual sandbox testing, without touching production data.
 *
 * A thin wrapper around Episciences\Journal\Demo\DemoSeeder, which itself reuses the same
 * import machinery `import:papers` uses (Episciences\Paper\Import\*): volumes/sections are
 * created on the fly from the CSV's titles, and papers are matched against existing ones so a
 * second run updates instead of duplicating. See scripts/importSamples/demo-papers.csv for the
 * dataset (real, fixed HAL/arXiv/Zenodo/BAOBAB identifiers in submitted/accepted/published
 * status) and docs/journal-provisioning.md for the full sandbox recipe.
 *
 * bootstrap() here follows the same pattern as ImportPapersCommand: unlike journal:create, this
 * command targets an *existing* journal, whose data directory already exists — so
 * defineJournalConstants() can safely be called with no rvcode up front, then locked to the
 * resolved journal via lockJournal(), exactly like import:papers.
 */
final class SeedJournalDemoCommand extends Command
{
    protected static $defaultName = 'journal:seed-demo';

    protected function configure(): void
    {
        $this
            ->setDescription('Seed a journal with a small set of real demo submissions (volumes, sections, papers) for sandbox testing.')
            ->addOption('rvcode', null, InputOption::VALUE_REQUIRED, 'Journal RVID or RVCODE to seed')
            ->addOption('uid', null, InputOption::VALUE_REQUIRED, 'UID to own all demo papers')
            ->addOption('csv-file', null, InputOption::VALUE_REQUIRED, 'Path to the demo CSV file', __DIR__ . '/importSamples/demo-papers.csv')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Allow seeding a journal whose status is enabled (production)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate the seeding without writing to the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool)$input->getOption('dry-run');
        $force = (bool)$input->getOption('force');

        $rvcodeOrId = trim((string)($input->getOption('rvcode') ?? ''));
        $uidOption = $input->getOption('uid');
        $csvFile = (string)$input->getOption('csv-file');

        if ($rvcodeOrId === '') {
            $io->error('Missing required option: --rvcode');
            return Command::FAILURE;
        }

        if ($uidOption === null || !ctype_digit((string)$uidOption)) {
            $io->error('Missing or invalid required option: --uid (must be a positive integer)');
            return Command::FAILURE;
        }

        $uid = (int)$uidOption;

        $io->title('Journal demo seeding');
        $this->bootstrap();

        $review = ReviewResolver::resolve($rvcodeOrId);
        if (!$review) {
            $io->error("Journal '$rvcodeOrId' not found.");
            return Command::FAILURE;
        }

        if ($review->getStatus() === Episciences_Review::ENABLED && !$force) {
            $io->error("Journal '{$review->getCode()}' is enabled (production). Use --force to seed demo content into it anyway.");
            return Command::FAILURE;
        }

        $user = new Episciences_User();
        if ($user->find($uid) === []) {
            $io->error("No user found with UID $uid.");
            return Command::FAILURE;
        }

        if (!file_exists($csvFile) || !is_readable($csvFile)) {
            $io->error("CSV file not found or not readable: $csvFile");
            return Command::FAILURE;
        }

        if ($dryRun) {
            $io->note('Dry-run mode enabled — no data will be written.');
        }

        $this->lockJournal($review);

        $seeder = new DemoSeeder($dryRun);

        try {
            $report = $seeder->seed($review->getRvid(), $uid, $csvFile);
        } catch (Throwable $e) {
            $io->error('Demo seeding failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->section($dryRun ? 'Plan' : 'Result');
        $io->table(['Step', 'Detail'], $report->toRows());

        $hasErrors = $report->warnings() !== [];
        foreach ($report->warnings() as $warning) {
            $io->warning($warning);
        }

        if ($hasErrors) {
            $io->warning('Demo seeding completed with errors — see above.');
        } else {
            $io->success("Demo content seeded into '{$review->getCode()}'.");
        }

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Locks this run to a single journal, since RVID is a process-wide PHP constant that
     * legacy code (Episciences_Volume, Episciences_Submit, ...) reads directly. Mirrors
     * ImportPapersCommand::lockJournal().
     */
    private function lockJournal(Episciences_Review $review): void
    {
        define('RVID', $review->getRvid());
        defineJournalConstants($review->getCode());
        Zend_Registry::set('reviewSettingsDoi', $review->getDoiSettings());

        if (is_dir(REVIEW_PATH . 'languages') && count(scandir(REVIEW_PATH . 'languages') ?: []) > 2) {
            Zend_Registry::get('Zend_Translate')->addTranslation(REVIEW_PATH . 'languages');
        }
    }

    private function bootstrap(): void
    {
        if (!defined('APPLICATION_PATH')) {
            define('APPLICATION_PATH', realpath(__DIR__ . '/../application'));
        }
        require_once __DIR__ . '/../public/const.php';
        require_once __DIR__ . '/../public/bdd_const.php';

        defineProtocol();
        defineSimpleConstants();
        defineSQLTableConstants();
        defineApplicationConstants();
        defineJournalConstants();

        $libraries = [realpath(APPLICATION_PATH . '/../library')];
        set_include_path(implode(PATH_SEPARATOR, array_merge($libraries, [get_include_path()])));
        require_once 'Zend/Application.php';

        $application = new Zend_Application('production', APPLICATION_PATH . '/configs/application.ini');

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);

        $db = Zend_Db::factory('PDO_MYSQL', $application->getOption('resources')['db']['params']);
        Zend_Db_Table::setDefaultAdapter($db);

        Zend_Registry::set('metadataSources', Episciences_Paper_MetaDataSourcesManager::all(false));
        Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));

        if (!Zend_Registry::isRegistered('Zend_Translate')) {
            Zend_Registry::set('Zend_Translate', new Zend_Translate([
                'adapter' => Zend_Translate::AN_ARRAY,
                'content' => ['' => ''],
                'locale' => 'en',
            ]));
        }
        Zend_Registry::isRegistered('lang') || Zend_Registry::set('lang', 'en');
    }
}
