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
 * Symfony Console command: enrich author data (ORCID) from OpenAIRE Research Graph + HAL TEI.
 *
 * Replaces: scripts/getCreatorData.php (JournalScript)
 */
class GetCreatorDataCommand extends Command
{
    protected static $defaultName = 'enrichment:creators';

    protected function configure(): void
    {
        $this
            ->setDescription('Enrich author ORCID data from OpenAIRE Research Graph and HAL TEI')
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

        $io->title('Author data enrichment');

        $this->bootstrap();

        $logger = new Logger('creatorEnrichment');
        $logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . 'creatorEnrichment_' . date('Y-m-d') . '.log', Logger::INFO));
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
                ->from(T_PAPERS, ['DOI', 'PAPERID', 'DOCID', 'IDENTIFIER', 'REPOID', 'VERSION'])
                ->where('DOI = ?', trim($doi))
                ->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED);
            $rows = $db->fetchAll($select);
        } elseif ($input->getOption('paperid')) {
            $paperId = (int) $input->getOption('paperid');
            $select = $db->select()
                ->from(T_PAPERS, ['DOI', 'PAPERID', 'DOCID', 'IDENTIFIER', 'REPOID', 'VERSION'])
                ->where('PAPERID = ?', $paperId)
                ->where("DOI != ''")
                ->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED);
            $rows = $db->fetchAll($select);
        } else {
            $select = $db->select()
                ->distinct()
                ->from(T_PAPERS, ['DOI', 'PAPERID', 'DOCID', 'IDENTIFIER', 'REPOID', 'VERSION'])
                ->where("DOI != ''")
                ->where('STATUS = ?', Episciences_Paper::STATUS_PUBLISHED)
                ->order('DOCID DESC');
            if ($rvid !== null) {
                $select->where('RVID = ?', $rvid);
            }
            $rows = $db->fetchAll($select);
        }

        $logger->info('Starting author enrichment for ' . count($rows) . ' papers');
        $io->progressStart(count($rows));

        foreach ($rows as $value) {
            $paperId = (int) $value['PAPERID'];
            $docId   = (int) $value['DOCID'];
            $doiTrim = trim($value['DOI']);

            if ($noCache && $doiTrim !== '') {
                $cacheDir = dirname(APPLICATION_PATH) . '/cache/';
                $cacheOARG = new FilesystemAdapter('openAireResearchGraph', 0, $cacheDir);
                $cacheOARG->deleteItem(md5($doiTrim) . '.json');
                $cacheAuth = new FilesystemAdapter('enrichmentAuthors', 0, $cacheDir);
                $cacheAuth->deleteItem(md5($doiTrim) . '_creator.json');
            }

            $paper = Episciences_PapersManager::get($docId, false);
            Episciences_Paper_AuthorsManager::InsertAuthorsFromPapers($paper);

            if ($doiTrim !== '' && !$dryRun) {
                Episciences_OpenAireResearchGraphTools::checkOpenAireGlobalInfoByDoi($doiTrim, $paperId);
                $setsGlobalOARG = Episciences_OpenAireResearchGraphTools::getsGlobalOARGCache($doiTrim);
                [$cacheCreator, , $setsOpenAireCreator] = Episciences_OpenAireResearchGraphTools::getCreatorCacheOA($doiTrim);

                if ($setsGlobalOARG->isHit() && !$setsOpenAireCreator->isHit()) {
                    try {
                        $decodeOpenAireResp = json_decode($setsGlobalOARG->get(), true, 512, JSON_THROW_ON_ERROR);
                        Episciences_OpenAireResearchGraphTools::putCreatorInCache($decodeOpenAireResp, $doiTrim);
                    } catch (JsonException $e) {
                        $logger->error("JSON error for paper {$paperId} (OpenAIRE creator cache): " . $e->getMessage());
                        $setsOpenAireCreator->set(json_encode(['']));
                        $cacheCreator->save($setsOpenAireCreator);
                    }
                    sleep(1);
                }

                [$cacheCreator, , $setsOpenAireCreator] = Episciences_OpenAireResearchGraphTools::getCreatorCacheOA($doiTrim);
                Episciences_OpenAireResearchGraphTools::insertOrcidAuthorFromOARG($setsOpenAireCreator, $paperId);
            }

            if ((int) $value['REPOID'] === (int) Episciences_Repositories::HAL_REPO_ID && !$dryRun) {
                $identifier = trim($value['IDENTIFIER']);
                $version    = (int) $value['VERSION'];

                $selectAuthor = Episciences_Paper_AuthorsManager::getAuthorByPaperId($paperId);
                $decodeAuthor = [];
                foreach ($selectAuthor as $authorsDb) {
                    $decodeAuthor = json_decode($authorsDb['authors'], true, 512, JSON_THROW_ON_ERROR);
                }

                Episciences_Paper_AuthorsManager::getHalTei($identifier, $version);
                $cacheTeiHal = Episciences_Paper_AuthorsManager::getHalTeiCache($identifier, $version);

                if ($cacheTeiHal !== '') {
                    $xmlString = simplexml_load_string($cacheTeiHal);
                    if (is_object($xmlString) && $xmlString->count() > 0) {
                        $authorTei = Episciences_Paper_AuthorsManager::getAuthorsFromHalTei($xmlString);
                        if (!empty($authorTei)) {
                            $affiInfo = Episciences_Paper_AuthorsManager::getAffiFromHalTei($xmlString);
                            $authorTei = Episciences_Paper_AuthorsManager::mergeAuthorInfoAndAffiTei($authorTei, $affiInfo);
                            $formattedAuthorsForDb = Episciences_Paper_AuthorsManager::mergeInfoDbAndInfoTei($decodeAuthor, $authorTei);
                            $newAuthorInfos = new Episciences_Paper_Authors();
                            $newAuthorInfos->setAuthors(json_encode($formattedAuthorsForDb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT));
                            $newAuthorInfos->setPaperId($paperId);
                            $newAuthorInfos->setAuthorsId(array_key_first($selectAuthor));
                            Episciences_Paper_AuthorsManager::update($newAuthorInfos);
                        }
                    }
                }
            }

            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success('Author data enrichment completed.');
        $logger->info('Author enrichment completed');

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
