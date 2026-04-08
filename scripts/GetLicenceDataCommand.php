<?php
declare(strict_types=1);

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Symfony Console command: enrich licence data from repository APIs.
 *
 * Replaces: scripts/getLicenceDataEnrichment.php (JournalScript)
 */
class GetLicenceDataCommand extends Command
{
    protected static $defaultName = 'enrichment:licences';

    private const ONE_MONTH = 3600 * 24 * 31;

    protected function configure(): void
    {
        $this
            ->setDescription('Enrich licence data for all papers from repository APIs')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without writing to the database')
            ->addOption('rvcode', null, InputOption::VALUE_REQUIRED, 'Restrict processing to one journal (RV code)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $rvcode = $input->getOption('rvcode');

        $io->title('Licence data enrichment');

        $this->bootstrap();

        $logger = new Logger('licenceEnrichment');
        $logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . 'licenceEnrichment_' . date('Y-m-d') . '.log', Logger::INFO));
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
            ->from(T_PAPERS, ['IDENTIFIER', 'DOCID', 'REPOID', 'VERSION'])
            ->where('REPOID != ?', 0)
            ->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED)
            ->order('REPOID DESC');
        if ($rvid !== null) {
            $select->where('RVID = ?', $rvid);
        }

        $rows = $db->fetchAll($select);
        $io->progressStart(count($rows));

        $cache = new FilesystemAdapter('enrichmentLicences', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');

        foreach ($rows as $value) {
            $identifier = $value['IDENTIFIER'];
            $repoId     = (int) $value['REPOID'];
            $docId      = (int) $value['DOCID'];
            $version    = (int) $value['VERSION'];

            // Clean old-style arXiv identifiers
            if (strpos($identifier, '.LO/') !== false) {
                $identifier = str_replace('.LO/', '/', $identifier);
            }

            $cacheKey = md5($identifier) . '_licence.json';
            $item     = $cache->getItem($cacheKey);
            $item->expiresAfter(self::ONE_MONTH);

            if (!$item->isHit()) {
                $logger->info("Fetching licence data for {$identifier}");
                $callArrayResp = Episciences_Paper_LicenceManager::getApiResponseByRepoId($repoId, $identifier, $version);

                if (!$dryRun) {
                    Episciences_Paper_LicenceManager::insertLicenceFromApiByRepoId((string) $repoId, $callArrayResp, $docId, $identifier);
                }

                $item->set(json_encode($callArrayResp, JSON_THROW_ON_ERROR));
                $cache->save($item);
            } else {
                $logger->info("Licence data from cache for {$identifier}");
            }

            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success('Licence data enrichment completed.');

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
