<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: download PDF + zbJATS XML files per volume and package them into a ZIP archive.
 *
 * For each published paper in each volume of the given journal, the command:
 *   - downloads the PDF  as  article{n}.pdf
 *   - downloads the zbJATS XML (if available) as article{n}.xml
 *   - groups files under volume{n}/ directories inside data/{rvcode}/zbjats/
 *   - creates a final ZIP archive at data/{rvcode}/zbjats/{prefix}{rvcode}.zip
 *
 * Replaces: scripts/zbjatsZipper.php (JournalScript)
 */
class ZbjatsZipperCommand extends Command
{
    protected static $defaultName = 'zbjats:zip';

    private Logger $logger;
    private Client $httpClient;
    private Episciences_Review $review;

    protected function configure(): void
    {
        $this
            ->setDescription('Download PDF + zbJATS XML per volume and package them into a ZIP archive.')
            ->addOption('rvid', null, InputOption::VALUE_REQUIRED, 'RVID (integer) of the journal to process')
            ->addOption('zip-prefix', null, InputOption::VALUE_OPTIONAL, 'Optional prefix for the ZIP filename (e.g. "2024_")')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate without downloading files or writing the ZIP');
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

        $this->review = $review;
        $this->review->loadSettings();

        $tabvolRepoName = $this->processJournal($dryRun);

        $this->createZipArchive($tabvolRepoName, (string) ($input->getOption('zip-prefix') ?? ''), $dryRun);

        $this->logger->info('Done');
        $io->success('zbJAT ZIP completed.');
        return Command::SUCCESS;
    }

    /**
     * Iterate over all volumes with papers and download files into per-volume directories.
     *
     * @return string[] Volume directory names (e.g. ['volume1', 'volume2'])
     */
    private function processJournal(bool $dryRun): array
    {
        $volumes        = $this->review->getVolumesWithPapers([]);
        $tabvolRepoName = [];
        $ivol           = 1;

        foreach ($volumes as $volume) {
            $this->logger->info('Processing volume', ['vid' => $volume->getVid()]);
            $this->processVolume($volume, $ivol, $dryRun);
            $tabvolRepoName[] = 'volume' . $ivol;
            $ivol++;
        }

        return $tabvolRepoName;
    }

    private function processVolume(Episciences_Volume $volume, int $ivol, bool $dryRun): void
    {
        $dirnameVol = sprintf('%svolume%d/', $this->getZbjatsPath(), $ivol);

        if (!$dryRun && !is_dir($dirnameVol) && !mkdir($dirnameVol, 0776, true) && !is_dir($dirnameVol)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $dirnameVol));
        }

        /** @var Episciences_Paper[] $paperList */
        $paperList = $volume->getSortedPapersFromVolume('object');
        $iArticle  = 1;

        foreach ($paperList as $paper) {
            if (!$paper->isPublished()) {
                continue;
            }

            if ($this->downloadPaperFiles($paper, $dirnameVol, $iArticle, $dryRun)) {
                $iArticle++;
            }
        }
    }

    private function downloadPaperFiles(
        Episciences_Paper $paper,
        string $dirnameVol,
        int $iArticle,
        bool $dryRun
    ): bool {
        $docId  = $paper->getDocid();
        $pdfUrl = $this->buildPaperUrl($this->review->getCode(), $docId, 'pdf');
        $xmlUrl = $this->buildPaperUrl($this->review->getCode(), $docId, 'zbjats');

        if ($dryRun) {
            $this->logger->info('[dry-run] Would download paper files', ['docId' => $docId, 'pdf' => $pdfUrl, 'xml' => $xmlUrl]);
            return true;
        }

        try {
            $pdfResponse = $this->httpClient->request('GET', $pdfUrl);

            if ($pdfResponse->getStatusCode() !== 200) {
                $this->logger->warning('Unexpected status for PDF', [
                    'docId'  => $docId,
                    'status' => $pdfResponse->getStatusCode(),
                ]);
                return false;
            }

            file_put_contents(
                sprintf('%sarticle%d.pdf', $dirnameVol, $iArticle),
                $pdfResponse->getBody()->getContents()
            );
            $this->logger->info('Downloaded PDF', ['docId' => $docId, 'url' => $pdfUrl]);

            $xmlResponse = $this->httpClient->request('GET', $xmlUrl);

            if ($xmlResponse->getStatusCode() === 200) {
                file_put_contents(
                    sprintf('%sarticle%d.xml', $dirnameVol, $iArticle),
                    $xmlResponse->getBody()->getContents()
                );
                $this->logger->info('Downloaded XML', ['docId' => $docId, 'url' => $xmlUrl]);
            }

            return true;

        } catch (GuzzleException $e) {
            $this->logger->error('Failed to download paper files', [
                'docId' => $docId,
                'url'   => $pdfUrl,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Build the URL for a paper resource.
     *
     * @param string $rvCode   Journal RV code
     * @param int    $docId    Document identifier
     * @param string $format   Resource format: 'pdf' or 'zbjats'
     */
    public static function buildPaperUrl(string $rvCode, int $docId, string $format): string
    {
        return sprintf('https://%s.%s/%d/%s', $rvCode, DOMAIN, $docId, $format);
    }

    /**
     * Build the ZIP output path.
     *
     * @param string $basePath   Directory that contains the volume subdirectories
     * @param string $reviewCode Journal RV code
     * @param string $zipPrefix  Optional filename prefix (e.g. "2024_")
     */
    public static function buildZipPath(string $basePath, string $reviewCode, string $zipPrefix = ''): string
    {
        return sprintf('%s%s%s.zip', $basePath, $zipPrefix, $reviewCode);
    }

    /**
     * @param string[] $tabvolRepoName
     * @throws \RuntimeException if the ZIP archive cannot be created
     */
    private function createZipArchive(array $tabvolRepoName, string $zipPrefix, bool $dryRun): void
    {
        $pathdir    = $this->getZbjatsPath();
        $zipcreated = self::buildZipPath($pathdir, $this->review->getCode(), $zipPrefix);

        if ($dryRun) {
            $this->logger->info('[dry-run] Would create ZIP archive', ['path' => $zipcreated]);
            return;
        }

        $zip = new \ZipArchive();

        if ($zip->open($zipcreated, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException(sprintf('Failed to create ZIP archive: %s', $zipcreated));
        }

        $this->logger->info('Creating ZIP archive', ['path' => $zipcreated]);

        foreach ($tabvolRepoName as $volumeDir) {
            $volumePath = $pathdir . $volumeDir;
            $iterator   = new \DirectoryIterator($volumePath);

            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isFile()) {
                    $zip->addFile(
                        $fileInfo->getPathname(),
                        sprintf('%s/%s', $volumeDir, $fileInfo->getFilename())
                    );
                }
            }
        }

        $zip->close();
        $this->logger->info('ZIP archive created', ['path' => $zipcreated]);
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
