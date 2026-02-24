<?php
declare(strict_types=1);

use Episciences\Api\OpenAireApiClient;
use GuzzleHttp\Client;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: enrich JEL classification data from OpenAIRE.
 *
 * Replaces: scripts/getClassificationJEL.php (JournalScript)
 */
class GetClassificationJelCommand extends Command
{
    protected static $defaultName = 'enrichment:classifications-jel';
    private const ONE_MONTH = 3600 * 24 * 31;

    protected function configure(): void
    {
        $this
            ->setDescription('Enrich JEL classification data from the OpenAIRE Research Graph')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without writing to the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $io->title('JEL classification enrichment');
        $this->bootstrap();

        $logger = new Logger('jelEnrichment');
        $logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . 'jelEnrichment_' . date('Y-m-d') . '.log', Logger::INFO
        ));
        if (!$io->isQuiet()) {
            $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }
        if ($dryRun) {
            $io->note('Dry-run mode enabled — no data will be written.');
        }

        $cacheDir  = dirname(APPLICATION_PATH) . '/cache/';
        $apiClient = new OpenAireApiClient(
            new Client(),
            new FilesystemAdapter('openAireResearchGraph', self::ONE_MONTH, $cacheDir),
            new FilesystemAdapter('enrichmentAuthors',     self::ONE_MONTH, $cacheDir),
            new FilesystemAdapter('enrichmentFunding',     self::ONE_MONTH, $cacheDir),
            $logger
        );

        $db       = Zend_Db_Table_Abstract::getDefaultAdapter();
        $allCodes = $db->fetchCol($db->select()->from(T_PAPER_CLASSIFICATION_JEL, ['code']));
        $papers   = $db->fetchAll(
            $db->select()
                ->from(T_PAPERS, ['DOI', 'DOCID'])
                ->where('DOI != ""')
                ->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED)
                ->order('DOCID ASC')
        );

        $logger->info('Starting JEL enrichment for ' . count($papers) . ' papers');
        $io->progressStart(count($papers));

        foreach ($papers as $row) {
            $doi   = $row['DOI'];
            $docId = (int) $row['DOCID'];
            try {
                $response = $apiClient->fetchPublication($doi, $docId);
                $codes    = $response !== null ? $apiClient->extractJelCodes($response) : [];

                if (!empty($codes)) {
                    $logger->info("JEL codes found for DOI {$doi}: " . implode(', ', $codes));
                } else {
                    $logger->info("No JEL codes found for DOI {$doi}");
                }

                $collection = $this->buildClassifications($codes, $docId, $allCodes, $logger);

                if (!$dryRun && !empty($collection)) {
                    $n = Episciences_Paper_ClassificationsManager::insert($collection);
                    $logger->info("{$n} JEL classifications inserted/updated for DOCID {$docId}");
                }
            } catch (\Throwable $e) {
                $logger->error("JEL enrichment error for DOI {$doi}: " . $e->getMessage());
            }
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success('JEL classification enrichment completed.');
        return Command::SUCCESS;
    }

    /**
     * @param array<string> $codes
     * @param array<string> $allCodes
     * @return array<Episciences_Paper_Classifications>
     */
    private function buildClassifications(array $codes, int $docId, array $allCodes, Logger $logger): array
    {
        $collection = [];
        foreach ($codes as $code) {
            $classification = new Episciences_Paper_Classifications();
            $classification->setClassificationName(Episciences\Classification\jel::$classificationName);
            try {
                $classification->checkClassificationCode($code, $allCodes);
            } catch (Zend_Exception $e) {
                $logger->warning("JEL code [{$code}] ignored: " . $e->getMessage());
                continue;
            }
            $classification->setClassificationCode($code);
            $classification->setDocid($docId);
            $classification->setSourceId((int) Episciences_Repositories::GRAPH_OPENAIRE_ID);
            $collection[] = $classification;
        }
        return $collection;
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
