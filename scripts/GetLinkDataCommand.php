<?php
declare(strict_types=1);

use Episciences\Api\ScholexplorerApiClient;
use Episciences\Console\ProgressAwareStreamHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: enrich dataset link data from Scholexplorer API v3
 * (Scholix 3.0 schema).
 *
 * Replaces: scripts/getLinkData.php (JournalScript), then the Scholexplorer API v1 client.
 */
class GetLinkDataCommand extends Command
{
    protected static $defaultName = 'enrichment:links';

    private const VALID_TYPES = ['dataset', 'software', 'all'];

    protected function configure(): void
    {
        $this
            ->setDescription('Enrich dataset link data from Scholexplorer (OpenAIRE v3)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without writing to the database')
            ->addOption('rvcode', null, InputOption::VALUE_REQUIRED, 'Restrict processing to one journal (RV code)')
            ->addOption('doi', null, InputOption::VALUE_REQUIRED, 'Target a single DOI')
            ->addOption('docid', null, InputOption::VALUE_REQUIRED, 'Target a single paper doc_id')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Target typology (dataset|software|all)', 'dataset')
            ->addOption('no-cache', null, InputOption::VALUE_NONE, 'Bypass cache and force network request')
            ->addOption('no-bidirectional', null, InputOption::VALUE_NONE, 'Disable reciprocal targetPid lookup');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $rvcode = $input->getOption('rvcode');
        $doiOption   = $input->getOption('doi');
        $docIdOption = $input->getOption('docid');
        $type        = (string) $input->getOption('type');
        $noCache       = (bool) $input->getOption('no-cache');
        $bidirectional = !$input->getOption('no-bidirectional');

        if (!in_array($type, self::VALID_TYPES, true)) {
            $io->error(sprintf("Invalid --type value '%s'. Expected one of: %s.", $type, implode(', ', self::VALID_TYPES)));
            return Command::FAILURE;
        }

        $io->title('Link data enrichment (Scholexplorer API v3)');

        $this->bootstrap();

        $logger = new Logger('linkEnrichment');
        $logger->pushHandler(new StreamHandler(EPISCIENCES_LOG_PATH . 'linkEnrichment_' . date('Y-m-d') . '.log', Logger::INFO));
        $stdoutHandler = null;
        if (!$io->isQuiet()) {
            $stdoutHandler = new ProgressAwareStreamHandler('php://stdout', Logger::INFO);
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

        $client = ScholexplorerApiClient::create(null, $logger);
        $logger->info($client->isAuthenticated()
            ? 'Scholexplorer client authenticated (7200 req/h)'
            : 'Scholexplorer client anonymous (60 req/h)');

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
        if ($doiOption !== null) {
            $select->where('DOI = ?', (string) $doiOption);
        }
        if ($docIdOption !== null) {
            $select->where('DOCID = ?', (int) $docIdOption);
        }

        $rows = $db->fetchAll($select);
        $progressBar = $io->createProgressBar(count($rows));
        $stdoutHandler?->setProgressBar($progressBar);
        $progressBar->start();

        $requestedTypes = $type === 'all' ? ['dataset', 'software'] : [$type];

        foreach ($rows as $value) {
            $docId   = (int) $value['DOCID'];
            $doiTrim = trim($value['DOI']);

            $links = [];
            foreach ($requestedTypes as $requestedType) {
                $links = array_merge(
                    $links,
                    $client->fetchLinksForDoi($doiTrim, $docId, $requestedType, $bidirectional, $noCache)
                );
            }

            if (empty($links)) {
                $progressBar->advance();
                continue;
            }

            if (!$dryRun) {
                $this->purgeExistingLinks($db, $docId);

                foreach ($links as $linkItem) {
                    $this->insertLink($client, $docId, $linkItem, $logger, $doiTrim);
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $stdoutHandler?->setProgressBar(null);
        $io->newLine();
        $io->success('Link data enrichment completed.');

        return Command::SUCCESS;
    }

    /**
     * Remove every existing Scholexplorer-sourced dataset link (and its metadata row)
     * for this paper, ahead of a fresh insertion of the deduplicated link set.
     *
     * Fetches all matching id_paper_datasets_meta values (fetchAll, not fetchOne) so
     * a paper with several pre-existing links does not leave orphaned metadata rows.
     */
    private function purgeExistingLinks(Zend_Db_Adapter_Abstract $db, int $docId): void
    {
        $existingMetaIds = $db->fetchCol(
            $db->select()
                ->distinct()
                ->from(T_PAPER_DATASETS, ['id_paper_datasets_meta'])
                ->where('doc_id = ?', $docId)
                ->where('source_id = ?', Episciences_Repositories::SCHOLEXPLORER_ID)
                ->where('id_paper_datasets_meta IS NOT NULL')
        );

        $db->delete(T_PAPER_DATASETS, [
            'doc_id = ?'    => $docId,
            'source_id = ?' => Episciences_Repositories::SCHOLEXPLORER_ID,
        ]);

        if (!empty($existingMetaIds)) {
            $db->delete(T_PAPER_DATASETS_META, ['id IN (?)' => $existingMetaIds]);
        }
    }

    /**
     * @param array<string, mixed> $linkItem
     */
    private function insertLink(ScholexplorerApiClient $client, int $docId, array $linkItem, Logger $logger, string $doiTrim): void
    {
        $canonicalId = (string) $linkItem['identifier'];
        $scheme      = (string) $linkItem['scheme'];
        $url         = $linkItem['link'];
        $relName     = (string) $linkItem['relationship'];
        $code        = (string) $linkItem['type'];
        $rawEntity   = is_array($linkItem['raw']) ? $linkItem['raw'] : [];

        $csl = null;
        if ($scheme === 'doi') {
            $fetched = Episciences_DoiTools::getMetadataFromDoi($canonicalId);
            $csl = $fetched !== '' ? $fetched : null;
        }
        if ($csl === null) {
            $csl = json_encode(
                $client->buildFallbackCsl($rawEntity, $relName),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        }

        $metaId = Episciences_Paper_DatasetsMetadataManager::insert(['metatext' => $csl]);

        $enrichment = Episciences_Paper_DatasetsManager::insert([[
            'docId'               => $docId,
            'code'                => $code,
            'name'                => $scheme,
            'value'               => $canonicalId,
            'link'                => is_string($url) && $url !== '' ? $url : $scheme,
            'sourceId'            => Episciences_Repositories::SCHOLEXPLORER_ID,
            'relationship'        => $relName,
            'idPaperDatasetsMeta' => $metaId,
        ]]);

        if ($enrichment >= 1) {
            $logger->info("Dataset link saved for DOI {$doiTrim}: {$canonicalId}");
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
