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
 * Symfony Console command: download PDF + zbJATS XML files per volume and package them into a ZIP archive.
 *
 * For each published paper in each volume of the given journal, the command:
 *   - fetches the PDF, cached 12 months (Symfony Cache, keyed by docId)
 *   - fetches the zbJATS XML (if available), cached 1 month
 *   - packages the cached files as volume{n}/article{m}.{pdf,xml} into a ZIP archive
 *     at data/{rvcode}/zbjats/{prefix}{rvcode}.zip — individual PDF/XML files are
 *     never written to data/, only the final ZIP is
 *   - the ZIP is only rebuilt when at least one file wasn't already cached;
 *     use --remove-cache to force re-fetching everything
 *
 * Replaces: scripts/zbjatsZipper.php (JournalScript)
 */
class ZbjatsZipperCommand extends Command
{
    protected static $defaultName = 'zbjats:zip';

    private const PDF_CACHE_TTL_SECONDS = 3600 * 24 * 365;
    private const XML_CACHE_TTL_SECONDS = 3600 * 24 * 30;

    private Logger $logger;
    private Client $httpClient;
    private Episciences_Review $review;
    private FilesystemAdapter $pdfCache;
    private FilesystemAdapter $xmlCache;
    private bool $hasNewFiles = false;
    private bool $isNewFront = false;

    protected function configure(): void
    {
        $this
            ->setDescription('Download PDF + zbJATS XML per volume and package them into a ZIP archive.')
            ->addOption('rvid', null, InputOption::VALUE_REQUIRED, 'RVID (integer) of the journal to process')
            ->addOption('zip-prefix', null, InputOption::VALUE_OPTIONAL, 'Optional prefix for the ZIP filename (e.g. "2024_")')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate without downloading files or writing the ZIP')
            ->addOption('remove-cache', null, InputOption::VALUE_NONE, 'Clear the PDF/XML cache for this journal before processing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $rvid   = $input->getOption('rvid');

        if ($rvid === null || $rvid === '') {
            $io->error('Missing required option: --rvid');
            return Command::FAILURE;
        }

        $io->title('zbJAT Zipper');
        $this->bootstrap();

        $this->logger = new Logger('zbjatsZipper');
        $this->logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . 'zbjatsZipper_' . date('Y-m-d') . '.log', Logger::DEBUG
        ));
        if (!$io->isQuiet()) {
            $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }

        if ($dryRun) {
            $io->note('Dry-run mode enabled — no files will be downloaded or written.');
        }

        $this->httpClient = new Client();

        // findByRvid() returns Episciences_Review|bool; check before assigning to typed property
        $review = Episciences_ReviewsManager::findByRvid((int) $rvid);

        if (!$review instanceof Episciences_Review) {
            $this->logger->error('Journal not found', ['rvid' => $rvid]);
            $io->error("No journal found for RVID {$rvid}.");
            return Command::FAILURE;
        }

        if (!defined('RVID')) {
            define('RVID', $review->getRvid());
        }

        $this->review     = $review;
        $this->isNewFront = Episciences_ReviewsManager::isNewFrontSwitched($review->getRvid());
        $this->review->loadSettings();

        $this->pdfCache = new FilesystemAdapter("zbjats-pdf-{$review->getCode()}", self::PDF_CACHE_TTL_SECONDS, CACHE_PATH_METADATA);
        $this->xmlCache = new FilesystemAdapter("zbjats-xml-{$review->getCode()}", self::XML_CACHE_TTL_SECONDS, CACHE_PATH_METADATA);

        if ($input->getOption('remove-cache')) {
            $this->pdfCache->clear();
            $this->xmlCache->clear();
            $this->logger->info('Cache cleared for journal', ['rvcode' => $review->getCode()]);
        }

        $volumesData = $this->processJournal($dryRun);

        $this->createZipArchive($volumesData, (string) ($input->getOption('zip-prefix') ?? ''), $dryRun);

