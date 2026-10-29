<?php
declare(strict_types=1);

use Episciences\Console\ProgressAwareStreamHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Episciences\Solr\Indexing\Enqueue\SolrIndexing;

/**
 * Symfony Console command: enrich author data (ORCID) from OpenAIRE Research Graph + HAL TEI.
 *
 * Replaces: scripts/getCreatorData.php (JournalScript)
 */
#[AsCommand(name: 'enrichment:creators', description: 'Enrich author ORCID data from OpenAIRE Research Graph and HAL TEI')]
class GetCreatorDataCommand extends Command
{
    protected function configure(): void
    {
        $this
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
        $logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . 'creatorEnrichment_' . date('Y-m-d') . '.log', Level::Info));
        $stdoutHandler = null;
        if (!$io->isQuiet() && !$io->isSilent()) {
            $stdoutHandler = new ProgressAwareStreamHandler('php://stdout', Level::Info);
            $logger->pushHandler($stdoutHandler);
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
        $progressBar = $io->createProgressBar(count($rows));
        $stdoutHandler?->setProgressBar($progressBar);
        $progressBar->start();

        $oaClient = \Episciences\Api\OpenAireApiClient::create();
        $logger->info($oaClient->isAuthenticated()
            ? 'OpenAIRE: authenticated mode (client credentials configured)'
            : 'OpenAIRE: anonymous fallback mode (no client credentials configured) — throttling at 60s/request'
        );

        // Author corrections from HAL TEI, listed at the end in verbose mode: [paper ID, message]
        $corrections = [];

        foreach ($rows as $value) {
            $paperId = (int) $value['PAPERID'];
            $docId   = (int) $value['DOCID'];
            $doiTrim = trim($value['DOI']);
            Episciences_Paper_Authors_EnrichmentService::resetCorrections();

            if ($noCache && $doiTrim !== '') {
                $cacheDir = dirname(APPLICATION_PATH) . '/cache/';
                $cacheOARG = new FilesystemAdapter('openAireResearchGraph', 0, $cacheDir);
                $cacheOARG->deleteItem(md5($doiTrim) . '.json');
                $cacheAuth = new FilesystemAdapter('enrichmentAuthors', 0, $cacheDir);
                $cacheAuth->deleteItem(md5($doiTrim) . '_creator.json');
            }

            $paper = Episciences_PapersManager::get($docId, false);
            if (!$dryRun) {
                Episciences_Paper_AuthorsManager::InsertAuthorsFromPapers($paper);
            }

            if ($doiTrim !== '' && !$dryRun) {
                $response = $oaClient->fetchPublication($doiTrim, $paperId);

                if ($response !== null) {
                    [, , $creatorItem] = $oaClient->getCreatorCacheItem($doiTrim);
                    if (!$creatorItem->isHit()) {
                        $oaClient->putCreatorInCache($response, $doiTrim);
                        [, , $creatorItem] = $oaClient->getCreatorCacheItem($doiTrim);
                    }
                    $oaClient->insertOrcidAuthorFromCache($creatorItem, $paperId);
                }
            }

            if ((int) $value['REPOID'] === (int) Episciences_Repositories::HAL_REPO_ID) {
                $this->enrichFromHalTei($paper, trim($value['IDENTIFIER']), (int) $value['VERSION'], $dryRun);
            }

            foreach (Episciences_Paper_Authors_EnrichmentService::getCorrections() as $correction) {
                $corrections[] = [$paperId, $correction];
            }

            if (!$dryRun) {
                try {
                    SolrIndexing::enqueueIndex($docId);
                } catch (Exception $e) {
                    $logger->error("Solr enqueue error for paper {$docId}: " . $e->getMessage());
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $stdoutHandler?->setProgressBar(null);
        $io->newLine();

        if ($io->isVerbose()) {
            $sectionTitle = $dryRun ? 'Corrections that would be applied from HAL TEI' : 'Corrections applied from HAL TEI';
            $io->section($sectionTitle . ' (' . count($corrections) . ')');
            if ($corrections === []) {
                $io->text('No correction.');
            } else {
                $io->table(['Paper ID', 'Correction'], $corrections);
            }
        }

        $io->success('Author data enrichment completed.');
        $logger->info('Author enrichment completed');

        return Command::SUCCESS;
    }

    /**
     * Merge HAL TEI data (names, ORCID, affiliations) into the paper authors.
     * In dry-run mode the corrections are computed (and listed with -v) but not written;
     * a paper without author record is then evaluated on the records an insertion would create.
     *
     * @throws JsonException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function enrichFromHalTei(Episciences_Paper $paper, string $identifier, int $version, bool $dryRun): void
    {
        $paperId = (int) $paper->getPaperid();
        $selectAuthor = Episciences_Paper_Authors_Repository::getAuthorByPaperId($paperId);
        $dbAuthors = Episciences_Paper_Authors_Repository::getDecodedAuthors($paperId);

        if ($dbAuthors === [] && $dryRun) {
            $dbAuthors = Episciences_Paper_Authors_Repository::buildFromPaper($paper);
        }

        $teiAuthors = Episciences_Paper_Authors_EnrichmentService::getHalTeiAuthors($identifier, $version);

        if ($teiAuthors === []) {
            return;
        }

        $enrichedAuthors = Episciences_Paper_Authors_EnrichmentService::mergeInfoDbAndInfoTei($dbAuthors, $teiAuthors);

        if ($dryRun || $selectAuthor === []) {
            return;
        }

        $newAuthorInfos = new Episciences_Paper_Authors();
        $newAuthorInfos->setAuthors(json_encode($enrichedAuthors, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT));
        $newAuthorInfos->setPaperId($paperId);
        $newAuthorInfos->setAuthorsId(array_key_first($selectAuthor));
        Episciences_Paper_AuthorsManager::update($newAuthorInfos);
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
