<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

require_once __DIR__ . '/../vendor/autoload.php';
require_once "JournalScript.php";

class UpdateStatistics extends Command
{
    // Regular expression patterns to analyze the logs
    private array $patterns = [
        'article_notice' => '/GET \/articles\/(\d+) HTTP/',
        'article_file_download' => '/GET \/articles\/(\d+)\/download HTTP/',
        'article_file_preview' => '/GET \/articles\/(\d+)\/preview HTTP/',
    ];

// Base path for the logs
    private string $logsBasePath = 'logs/httpd';
    private Logger $logger;
    private SymfonyStyle $io;

    /**
     * Class constructor
     */
    public function __construct()
    {
        parent::__construct();

        // Set default timezone to match log files
        date_default_timezone_set('Europe/Paris');

        // Initialize Monolog
        $this->logger = new Logger('update_statistics');
        $this->logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/statistics.log', Logger::INFO));
    }

    /**
     * Configure the command options
     */
    protected function configure()
    {
        $this->setName('update:statistics')
            ->setDescription('Update statistics for the site')
            ->addOption('rvcode', null, InputOption::VALUE_REQUIRED, 'The journal code (e.g., mbj)')
            ->addOption('date', null, InputOption::VALUE_OPTIONAL, 'Date to process (format: YYYY-mm-dd, default: yesterday)', $this->getYesterdayDate());
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input Command input
     * @param OutputInterface $output Command output
     * @return int Exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $startTime = microtime(true);
        $this->io = new SymfonyStyle($input, $output);

        // Get and validate parameters
        $rvcode = $input->getOption('rvcode');
        if (!$rvcode) {
            $this->io->error('The rvcode parameter is required.');
            $this->logger->error('The rvcode parameter is required.');
            return Command::FAILURE;
        }

        $date = $input->getOption('date');
        try {
            $dateObj = $this->validateDate($date);
            $formattedDate = $dateObj->format('Y-m-d');
        } catch (\Exception $e) {
            $this->io->error($e->getMessage());
            $this->logger->error($e->getMessage());
            return Command::FAILURE;
        }

        $this->io->text('Processing logs for the date: ' . $formattedDate . ' (only entries from 00:00:00 to 23:59:59)');
        $this->logger->info('Processing logs for the date: ' . $formattedDate . ' (only entries from 00:00:00 to 23:59:59)');

        $siteName = $rvcode . '.episciences.org';

        try {
            // Initialize the application and database
            $this->initializeApplication();

            // Build and validate the log file path
            $logFile = $this->buildLogFilePath($siteName, $dateObj);

            // Collect and filter article accesses
            $articleAccesses = $this->collectArticleAccesses($logFile, $formattedDate);

            if (empty($articleAccesses)) {
                $this->io->info("No article access found in log file for the specified date");
                $this->logger->info("No article access found in log file for the specified date");
                return Command::SUCCESS;
            }

            $this->io->success("Found " . count($articleAccesses) . " article accesses for " . $formattedDate);
            $this->logger->info("Found " . count($articleAccesses) . " article accesses for " . $formattedDate);

            // Display data summary
            $this->displayDataSummary($articleAccesses);

            // Insert data into STAT_TEMP
            $this->io->section("Inserting data into STAT_TEMP...");
            $insertedCount = $this->insertIntoStatTemp($articleAccesses);

            $this->io->success("Inserted {$insertedCount} records into STAT_TEMP");
            $this->logger->info("Inserted {$insertedCount} records into STAT_TEMP");

        } catch (\Exception $e) {
            $this->io->error("Error: " . $e->getMessage());
            $this->logger->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }

        $executionTime = round(microtime(true) - $startTime, 2);
        $this->io->comment("The script took {$executionTime} seconds to run.");
        $this->logger->info("The script took {$executionTime} seconds to run.");

        return Command::SUCCESS;
    }

    /**
     * Validate the date format and return a DateTime object
     *
     * @param string $date Date to validate
     * @return \DateTime Validated DateTime object
     * @throws \Exception If date is invalid
     */
    private function validateDate(string $date): \DateTime
    {
        $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
        if ($dateObj === false) {
            throw new \Exception("Invalid date format. Required format: YYYY-MM-DD");
        }

        // Set time to beginning of day to avoid timezone issues
        $dateObj->setTime(0, 0, 0);

        return $dateObj;
    }

