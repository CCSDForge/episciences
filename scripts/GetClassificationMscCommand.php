<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: enrich MSC 2020 classification data from zbMath.
 *
 * Replaces: scripts/getClassificationMsc.php (JournalScript)
 */
class GetClassificationMscCommand extends Command
{
    protected static $defaultName = 'enrichment:classifications-msc';
    private const ONE_MONTH = 3600 * 24 * 31;

    protected function configure(): void
    {
        $this
            ->setDescription('Enrich MSC 2020 classification data from zbMath Open API')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without writing to the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $io->title('MSC 2020 classification enrichment');
        $this->bootstrap();

        $logger = new Logger('mscEnrichment');
        $logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . 'mscEnrichment_' . date('Y-m-d') . '.log', Logger::INFO
        ));
        if (!$io->isQuiet()) {
            $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }
        if ($dryRun) {
            $io->note('Dry-run mode enabled — no data will be written.');
        }

        $cacheDir = dirname(APPLICATION_PATH) . '/cache/';
        $cache    = new FilesystemAdapter('zbmathApiDocument', self::ONE_MONTH, $cacheDir);

        $db       = Zend_Db_Table_Abstract::getDefaultAdapter();
        $allCodes = $db->fetchCol($db->select()->from(T_PAPER_CLASSIFICATION_MSC2020, ['code']));
        $papers   = $db->fetchAll(
            $db->select()
                ->from(T_PAPERS, ['DOI', 'DOCID'])
                ->where('DOI != ""')
                ->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED)
                ->order('DOCID ASC')
        );

        $logger->info('Starting MSC 2020 enrichment for ' . count($papers) . ' papers');
        $io->progressStart(count($papers));

        foreach ($papers as $row) {
            $doi   = $row['DOI'];
            $docId = (int) $row['DOCID'];
            try {
                $apiResponse = $this->queryZbMath($doi, $cache, $logger);
                $codes       = $this->extractMscCodes($apiResponse);

                if (!empty($codes)) {
                    $logger->info("MSC 2020 codes found for DOI {$doi}: " . implode(', ', $codes));
                } else {
                    $logger->info("No MSC 2020 codes found for DOI {$doi}");
                }

                $collection = $this->buildClassifications($codes, $docId, $allCodes, $logger);

                if (!$dryRun && !empty($collection)) {
                    $n = Episciences_Paper_ClassificationsManager::insert($collection);
                    $logger->info("{$n} MSC 2020 classifications inserted/updated for DOCID {$docId}");
                }
            } catch (\Throwable $e) {
                $logger->error("MSC enrichment error for DOI {$doi}: " . $e->getMessage());
            }
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success('MSC 2020 classification enrichment completed.');
        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function queryZbMath(string $doi, FilesystemAdapter $cache, Logger $logger): array
    {
        $key  = 'zbmath_api_' . md5($doi);
        $item = $cache->getItem($key);
        if ($item->isHit()) {
            $logger->info("zbMath data from cache for DOI {$doi}");
            return $item->get();
        }

        $url = 'https://api.zbmath.org/v1/document/_structured_search?page=0&results_per_page=1&'
             . urlencode('external id') . '=' . urlencode($doi);
        try {
            $body = (new Client(['headers' => ['User-Agent' => 'Episciences', 'Accept' => 'application/json']]))
                        ->get($url)->getBody()->getContents();
            sleep(1);
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if (($decoded['status']['status_code'] ?? null) === 404) {
                $logger->info("No results found for DOI {$doi}");
                $decoded = ['result' => []];
            }
        } catch (GuzzleException $e) {
            if ($e->getCode() === 404) {
                $logger->info("No results found for DOI {$doi} (404 response)");
                $decoded = ['result' => []];
            } else {
                $logger->error('zbMath API error for DOI ' . $doi . ': ' . $e->getMessage());
                return ['result' => []]; // not cached — will retry next run
            }
        }

        $item->set($decoded);
        $item->expiresAfter(self::ONE_MONTH);
        $cache->save($item);
        return $decoded;
    }

    /**
     * @param array<string, mixed> $apiResponse
     * @return array<string>
     */
    private function extractMscCodes(array $apiResponse): array
    {
        $codes = [];
        foreach ($apiResponse['result'] ?? [] as $result) {
            foreach ($result['msc'] ?? [] as $mscItem) {
                if (($mscItem['scheme'] ?? '') === 'msc2020' && isset($mscItem['code'])) {
                    $codes[] = $mscItem['code'];
                }
            }
        }
        return $codes;
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
            $classification->setClassificationName(Episciences\Classification\msc2020::$classificationName);
            try {
                $classification->checkClassificationCode($code, $allCodes);
            } catch (Zend_Exception $e) {
                $logger->warning("MSC 2020 code [{$code}] ignored: " . $e->getMessage());
                continue;
            }
            $classification->setClassificationCode($code);
            $classification->setDocid($docId);
            $classification->setSourceId((int) Episciences_Repositories::ZBMATH_OPEN);
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
