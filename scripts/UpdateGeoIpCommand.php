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
 * Symfony Console command: download and install MaxMind GeoLite2-City database.
 *
 * Downloads the GeoLite2-City.mmdb from MaxMind using Basic Auth (account_id:license_key),
 * verifies the SHA-256 checksum, backs up the existing database with a date suffix,
 * and installs the new database at the configured path.
 *
 * Freshness is determined by a HEAD request to check MaxMind's Last-Modified header
 * against the local file's modification time (per MaxMind best practices — HEAD requests
 * do not count against the daily download limit).
 *
 * Since January 2024, MaxMind uses R2 presigned URLs for database downloads.
 * Requests are redirected to Cloudflare R2 storage; allow_redirects must be enabled.
 */
class UpdateGeoIpCommand extends Command
{
    protected static $defaultName = 'geoip:update';

    /** Required constant names derived from the GEO_IP section in pwd.json. */
    private const REQUIRED_CONSTANTS = [
        'GEO_IP_DATABASE_PATH',
        'GEO_IP_DATABASE',
        'GEO_IP_ACCOUNT_ID',
        'GEO_IP_LICENSE_KEY',
        'GEO_IP_DB_URL',
        'GEO_IP_DB_SHA256',
    ];

    /** @var list<string> Temporary files created during execution, cleaned up on failure. */
    private array $tempFiles = [];

    protected function configure(): void
    {
        $this
            ->setDescription('Download and install the MaxMind GeoLite2-City database')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Re-download even if the database is up to date')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without writing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $force  = (bool) $input->getOption('force');
        $dryRun = (bool) $input->getOption('dry-run');

        $this->bootstrap();

        $logger = $this->buildLogger($io);

        $result = $this->checkConstants($io, $logger);
        if ($result !== Command::SUCCESS) {
            return $result;
        }

        $destPath = $this->buildDestinationPath();

        if ($dryRun) {
            $io->note('Dry-run mode — no file will be written.');
            $io->writeln('Would check:    ' . (string) GEO_IP_DB_URL . ' (HEAD Last-Modified)');
            $io->writeln('Would verify:   ' . (string) GEO_IP_DB_SHA256);
            $io->writeln('Would write to: ' . $destPath);
            return Command::SUCCESS;
        }

        // Check freshness via HEAD request (does not count against download limit)
        if (!$force) {
            $remoteDate = $this->fetchRemoteLastModified($io, $logger);
            if ($remoteDate !== null && file_exists($destPath)) {
                $localMtime = (int) filemtime($destPath);
                if ($localMtime >= $remoteDate) {
                    $logger->info('GeoIP database is up to date (local: ' . date('Y-m-d', $localMtime) . ', remote: ' . date('Y-m-d', $remoteDate) . '). Use --force to override.');
                    $io->success('GeoIP database is up to date. Use --force to re-download.');
                    return Command::SUCCESS;
                }
                $logger->info('Remote database is newer (remote: ' . date('Y-m-d', $remoteDate) . '). Downloading.');
            }
        }

        // Step 1: Download checksum
        $expectedHash = $this->downloadChecksum($io, $logger);
        if ($expectedHash === null) {
            return Command::FAILURE;
        }

        // Step 2: Download tar.gz
        $tarGzPath = $this->makeTempFile('.tar.gz');
        $this->tempFiles[] = $tarGzPath;

        if (!$this->downloadTarGz($tarGzPath, $io, $logger)) {
            $this->cleanupTempFiles();
            return Command::FAILURE;
        }

        // Step 3: Validate checksum
        if (!$this->validateFileChecksum($tarGzPath, $expectedHash)) {
            $logger->error('SHA-256 checksum mismatch — aborting installation.');
            $io->error('SHA-256 checksum mismatch. The downloaded file may be corrupted.');
            $this->cleanupTempFiles();
            return Command::FAILURE;
        }

        $logger->info('Checksum verified successfully.');

        // Step 4: Extract .mmdb from tar.gz
        $mmdbPath = $this->extractMmdb($tarGzPath, $logger);
        if ($mmdbPath === null) {
            $logger->error('Failed to extract .mmdb from archive.');
            $io->error('Failed to extract GeoLite2-City.mmdb from the downloaded archive.');
            $this->cleanupTempFiles();
            return Command::FAILURE;
        }
        $this->tempFiles[] = $mmdbPath;

        // Step 5: Backup existing database
        if (file_exists($destPath)) {
            $backupPath = $this->buildBackupPath($destPath, date('Ymd_His'));
            if (!rename($destPath, $backupPath)) {
                $logger->error('Failed to backup existing database to: ' . $backupPath);
                $io->error('Could not backup existing GeoIP database.');
                $this->cleanupTempFiles();
                return Command::FAILURE;
            }
            $logger->info('Backed up existing database to: ' . $backupPath);
        }

        // Step 6: Install new database
        $dir = dirname($destPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            $logger->error('Cannot create directory: ' . $dir);
            $io->error('Failed to create directory: ' . $dir);
            $this->cleanupTempFiles();
            return Command::FAILURE;
        }

        if (!rename($mmdbPath, $destPath)) {
            $logger->error('Failed to install database at: ' . $destPath);
            $io->error('Failed to install GeoIP database.');
            $this->cleanupTempFiles();
            return Command::FAILURE;
        }

        chmod($destPath, 0644);

        $this->cleanupTempFiles();

        $logger->info('GeoIP database installed at: ' . $destPath);
        $io->success('GeoLite2-City database updated successfully: ' . $destPath);
        return Command::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Public pure methods (testable without I/O)
    // -------------------------------------------------------------------------

    /**
     * Returns the absolute path where the GeoIP database file is stored.
     */
    public function buildDestinationPath(): string
    {
        return rtrim((string) GEO_IP_DATABASE_PATH, '/') . '/' . (string) GEO_IP_DATABASE;
    }

    /**
     * Returns the backup path for the existing database (appends a date/time suffix).
     *
     * @param string $destPath Full path to the current database file
     * @param string $suffix   Suffix appended after a dot (e.g. 'YYYYmmdd_HHiiss')
     */
    public function buildBackupPath(string $destPath, string $suffix): string
    {
        return $destPath . '.' . $suffix;
    }

    /**
     * Parses the SHA-256 checksum file content and extracts the hash.
     *
     * MaxMind checksum files have the format:
     *   abc123def456...  GeoLite2-City_20240101.tar.gz
     *
     * @param string $content Raw content of the .sha256 file
     * @return string The hex hash, or empty string if content is empty/unparseable
     */
    public function parseChecksumContent(string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $content);
        if ($parts === false) {
            return '';
        }

        return trim($parts[0]);
    }