    /**
     * Get yesterday's date formatted as Y-m-d
     *
     * @return string Yesterday's date
     */
    private function getYesterdayDate(): string
    {
        $yesterday = new \DateTime('yesterday');
        return $yesterday->format('Y-m-d');
    }

    /**
     * Initialize the application and database environment
     */
    private function initializeApplication(): void
    {
        defineSQLTableConstants();
        defineApplicationConstants();
    }

    /**
     * Build the path to the log file and check if it exists
     *
     * @param string $siteName Site name
     * @param \DateTime $date Date object
     * @return string Path to the log file
     * @throws \Exception If log file doesn't exist
     */
    private function buildLogFilePath(string $siteName, \DateTime $date): string
    {
        $year = $date->format('Y');
        $month = $date->format('m');
        $day = $date->format('d');

        $logFile = $this->logsBasePath . '/' . $siteName . '/' . $year . '/' . $month . '/' .
            $day . '-' . $siteName . '.access_log';

        if (!file_exists($logFile)) {
            throw new \Exception("Log file '{$logFile}' does not exist");
        }

        return $logFile;
    }

    /**
     * Collect and filter article accesses for a specific date
     *
     * @param string $logfile Path to the log file
     * @param string $targetDate Date to filter (Y-m-d format)
     * @return array Article accesses
     */
    private function collectArticleAccesses(string $logfile, string $targetDate): array
    {
        $articleAccesses = [];

        // Create timestamps for the beginning and end of the target day
        $startDay = strtotime($targetDate . ' 00:00:00');
        $endDay = strtotime($targetDate . ' 23:59:59');

        $this->logger->info(sprintf(
            "Filtering logs between %s and %s (timestamps: %d - %d)",
            date('Y-m-d H:i:s', $startDay),
            date('Y-m-d H:i:s', $endDay),
            $startDay,
            $endDay
        ));

        // Process the log file line by line
        if (file_exists($logfile)) {
            $this->logger->info("Processing log file: {$logfile}");
            $handle = fopen($logfile, 'r');
            if ($handle) {
                $lineCount = 0;
                $matchCount = 0;
                $dateMatches = 0;

                while (($line = fgets($handle)) !== false) {
                    $lineCount++;

                    // Extract timestamp first and verify its format
                    $timestamp = $this->extractTimestamp($line);

                    // Check if timestamp is within the target date range
                    if ($timestamp < $startDay || $timestamp > $endDay) {
                        continue;
                    }

                    $dateMatches++;

                    // Process article access patterns
                    $accessInfo = $this->processAccessPatterns($line);
                    if (!$accessInfo) {
                        continue;
                    }

                    ['accessType' => $accessType, 'docId' => $docId] = $accessInfo;
                    $matchCount++;

                    // Validate that docId is numeric and reasonable
                    if (!$this->isValidDocId($docId)) {
                        continue;
                    }

                    // Extract necessary information
                    $dateTime = date('Y-m-d H:i:s', $timestamp);
                    $ipv4 = $this->extractIP($line);

                    // Validate IP
                    if (!$this->isValidIP($ipv4)) {
                        continue;
                    }

                    $ip = sprintf("%u", ip2long($ipv4));
                    $userAgent = $this->extractUserAgent($line);

                    // Add the access to our collection
                    $articleAccesses[] = [
                        'doc_id' => $docId,
                        'ip' => $ip,
                        'user_agent' => $userAgent,
                        'date_time' => $dateTime,
                        'timestamp' => $timestamp,
                        'access_type' => $accessType,
                    ];
                }

                fclose($handle);
                $this->logger->info(sprintf(
                    "Processed %d lines, found %d entries matching date %s, %d matching patterns",
                    $lineCount, $dateMatches, $targetDate, $matchCount
                ));
            }
        }

        // Sort accesses by timestamp
        usort($articleAccesses, function($a, $b) {
            return $a['timestamp'] <=> $b['timestamp'];
        });

        return $articleAccesses;
    }

