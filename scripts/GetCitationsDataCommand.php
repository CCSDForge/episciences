<?php
declare(strict_types=1);

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: enrich citations data from OpenCitations + OpenAlex + Crossref.
 *
 * Replaces: scripts/getCitationsData.php (JournalScript)
 */
class GetCitationsDataCommand extends Command
{
    protected static $defaultName = 'enrichment:citations';

    protected function configure(): void
    {
        $this
            ->setDescription('Enrich citation metadata for all published papers via OpenCitations, OpenAlex and Crossref')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without writing to the database')
            ->addOption('rvcode', null, InputOption::VALUE_REQUIRED, 'Restrict processing to one journal (RV code)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $rvcode = $input->getOption('rvcode');

        $io->title('Citation data enrichment');

        // Bootstrap Episciences environment
        $this->bootstrap();

        $logger = new Logger('citationsEnrichment');
        $logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . 'citationsEnrichment_' . date('Y-m-d') . '.log', Logger::INFO));
        if (!$io->isQuiet()) {
            $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }

        if ($dryRun) {
            $io->note('Dry-run mode enabled — no data will be written.');
        }

        $rvid = null;
        if ($rvcode !== null) {
            $review = Episciences_ReviewsManager::findByRvcode((string) $rvcode);
            if (!$review instanceof Episciences_Review) {
                $io->error("No journal found for RV code '{$rvcode}'.");
                return Command::FAILURE;
            }
            $rvid = $review->getRvid();
            $logger->info("Filtering on journal: {$rvcode} (RVID {$rvid})");
        }

        $db     = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from(T_PAPERS, ['DOI', 'DOCID'])
            ->where('DOI != ""')
            ->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED)
            ->order('DOCID DESC');
        if ($rvid !== null) {
            $select->where('RVID = ?', $rvid);
        }

        $rows  = $db->fetchAll($select);
        $total = count($rows);
        $io->progressStart($total);

        foreach ($rows as $value) {
            // getOpenCitationCitedByDoi() now returns ?array directly (no PSR-6 CacheItem)
            $apiCallCitationCache = Episciences_OpencitationsTools::getOpenCitationCitedByDoi($value['DOI']);

            if ($apiCallCitationCache === null) {
                $logger->error('OpenCitations API error for DOI ' . $value['DOI']);
                $io->progressAdvance();
                continue;
            }

            if (!empty($apiCallCitationCache)) {
                if (!$dryRun) {
                    try {
                        Episciences_Paper_Citations_EnrichmentService::extractAndStore($apiCallCitationCache, (int) $value['DOCID']);
                    } catch (\Throwable $e) {
                        $logger->error('Citation enrichment error for doc ' . $value['DOCID'] . ': ' . get_class($e) . ': ' . $e->getMessage());
                    }
                }
                $logger->info('Citations processed for doc ' . $value['DOCID']);
            } else {
                $logger->info('No citations found for doc ' . $value['DOCID']);
            }

            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success('Citation data enrichment completed.');

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
        // Mirrors legacy JournalScript pattern: initApp() reads config, initDb() sets adapter.
        $application = new Zend_Application('production', APPLICATION_PATH . '/configs/application.ini');

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);

        $db = Zend_Db::factory('PDO_MYSQL', $application->getOption('resources')['db']['params']);
        Zend_Db_Table::setDefaultAdapter($db);

        Zend_Registry::set('metadataSources', Episciences_Paper_MetaDataSourcesManager::all(false));
        Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));
    }
}