        $this->logger->info('Done');
        $io->success('zbJAT ZIP completed.');
        return Command::SUCCESS;
    }

    /**
     * Iterate over all volumes with papers and ensure their PDF/XML are cached.
     *
     * @return array<int, array{dir: string, papers: array<int, array{iArticle: int, docId: int, hasXml: bool}>}>
     */
    private function processJournal(bool $dryRun): array
    {
        $volumes     = $this->review->getVolumesWithPapers([]);
        $volumesData = [];
        $ivol        = 1;

        foreach ($volumes as $volume) {
            $this->logger->info('Processing volume', ['vid' => $volume->getVid()]);
            $volumesData[] = [
                'dir'    => 'volume' . $ivol,
                'papers' => $this->processVolume($volume, $dryRun),
            ];
            $ivol++;
        }

        return $volumesData;
    }

    /**
     * @return array<int, array{iArticle: int, docId: int, hasXml: bool}>
     */
    private function processVolume(Episciences_Volume $volume, bool $dryRun): array
    {
        /** @var Episciences_Paper[] $paperList */
        $paperList = $volume->getSortedPapersFromVolume('object');
        $papers    = [];
        $iArticle  = 1;

        foreach ($paperList as $paper) {
            if (!$paper->isPublished()) {
                continue;
            }

            $hasXml = $this->cachePaperFiles($paper, $dryRun);

            if ($hasXml === null) {
                continue;
            }

            $papers[] = [
                'iArticle' => $iArticle,
                'docId'    => $paper->getDocid(),
                'hasXml'   => $hasXml,
            ];
            $iArticle++;
        }

        return $papers;
    }

    /**
     * Ensure the paper's PDF (and zbJATS XML, if available) are present in cache.
     *
     * @return bool|null null if the PDF could not be fetched (paper skipped), otherwise whether an XML is cached
     */
    private function cachePaperFiles(Episciences_Paper $paper, bool $dryRun): ?bool
    {
        $docId   = $paper->getDocid();
        $paperId = $paper->getPaperid();
        $pdfUrl  = $this->buildPaperUrl($this->review->getCode(), $docId, $paperId, 'pdf', $this->isNewFront);
        $xmlUrl  = $this->buildPaperUrl($this->review->getCode(), $docId, $paperId, 'zbjats', $this->isNewFront);

        if ($dryRun) {
            $this->logger->info('[dry-run] Would cache paper files', ['docId' => $docId, 'pdf' => $pdfUrl, 'xml' => $xmlUrl]);
            return true;
        }

        $pdfItem = $this->pdfCache->getItem('pdf_' . $docId);

        if (!$pdfItem->isHit()) {
            try {
                $pdfResponse = $this->httpClient->request('GET', $pdfUrl);
            } catch (GuzzleException $e) {
                $this->logger->error('Failed to download PDF', ['docId' => $docId, 'url' => $pdfUrl, 'error' => $e->getMessage()]);
                return null;
            }

            if ($pdfResponse->getStatusCode() !== 200) {
                $this->logger->warning('Unexpected status for PDF', ['docId' => $docId, 'status' => $pdfResponse->getStatusCode()]);
                return null;
            }

            $pdfItem->expiresAfter(self::PDF_CACHE_TTL_SECONDS);
            $pdfItem->set($pdfResponse->getBody()->getContents());
            $this->pdfCache->save($pdfItem);
            $this->hasNewFiles = true;
            $this->logger->info('Cached PDF', ['docId' => $docId, 'url' => $pdfUrl]);
        }

        $xmlItem = $this->xmlCache->getItem('xml_' . $docId);

        if ($xmlItem->isHit()) {
            return true;
        }

        try {
            $xmlResponse = $this->httpClient->request('GET', $xmlUrl);
        } catch (GuzzleException $e) {
            $this->logger->warning('Failed to download zbJATS XML', ['docId' => $docId, 'url' => $xmlUrl, 'error' => $e->getMessage()]);
            return false;
        }

        if ($xmlResponse->getStatusCode() !== 200) {
            return false;
        }

        $xmlItem->expiresAfter(self::XML_CACHE_TTL_SECONDS);
        $xmlItem->set($xmlResponse->getBody()->getContents());
        $this->xmlCache->save($xmlItem);
        $this->hasNewFiles = true;
        $this->logger->info('Cached zbJATS XML', ['docId' => $docId, 'url' => $xmlUrl]);

        return true;
    }

    /**
     * Build the URL for a paper resource. Journals migrated to the new front-end
     * (REVIEW.is_new_front_switched = 'yes') serve files under /articles/{paperId}/...
     * instead of the legacy /{docId}/... paths, and rename 'pdf' to 'download'.
     *
     * @param string $rvCode     Journal RV code
     * @param int    $docId      Document identifier (legacy front)
     * @param int    $paperId    Paper identifier (new front)
     * @param string $format     Resource format: 'pdf' or 'zbjats'
     * @param bool   $isNewFront Whether the journal has switched to the new front-end
     */
    public static function buildPaperUrl(string $rvCode, int $docId, int $paperId, string $format, bool $isNewFront): string
    {
        if ($isNewFront) {
            $newFrontFormat = $format === 'pdf' ? 'download' : $format;
            return sprintf('https://%s.%s/articles/%d/%s', $rvCode, DOMAIN, $paperId, $newFrontFormat);
        }

        return sprintf('https://%s.%s/%d/%s', $rvCode, DOMAIN, $docId, $format);
    }

    /**
     * Build the ZIP output path.
     *
     * @param string $basePath   Directory that will contain the ZIP archive
     * @param string $reviewCode Journal RV code
     * @param string $zipPrefix  Optional filename prefix (e.g. "2024_")
     */
    public static function buildZipPath(string $basePath, string $reviewCode, string $zipPrefix = ''): string
    {
        return sprintf('%s%s%s.zip', $basePath, $zipPrefix, $reviewCode);
    }

    /**
     * @param array<int, array{dir: string, papers: array<int, array{iArticle: int, docId: int, hasXml: bool}>}> $volumesData
     * @throws \RuntimeException if the output directory or the ZIP archive cannot be created
     */
    private function createZipArchive(array $volumesData, string $zipPrefix, bool $dryRun): void
    {
        $pathdir    = $this->getZbjatsPath();
        $zipcreated = self::buildZipPath($pathdir, $this->review->getCode(), $zipPrefix);

        if ($dryRun) {
            $this->logger->info('[dry-run] Would create ZIP archive', ['path' => $zipcreated]);
            return;
        }

        $totalPapers = 0;
        foreach ($volumesData as $volumeData) {
            $totalPapers += count($volumeData['papers']);
        }

        if ($totalPapers === 0) {
            $this->logger->info('No published paper found, skipping ZIP archive', ['rvcode' => $this->review->getCode()]);
            return;
        }

        if (!$this->hasNewFiles && is_file($zipcreated)) {
            $this->logger->info('No new files cached, ZIP archive already up to date, skipping', ['path' => $zipcreated]);
            return;
        }

        if (!is_dir($pathdir) && !mkdir($pathdir, 0776, true) && !is_dir($pathdir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $pathdir));
        }

        $zip = new \ZipArchive();

        if ($zip->open($zipcreated, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException(sprintf('Failed to create ZIP archive: %s', $zipcreated));
        }

        $this->logger->info('Creating ZIP archive', ['path' => $zipcreated]);

        foreach ($volumesData as $volumeData) {
            foreach ($volumeData['papers'] as $paper) {
                $this->addCachedPaperToZip($zip, $volumeData['dir'], $paper);
            }
        }

        $closeError = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$closeError): bool {
            $closeError = $errstr;
            return true;
        });
        $closed = $zip->close();
        restore_error_handler();

        if (!$closed) {
            throw new \RuntimeException(sprintf(
                'Failed to write ZIP archive: %s%s',
                $zipcreated,
                $closeError !== null ? sprintf(' (%s)', $closeError) : ''
            ));
        }
        $this->logger->info('ZIP archive created', ['path' => $zipcreated]);
    }

    /**
     * @param array{iArticle: int, docId: int, hasXml: bool} $paper
     */
    private function addCachedPaperToZip(\ZipArchive $zip, string $volumeDir, array $paper): void
    {
        $pdfItem = $this->pdfCache->getItem('pdf_' . $paper['docId']);

        if ($pdfItem->isHit()) {
            $zip->addFromString(
                sprintf('%s/article%d.pdf', $volumeDir, $paper['iArticle']),
                $pdfItem->get()
            );
        }

        if (!$paper['hasXml']) {
            return;
        }

        $xmlItem = $this->xmlCache->getItem('xml_' . $paper['docId']);

        if ($xmlItem->isHit()) {
            $zip->addFromString(
                sprintf('%s/article%d.xml', $volumeDir, $paper['iArticle']),
                $xmlItem->get()
            );
        }
    }

    private function getZbjatsPath(): string
    {
        return sprintf('%s/data/%s/zbjats/', dirname(APPLICATION_PATH), $this->review->getCode());
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
