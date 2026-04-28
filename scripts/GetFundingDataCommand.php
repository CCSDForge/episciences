<?php
declare(strict_types=1);

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: enrich funding data from OpenAIRE Research Graph + HAL.
 *
 * Replaces: scripts/getFundingData.php (JournalScript)
 */
class GetFundingDataCommand extends Command
{
    protected static $defaultName = 'enrichment:funding';

    private const ONE_MONTH = 3600 * 24 * 31;

    protected function configure(): void
    {
        $this
            ->setDescription('Enrich funding data from OpenAIRE Research Graph and HAL')
            ->addOption('doi', null, InputOption::VALUE_OPTIONAL, 'Process a single paper by DOI')
            ->addOption('paperid', null, InputOption::VALUE_OPTIONAL, 'Process a single paper by paper ID')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without writing to the database')
            ->addOption('no-cache', null, InputOption::VALUE_NONE, 'Bypass cache and fetch fresh data')
            ->addOption('rvcode', null, InputOption::VALUE_REQUIRED, 'Restrict processing to one journal (RV code); ignored when --doi or --paperid is used');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $dryRun  = (bool) $input->getOption('dry-run');
        $noCache = (bool) $input->getOption('no-cache');
        $rvcode  = $input->getOption('rvcode');

        $io->title('Funding data enrichment');

        $this->bootstrap();

        $logger = new Logger('fundingEnrichment');
        $logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . 'fundingEnrichment_' . date('Y-m-d') . '.log', Logger::INFO));
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

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        if ($input->getOption('doi')) {
            $doi = (string) $input->getOption('doi');
            $select = $db->select()
                ->from(T_PAPERS, ['PAPERID', 'DOI', 'IDENTIFIER', 'VERSION', 'REPOID'])
                ->where('DOI = ?', trim($doi))
                ->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED);
            $rows = $db->fetchAll($select);
        } elseif ($input->getOption('paperid')) {
            $paperId = (int) $input->getOption('paperid');
            $select = $db->select()
                ->from(T_PAPERS, ['PAPERID', 'DOI', 'IDENTIFIER', 'VERSION', 'REPOID'])
                ->where('PAPERID = ?', $paperId)
                ->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED);
            $rows = $db->fetchAll($select);
        } else {
            $select = $db->select()
                ->from(T_PAPERS, ['PAPERID', 'DOI', 'IDENTIFIER', 'VERSION', 'REPOID'])
                ->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED)
                ->order('REPOID DESC');
            if ($rvid !== null) {
                $select->where('RVID = ?', $rvid);
            }
            $rows = $db->fetchAll($select);
        }

        $logger->info('Starting funding enrichment for ' . count($rows) . ' papers');
        $io->progressStart(count($rows));

        foreach ($rows as $value) {
            $paperId = (int) $value['PAPERID'];

            // Process OpenAIRE funding for papers with DOI (all rows are already STATUS_PUBLISHED)
            if (!empty($value['DOI'])) {
                $doiTrim = trim($value['DOI']);

                if ($noCache) {
                    $cacheDir = dirname(APPLICATION_PATH) . '/cache/';
                    $cacheOARG = new FilesystemAdapter('openAireResearchGraph', 0, $cacheDir);
                    $cacheOARG->deleteItem(md5($doiTrim) . '.json');
                    $cacheFund = new FilesystemAdapter('enrichmentFunding', 0, $cacheDir);
                    $cacheFund->deleteItem(md5($doiTrim) . '_funding.json');
                }

                Episciences_OpenAireResearchGraphTools::checkOpenAireGlobalInfoByDoi($doiTrim, $paperId);

                $cacheOARG = new FilesystemAdapter('openAireResearchGraph', self::ONE_MONTH, dirname(APPLICATION_PATH) . '/cache/');
                $setsGlobalOARG = $cacheOARG->getItem(md5($doiTrim) . '.json');

                [$cacheFunding, , $setOAFunding] = Episciences_OpenAireResearchGraphTools::getFundingCacheOA($doiTrim);

                if ($setsGlobalOARG->isHit() && !$setOAFunding->isHit()) {
                    try {
                        $decodeOpenAireResp = json_decode($setsGlobalOARG->get(), true, 512, JSON_THROW_ON_ERROR);
                        Episciences_OpenAireResearchGraphTools::putFundingsInCache($decodeOpenAireResp, $doiTrim);
                        $logger->info("Funding data cached for DOI {$doiTrim}");
                    } catch (JsonException $e) {
                        $logger->error("JSON error for paper {$paperId} (funding cache): " . $e->getMessage());
                        $setOAFunding->set(json_encode(['']));
                        $cacheFunding->save($setOAFunding);
                    }
                    sleep(1);
                }

                [$cacheFunding, , $setOAFunding] = Episciences_OpenAireResearchGraphTools::getFundingCacheOA($doiTrim);

                if (!$dryRun) {
                    try {
                        $fileFound = json_decode($setOAFunding->get(), true, 512, JSON_THROW_ON_ERROR);
                        if (!empty($fileFound[0])) {
                            $fundingArray       = [];
                            $globalfundingArray = [];
                            $globalfundingArray = Episciences_Paper_ProjectsManager::formatFundingOAForDB($fileFound, $fundingArray, $globalfundingArray);
                            $rowInDBGraph       = Episciences_Paper_ProjectsManager::getProjectsByPaperIdAndSourceId($paperId, (int) Episciences_Repositories::GRAPH_OPENAIRE_ID);
                            Episciences_Paper_ProjectsManager::insertOrUpdateFundingOA($globalfundingArray, $rowInDBGraph, $paperId);
                        }
                    } catch (JsonException $e) {
                        $logger->error("JSON decode error for paper {$paperId} (OpenAIRE funding): " . $e->getMessage());
                    } catch (\Throwable $e) {
                        $logger->error("OpenAIRE funding error for paper {$paperId}: " . get_class($e) . ': ' . $e->getMessage());
                    }
                }
            }

            // Process HAL funding
            if ((int) $value['REPOID'] === (int) Episciences_Repositories::HAL_REPO_ID && !empty(trim($value['IDENTIFIER']))) {
                $trimIdentifier = trim($value['IDENTIFIER']);
                $version        = (int) $value['VERSION']; // cast: function expects int, DB returns string

                if (!$dryRun) {
                    try {
                        $arrayIdEuAnr    = Episciences_Paper_ProjectsManager::CallHAlApiForIdEuAndAnrFunding($trimIdentifier, $version);
                        $decodeHalResp   = json_decode($arrayIdEuAnr, true, 512, JSON_THROW_ON_ERROR);
                        $globalArrayJson = [];

                        if (!empty($decodeHalResp['response']['docs'])) {
                            $globalArrayJson = Episciences_Paper_ProjectsManager::FormatFundingANREuToArray(
                                $decodeHalResp['response']['docs'],
                                $trimIdentifier,
                                $globalArrayJson
                            );
                        }

                        if (!empty($globalArrayJson)) {
                            $mergeArrayANREU = [];
                            foreach ($globalArrayJson as $globalPreJson) {
                                $mergeArrayANREU[] = $globalPreJson[0];
                            }
                            $rowInDbHal = Episciences_Paper_ProjectsManager::getProjectsByPaperIdAndSourceId($paperId, (int) Episciences_Repositories::HAL_REPO_ID);
                            Episciences_Paper_ProjectsManager::insertOrUpdateHalFunding($rowInDbHal, $mergeArrayANREU, $paperId);
                        }
                    } catch (JsonException $e) {
                        $logger->error("JSON decode error for paper {$paperId} (HAL funding): " . $e->getMessage());
                    } catch (\Throwable $e) {
                        $logger->error("HAL funding error for paper {$paperId}: " . $e->getMessage());
                    }
                }
            }

            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success('Funding data enrichment completed.');
        $logger->info('Funding enrichment completed');

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
