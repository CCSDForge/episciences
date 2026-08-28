<?php
declare(strict_types=1);

use Episciences\Paper\Export\Csv\Filters;
use Episciences\Paper\Export\Csv\PaperCsvExporter;
use Episciences\Paper\Import\ReviewResolver;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: export papers to a semicolon-separated CSV file, in the same
 * 19-column format import:papers reads — see Episciences\Paper\Import\Row for the exact meaning
 * of each column.
 *
 * volume_id/section_id are exported alongside the human-readable volume/section titles, but the
 * ids are what a later import:papers run will actually use to match volumes/sections; the titles
 * are for readability when editing the CSV. Re-importing a CSV whose "editors" column is filled
 * for an already-published paper re-triggers an assign+unassign (see
 * Episciences\Paper\Import\PaperImporter::processEditors()) — logged noise, not an error.
 *
 * One journal per run, resolved from --rvid (numeric RVID or RVCODE, see
 * Episciences\Paper\Import\ReviewResolver) — same constraint as import:papers, since RVID is a
 * process-wide PHP constant read directly by legacy code.
 */
final class ExportPapersCommand extends Command
{
    protected static $defaultName = 'export:papers';

    protected function configure(): void
    {
        $this
            ->setDescription('Export papers to a semicolon-separated CSV file, in the same format import:papers reads.')
            ->addOption('rvid', null, InputOption::VALUE_REQUIRED, 'Journal RVID (integer) or RVCODE')
            ->addOption('csv-file', null, InputOption::VALUE_REQUIRED, 'Path to write the CSV file to')
            ->addOption('volume-id', null, InputOption::VALUE_REQUIRED, 'Only export papers in this volume')
            ->addOption('section-id', null, InputOption::VALUE_REQUIRED, 'Only export papers in this section')
            ->addOption('year', null, InputOption::VALUE_REQUIRED, 'Only export papers published in this year')
            ->addOption('docid', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only export this docid (repeatable)')
            ->addOption('identifier', null, InputOption::VALUE_REQUIRED, 'Only export papers matching this archive identifier')
            ->addOption('version', null, InputOption::VALUE_REQUIRED, 'Only export this version of --identifier (ignored without --identifier)')
            ->addOption('status', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only export papers with this status (repeatable)')
            ->addOption('repoid', null, InputOption::VALUE_REQUIRED, 'Only export papers from this source repository')
            ->addOption('uid', null, InputOption::VALUE_REQUIRED, 'Only export papers submitted by this user')
            ->addOption('sql-where', null, InputOption::VALUE_REQUIRED, 'Additional raw SQL WHERE clause on PAPERS. TRUSTED INPUT ONLY — passed as-is to the query.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of papers to export');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rvidOrCode = (string)($input->getOption('rvid') ?? '');
        $csvFile = (string)($input->getOption('csv-file') ?? '');

        if ($rvidOrCode === '') {
            $io->error('Missing required option: --rvid');
            return Command::FAILURE;
        }

        if ($csvFile === '') {
            $io->error('Missing required option: --csv-file');
            return Command::FAILURE;
        }

        $io->title('Papers export');
        $this->bootstrap();

        $logger = new Logger('export-papers');
        $logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . 'export-papers_' . date('Y-m-d') . '.log',
            Logger::DEBUG
        ));
        if (!$io->isQuiet()) {
            $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }

        $review = ReviewResolver::resolve($rvidOrCode);
        if (!$review) {
            $logger->error('Journal not found', ['rvid' => $rvidOrCode]);
            $io->error("No journal found for RVID/RVCODE '{$rvidOrCode}'.");
            return Command::FAILURE;
        }

        define('RVID', $review->getRvid());
        defineJournalConstants($review->getCode());
        Zend_Registry::set('reviewSettingsDoi', $review->getDoiSettings());

        $filters = Filters::fromOptions([
            'volume-id' => $input->getOption('volume-id'),
            'section-id' => $input->getOption('section-id'),
            'year' => $input->getOption('year'),
            'docid' => $input->getOption('docid'),
            'identifier' => $input->getOption('identifier'),
            'version' => $input->getOption('version'),
            'status' => $input->getOption('status'),
            'repoid' => $input->getOption('repoid'),
            'uid' => $input->getOption('uid'),
            'sql-where' => $input->getOption('sql-where'),
            'limit' => $input->getOption('limit'),
        ], $review->getRvid());

        if ($filters->versionIgnored) {
            $logger->warning('--version given without --identifier — ignored (a version alone does not uniquely identify a paper)');
        }

        $handle = @fopen($csvFile, 'wb');
        if ($handle === false) {
            $logger->error("Cannot open {$csvFile} for writing");
            $io->error("Cannot open {$csvFile} for writing.");
            return Command::FAILURE;
        }

        try {
            $count = (new PaperCsvExporter($filters))->export($handle);
        } catch (\Throwable $e) {
            fclose($handle);
            $logger->error('Export failed: ' . $e->getMessage());
            $io->error('Export failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        if (fclose($handle) === false) {
            $logger->error("Failed to close {$csvFile}");
            $io->error("Failed to close {$csvFile}.");
            return Command::FAILURE;
        }

        if ($count === 0) {
            $logger->warning('No paper matched the given criteria — an empty CSV (header only) was written.');
            $io->warning("No paper matched the given criteria. {$csvFile} contains only the header row.");
        } else {
            $logger->info("Exported {$count} paper(s) to {$csvFile}");
            $io->success("Exported {$count} paper(s) to {$csvFile}.");
        }

        return Command::SUCCESS;
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

        // Do NOT call $application->bootstrap() — APPLICATION_MODULE may be undefined
        // (no rvcode) which causes Bootstrap::_initModule() to fail silently.
        // Mirrors ImportPapersCommand::bootstrap() / the legacy JournalScript pattern.
        $application = new Zend_Application('production', APPLICATION_PATH . '/configs/application.ini');

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);

        $db = Zend_Db::factory('PDO_MYSQL', $application->getOption('resources')['db']['params']);
        Zend_Db_Table::setDefaultAdapter($db);

        Zend_Registry::set('metadataSources', Episciences_Paper_MetaDataSourcesManager::all(false));
        Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));
    }
}