    /**
     * Process the access patterns to extract access type and document ID
     *
     * @param string $line Log line
     * @return array|null Access information or null if not matching
     */
    private function processAccessPatterns(string $line): ?array
    {
        foreach ($this->patterns as $type => $pattern) {
            if (preg_match($pattern, $line, $matches)) {
                $accessType = $type === 'article_notice' ? 'notice' : 'file';
                return [
                    'accessType' => $accessType,
                    'docId' => $matches[1]
                ];
            }
        }

        return null;
    }

    /**
     * Check if document ID is valid
     *
     * @param string $docId Document ID
     * @return bool True if valid
     */
    private function isValidDocId(string $docId): bool
    {
        if (!is_numeric($docId) || $docId <= 0 || $docId > 9999999) {
            $this->logger->warning("Invalid article ID: {$docId}");
            return false;
        }

        return true;
    }

    /**
     * Check if IP address is valid
     *
     * @param string $ipv4 IP address
     * @return bool True if valid
     */
    private function isValidIP(string $ipv4): bool
    {
        if (!filter_var($ipv4, FILTER_VALIDATE_IP) && $ipv4 !== 'unknown') {
            $this->logger->warning("Invalid IP address: {$ipv4}");
            return false;
        }

        return true;
    }

    /**
     * Display a summary of the data collected
     *
     * @param array $articleAccesses Article accesses
     */
    private function displayDataSummary(array $articleAccesses): void
    {
        $this->io->section("Data summary:");

        if (count($articleAccesses) > 0) {
            // Display the first log entry
            $firstAccess = $articleAccesses[0];
            $this->io->text("First item:");
            $this->io->text("Article: {$firstAccess['doc_id']}, Type: {$firstAccess['access_type']}, Date: {$firstAccess['date_time']}, IP: {$firstAccess['ip']}, UserAgent: {$firstAccess['user_agent']}");

            // If there are multiple entries, also display the last one
            if (count($articleAccesses) > 1) {
                $lastAccess = $articleAccesses[count($articleAccesses) - 1];
                $this->io->text("\nLast item:");
                $this->io->text("Article: {$lastAccess['doc_id']}, Type: {$lastAccess['access_type']}, Date: {$lastAccess['date_time']}, IP: {$lastAccess['ip']}, UserAgent: {$lastAccess['user_agent']}");
            }

            // Show the total count and time range
            $this->io->text("\nTotal entries: " . count($articleAccesses));
            $this->io->text("Time range: {$firstAccess['date_time']} to {$lastAccess['date_time']}");

            // Add summary statistics
            $noticeCount = count(array_filter($articleAccesses, function($access) {
                return $access['access_type'] === 'notice';
            }));

            $fileCount = count(array_filter($articleAccesses, function($access) {
                return $access['access_type'] === 'file';
            }));

            $this->io->text("\nAccess types:");
            $this->io->text("- Notice views: {$noticeCount}");
            $this->io->text("- File downloads/previews: {$fileCount}");
        } else {
            $this->io->text("No data available");
        }
    }

    /**
     * Insert article accesses into STAT_TEMP table
     *
     * @param array $articleAccesses Article accesses
     * @return int Number of records inserted
     */
    private function insertIntoStatTemp(array $articleAccesses): int
    {
        if (empty($articleAccesses)) {
            return 0;
        }

        $insertedCount = 0;
        $db = $this->getDb();

        $this->logger->info("Starting database insertion");

        // Start a transaction for bulk inserts
        $db->beginTransaction();

        try {
            $this->progressBar = $this->io->createProgressBar(count($articleAccesses));
            $this->progressBar->start();

            // Prepare the insert query
            $sql = 'INSERT INTO STAT_TEMP (docId, ip, http_user_agent, Dhit, Consult) VALUES (?, ?, ?, ?, ?)';

            $stmt = $db->prepare($sql);

            foreach ($articleAccesses as $access) {
                // Parameters for insertion
                $params = [
                    $access['doc_id'],
                    $access['ip'],
                    $access['user_agent'],
                    $access['date_time'],
                    $access['access_type']
                ];

                // Execute the insert
                $stmt->execute($params);
                $insertedCount++;

                // Update the progress bar
                $this->progressBar->advance();
            }

            // Commit the transaction
            $db->commit();
            $this->progressBar->finish();
            $this->io->newLine(2);

            $this->logger->info("Database insertion completed successfully");

        } catch (Exception $e) {
            // Rollback the transaction in case of error
            $db->rollBack();
            $this->logger->error("Database error: " . $e->getMessage());
            $this->io->error("Database error: " . $e->getMessage());
            throw $e;
        }

        return $insertedCount;
    }

