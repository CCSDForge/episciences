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
 * Symfony Console command: enrich dataset link data from Scholexplorer.
 *
 * Replaces: scripts/getLinkData.php (JournalScript)
 *
 * Uses Symfony Cache instead of custom file-based cache.
 */
class GetLinkDataCommand extends Command
{
    protected static $defaultName = 'enrichment:links';

    private const API_URL = 'https://api.scholexplorer.openaire.eu';
    private const ONE_MONTH = 3600 * 24 * 31;

    protected function configure(): void
    {
        $this
            ->setDescription('Enrich dataset link data from Scholexplorer (OpenAIRE)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without writing to the database')
            ->addOption('rvcode', null, InputOption::VALUE_REQUIRED, 'Restrict processing to one journal (RV code)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $rvcode = $input->getOption('rvcode');

        $io->title('Link data enrichment (Scholexplorer)');

        $this->bootstrap();

        $logger = new Logger('linkEnrichment');
        $logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . 'linkEnrichment_' . date('Y-m-d') . '.log', Logger::INFO));
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

        $client = new Client();
        $cache  = new FilesystemAdapter('scholexplorerLinkData', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');

        $db     = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->distinct()
            ->from(T_PAPERS, ['DOI', 'DOCID'])
            ->where('DOI IS NOT NULL')
            ->where('DOI != ""')
            ->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED);
        if ($rvid !== null) {
            $select->where('RVID = ?', $rvid);
        }

        $rows = $db->fetchAll($select);
        $io->progressStart(count($rows));

        foreach ($rows as $value) {
            $docId   = (int) $value['DOCID'];
            $doiTrim = trim($value['DOI']);

            // Use DOI suffix as cache key (same as old file-name scheme)
            $doiParts = explode('/', $doiTrim, 2);
            $cacheKey = isset($doiParts[1]) ? md5($doiParts[1]) : md5($doiTrim);
            $cacheKey .= '_link';

            $cacheItem = $cache->getItem($cacheKey);

            if ($cacheItem->isHit()) {
                $apiResult = (string) $cacheItem->get();
                $logger->info('Dataset links from cache for DOI ' . $doiTrim);
            } else {
                $apiUrl = self::API_URL . '/v1/linksFromPid?pid=' . $doiTrim;
                $logger->info('Fetching dataset links from Scholexplorer for DOI ' . $doiTrim);

                try {
                    $apiResult = $client->get($apiUrl, [
                        'headers' => [
                            'User-Agent'   => 'CCSD Episciences support@episciences.org',
                            'Content-Type' => 'application/json',
                            'Accept'       => 'application/json',
                        ],
                    ])->getBody()->getContents();
                } catch (GuzzleException $e) {
                    $logger->error('Scholexplorer API error for DOI ' . $doiTrim . ': ' . $e->getMessage());
                    $io->progressAdvance();
                    continue;
                }

                $decoded = json_decode($apiResult, true, 512, JSON_THROW_ON_ERROR);
                if (!empty($decoded)) {
                    $cacheItem->set($apiResult);
                    $cacheItem->expiresAfter(self::ONE_MONTH);
                    $cache->save($cacheItem);
                }
            }

            $decoded = json_decode($apiResult, true, 512, JSON_THROW_ON_ERROR);
            if (empty($decoded)) {
                $io->progressAdvance();
                sleep(1);
                continue;
            }

            $filtered = array_filter(array_map(static function (array $valuesResult): array|false {
                if (!isset($valuesResult['target']['objectType'])) {
                    return false;
                }
                $objectType = $valuesResult['target']['objectType'];
                if ($objectType !== 'dataset' && $objectType !== 'datasets') {
                    return false;
                }
                return $valuesResult;
            }, $decoded));

            if (!empty($filtered) && !$dryRun) {
                // Remove existing Scholexplorer data for this paper
                $getTargetId = $db->select()
                    ->distinct()
                    ->from('paper_datasets', ['id_paper_datasets_meta'])
                    ->where('doc_id IS NOT NULL')
                    ->where('source_id = ?', Episciences_Repositories::SCHOLEXPLORER_ID)
                    ->where('doc_id = ?', $docId);
                $idToDelete = $db->fetchOne($getTargetId);
                if (is_string($idToDelete)) {
                    Episciences_Paper_DatasetsMetadataManager::deleteMetaDataAndDatasetsByIdMd((int) $idToDelete);
                    $logger->info('Old dataset links removed for DOI ' . $doiTrim);
                }

                foreach ($filtered as $ar) {
                    foreach ($ar['target']['identifiers'] as $identifier) {
                        try {
                            $csl                 = Episciences_DoiTools::getMetadataFromDoi($identifier['identifier']);
                            $lastMetatextInserted = Episciences_Paper_DatasetsMetadataManager::insert(['metatext' => $csl]);
                            $enrichment          = Episciences_Paper_DatasetsManager::insert([[
                                'docId'                => $docId,
                                'code'                 => 'dataset',
                                'name'                 => $identifier['schema'],
                                'value'                => $identifier['identifier'],
                                'link'                 => $identifier['schema'],
                                'sourceId'             => Episciences_Repositories::SCHOLEXPLORER_ID,
                                'relationship'         => $ar['relationship']['name'],
                                'idPaperDatasetsMeta'  => $lastMetatextInserted,
                            ]]);
                            if ($enrichment >= 1) {
                                $logger->info('Dataset link saved for DOI ' . $doiTrim);
                            }
                        } catch (Exception $e) {
                            $logger->error('Dataset link insert error for DOI ' . $doiTrim . ': ' . $e->getMessage());
                        }
                    }
                }
            }

            $io->progressAdvance();
            sleep(1);
        }

        $io->progressFinish();
        $io->success('Link data enrichment completed.');

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