    /**
     * Validates the SHA-256 checksum of a file against an expected hash.
     *
     * Uses hash_equals() for timing-safe comparison.
     *
     * @param string $filePath     Absolute path to the file to check
     * @param string $expectedHash Expected SHA-256 hex string
     */
    public function validateFileChecksum(string $filePath, string $expectedHash): bool
    {
        if ($expectedHash === '' || !file_exists($filePath)) {
            return false;
        }

        $actualHash = hash_file('sha256', $filePath);
        if ($actualHash === false) {
            return false;
        }

        return hash_equals($expectedHash, $actualHash);
    }

    /**
     * Parses the value of a Last-Modified HTTP header into a Unix timestamp.
     *
     * @param string $headerValue e.g. "Tue, 05 Mar 2024 12:00:00 GMT"
     * @return int|null Unix timestamp, or null if the value cannot be parsed
     */
    public function parseLastModifiedHeader(string $headerValue): ?int
    {
        $ts = strtotime($headerValue);
        return ($ts !== false && $ts > 0) ? $ts : null;
    }

    // -------------------------------------------------------------------------
    // Private methods
    // -------------------------------------------------------------------------

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

        $application = new Zend_Application('production', APPLICATION_PATH . '/configs/application.ini');

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);

        $db = Zend_Db::factory('PDO_MYSQL', $application->getOption('resources')['db']['params']);
        Zend_Db_Table::setDefaultAdapter($db);

        Zend_Registry::set('metadataSources', Episciences_Paper_MetaDataSourcesManager::all(false));
        Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));
    }

    private function buildLogger(SymfonyStyle $io): Logger
    {
        $logger = new Logger('updateGeoIp');
        $logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . 'updateGeoIp_' . date('Y-m-d') . '.log',
            Logger::INFO
        ));
        if (!$io->isQuiet()) {
            $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }
        return $logger;
    }

    /**
     * Verifies that all required GEO_IP_* constants are defined.
     *
     * @return int Command::SUCCESS or Command::FAILURE
     */
    private function checkConstants(SymfonyStyle $io, Logger $logger): int
    {
        foreach (self::REQUIRED_CONSTANTS as $constant) {
            if (!defined($constant)) {
                $logger->error('Missing required constant: ' . $constant);
                $io->error('Missing required constant: ' . $constant . '. Check the GEO_IP section in your pwd.json.');
                return Command::FAILURE;
            }
        }
        return Command::SUCCESS;
    }

    /**
     * Issues a HEAD request to MaxMind to retrieve the Last-Modified date of the remote database.
     *
     * HEAD requests do not count against the daily download limit (per MaxMind best practices).
     * MaxMind redirects to Cloudflare R2 presigned URLs — allow_redirects must be enabled.
     *
     * @return int|null Unix timestamp of the remote Last-Modified date, or null on failure
     */
    private function fetchRemoteLastModified(SymfonyStyle $io, Logger $logger): ?int
    {
        $url = (string) GEO_IP_DB_URL;
        $logger->info('Checking remote Last-Modified (HEAD): ' . $url);

        try {
            $client   = new Client(['timeout' => 30]);
            $response = $client->head($url, [
                'auth'            => [(string) GEO_IP_ACCOUNT_ID, (string) GEO_IP_LICENSE_KEY],
                'allow_redirects' => true,
            ]);

            $headerLine = $response->getHeaderLine('Last-Modified');
            if ($headerLine === '') {
                $logger->info('No Last-Modified header in HEAD response — will proceed with download.');
                return null;
            }

            $ts = $this->parseLastModifiedHeader($headerLine);
            if ($ts === null) {
                $logger->info('Could not parse Last-Modified header: ' . $headerLine);
            }
            return $ts;
        } catch (GuzzleException $e) {
            $logger->info('HEAD request failed: ' . $e->getMessage() . ' — will proceed with download.');
            return null;
        }
    }

    /**
     * Downloads the SHA-256 checksum file from MaxMind and returns the hash string.
     *
     * @return string|null The hex hash, or null on failure
     */
    private function downloadChecksum(SymfonyStyle $io, Logger $logger): ?string
    {
        $url = (string) GEO_IP_DB_SHA256;
        $logger->info('Downloading checksum from: ' . $url);

        try {
            $client   = new Client(['timeout' => 30]);
            $response = $client->get($url, [
                'auth'            => [(string) GEO_IP_ACCOUNT_ID, (string) GEO_IP_LICENSE_KEY],
                'allow_redirects' => true,
            ]);
            $content = (string) $response->getBody();
        } catch (GuzzleException $e) {
            $logger->error('Checksum download failed: ' . $e->getMessage());
            $io->error('Failed to download checksum: ' . $e->getMessage());
            return null;
        }

        $hash = $this->parseChecksumContent($content);
        if ($hash === '') {
            $logger->error('Checksum file is empty or unparseable.');
            $io->error('Downloaded checksum file is empty or in an unexpected format.');
            return null;
        }

        $logger->info('Expected SHA-256: ' . $hash);
        return $hash;
    }

    /**
     * Downloads the GeoLite2-City tar.gz archive to a temporary file.
     *
     * MaxMind redirects to Cloudflare R2 presigned URLs — allow_redirects must be enabled.
     *
     * @param string $destPath Absolute path of the temp file to write to
     * @return bool True on success, false on failure
     */
    private function downloadTarGz(string $destPath, SymfonyStyle $io, Logger $logger): bool
    {
        $url = (string) GEO_IP_DB_URL;
        $logger->info('Downloading GeoIP database from: ' . $url);

        try {
            $client = new Client(['timeout' => 120]);
            $client->get($url, [
                'auth'            => [(string) GEO_IP_ACCOUNT_ID, (string) GEO_IP_LICENSE_KEY],
                'allow_redirects' => true,
                'sink'            => $destPath,
            ]);
        } catch (GuzzleException $e) {
            $logger->error('Database download failed: ' . $e->getMessage());
            $io->error('Failed to download GeoIP database: ' . $e->getMessage());
            return false;
        }

        $logger->info('Archive downloaded to: ' . $destPath);
        return true;
    }

    /**
     * Extracts the .mmdb file from a tar.gz archive into a temp file.
     *
     * @param string $tarGzPath Absolute path to the .tar.gz file
     * @return string|null Path to the extracted .mmdb temp file, or null on failure
     */
    private function extractMmdb(string $tarGzPath, Logger $logger): ?string
    {
        $mmdbTmp = $this->makeTempFile('.mmdb');

        try {
            $phar   = new \PharData($tarGzPath);
            $tmpDir = sys_get_temp_dir() . '/geoip_extract_' . bin2hex(random_bytes(8));
            mkdir($tmpDir, 0700, true);

            $phar->extractTo($tmpDir, null, true);

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tmpDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            $mmdbSource = null;
            foreach ($iterator as $file) {
                if (!($file instanceof \SplFileInfo)) {
                    continue;
                }
                if ($file->getExtension() === 'mmdb') {
                    $mmdbSource = $file->getRealPath();
                    break;
                }
            }

            if ($mmdbSource === null || $mmdbSource === false) {
                $this->removeDirectory($tmpDir);
                $logger->error('No .mmdb file found in archive: ' . $tarGzPath);
                return null;
            }

            if (!rename($mmdbSource, $mmdbTmp)) {
                $this->removeDirectory($tmpDir);
                $logger->error('Failed to move extracted .mmdb to temp path.');
                return null;
            }

            $this->removeDirectory($tmpDir);
        } catch (\Exception $e) {
            $logger->error('Extraction failed: ' . $e->getMessage());
            if (isset($tmpDir) && is_dir($tmpDir)) {
                $this->removeDirectory($tmpDir);
            }
            return null;
        }

        $logger->info('Extracted .mmdb to: ' . $mmdbTmp);
        return $mmdbTmp;
    }

    /**
     * Creates a unique temporary file path with the given suffix.
     *
     * Uses sys_get_temp_dir() + random bytes to avoid race conditions.
     *
     * @param string $suffix File extension suffix (e.g. '.tar.gz', '.mmdb')
     */
    private function makeTempFile(string $suffix): string
    {
        return sys_get_temp_dir() . '/geoip_' . bin2hex(random_bytes(8)) . $suffix;
    }

    /**
     * Recursively removes a directory and all its contents.
     *
     * @param string $dir Absolute path to the directory to remove
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if (!($file instanceof \SplFileInfo)) {
                continue;
            }
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }

    /**
     * Removes all temporary files created during execution.
     */
    private function cleanupTempFiles(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->tempFiles = [];
    }
}