    /**
     * Extract timestamp from a log line
     *
     * @param string $line Log line
     * @return int Unix timestamp
     */
    private function extractTimestamp(string $line): int
    {
        if (preg_match('/\[(\d+\/[A-Za-z]+\/\d+):(\d+:\d+:\d+) ([+-]\d{4})\]/', $line, $matches)) {
            $dateString = $matches[1] . ' ' . $matches[2];

            try {
                $date = \DateTime::createFromFormat('d/M/Y H:i:s', $dateString);
                if ($date) {
                    return $date->getTimestamp();
                }
            } catch (\Exception $e) {
                $this->logger->warning("Error parsing date: " . $e->getMessage());
            }
        }

        $this->logger->warning("Could not extract timestamp from line, using current time");
        return time();
    }

    /**
     * Extract IP address from a log line
     *
     * @param string $line Log line
     * @return string IP address
     */
    private function extractIP(string $line): string
    {
        preg_match('/\b([\d]+\.[\d]+\.[\d]+\.[\d]+)\b/', $line, $matches);
        $ip = $matches[1] ?? 'unknown';

        if ($ip === 'unknown') {
            $this->logger->warning("Could not extract IP address from line");
        }

        return $ip;
    }

    /**
     * Extract user agent from a log line
     *
     * @param string $line Log line
     * @return string User agent
     */
    private function extractUserAgent(string $line): string
    {
        // Regular expression to capture all quoted elements in the log line
        preg_match_all('/"([^"]+)"/', $line, $matches);

        // The User-Agent is always the last captured element
        $userAgent = $matches[1][count($matches[1]) - 1] ?? 'unknown';

        if ($userAgent === 'unknown') {
            $this->logger->warning("Could not extract User-Agent from line");
        }

        return $userAgent;
    }

    /**
     * Get database connection
     *
     * @return \Zend_Db_Adapter_Abstract Database connection
     * @throws \Exception If database connection fails
     */
    private function getDb()
    {
        try {
            // First try the default adapter
            $db = Zend_Db_Table::getDefaultAdapter();
            if ($db instanceof \Zend_Db_Adapter_Abstract) {
                $this->logger->info("Using Zend_Db_Table default adapter");
                return $db;
            }

            // Check and define APPLICATION_ENV if necessary
            $this->setupApplicationEnvironment();

            // Initialize Zend application
            $application = new Zend_Application(
                APPLICATION_ENV,
                APPLICATION_PATH . '/configs/application.ini'
            );

            $params = $application->getOption('resources')['db']['params'];
            if ($params) {
                $db = Zend_Db::factory('PDO_MYSQL', $params);
                Zend_Db_Table::setDefaultAdapter($db);
                $this->logger->info("Created new database connection from application config");
                return $db;
            }

            $this->logger->critical("Unable to establish database connection");
            throw new \Exception("No valid database connection available");
        } catch (\Exception $e) {
            $this->logger->critical("Database connection error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Setup application environment variables
     *
     * @param InputInterface|null $input Optional input interface from execute method
     */
    private function setupApplicationEnvironment(InputInterface $input = null): void
    {
        // Check and define APPLICATION_ENV if necessary
        if (!defined('APPLICATION_ENV')) {
            // Try to get the environment from command line option
            if ($input !== null && $this->getDefinition()->hasOption('app_env') &&
                $input->getOption('app_env')) {
                define('APPLICATION_ENV', $input->getOption('app_env'));
            } else {
                // Use 'development' as default
                define('APPLICATION_ENV', 'development');
                $this->logger->warning("APPLICATION_ENV not defined, using 'development'");
            }
        }

        // Check if APPLICATION_PATH is defined
        if (!defined('APPLICATION_PATH')) {
            define('APPLICATION_PATH', __DIR__ . '/../application');
            $this->logger->warning("APPLICATION_PATH not defined, using " . APPLICATION_PATH);
        }
    }
}

// Execute the script
$application = new Application();
$application->add(new UpdateStatistics());
$application->run();