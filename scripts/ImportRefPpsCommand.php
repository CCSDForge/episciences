<?php
declare(strict_types=1);

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: import PPS data from a CSV file into Solr.
 *
 * Expected CSV format (with header row). Column order is detected from the header,
 * so extra columns in the source file are ignored gracefully:
 *   Detectors,Doi,Title,Pubpeerusers,Pubpeerurl,Status
 */
class ImportRefPpsCommand extends Command
{
    protected static $defaultName = 'import:ref-pps';

    private const BATCH_SIZE = 1000;

    private const REQUIRED_COLUMNS = ['detectors', 'doi', 'title', 'pubpeerusers', 'pubpeerurl', 'status'];

    protected function configure(): void
    {
        $this
            ->setDescription('Import PPS data from a CSV file into the ref_pps Solr core.')
            ->addArgument('csv-file', InputArgument::OPTIONAL, 'Path to the CSV file', 'data/ref_pps/pps-current.csv');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $csvFile = (string) $input->getArgument('csv-file');

        // Resolve relative paths from the project root (one level above scripts/)
        if (!str_starts_with($csvFile, '/')) {
            $csvFile = dirname(__DIR__) . '/' . $csvFile;
        }

        if (!file_exists($csvFile) || !is_readable($csvFile)) {
            $io->error("CSV file not found or not readable: {$csvFile}");
            return Command::FAILURE;
        }

        $this->bootstrap();

        $io->title("Importing PPS data from {$csvFile}");

        $handle = fopen($csvFile, 'rb');
        if ($handle === false) {
            $io->error("Failed to open CSV file: {$csvFile}");
            return Command::FAILURE;
        }

        $totalLines = $this->countDataLines($csvFile);
        if ($totalLines === null) {
            $io->error("Failed to count lines in CSV file: {$csvFile}");
            fclose($handle);
            return Command::FAILURE;
        }

        $io->note("Lines to import: {$totalLines}");
        $progressBar = new ProgressBar($output, $totalLines);
        $progressBar->start();

        $header = fgetcsv($handle);
        if ($header === false) {
            $io->error("CSV file appears empty or has no header row: {$csvFile}");
            $progressBar->finish();
            fclose($handle);
            return Command::FAILURE;
        }

        $columnMap = self::buildColumnMap($header);
        if (!self::validateColumnMap($columnMap)) {
            $progressBar->finish();
            $io->newLine(2);
            $io->error(sprintf(
                'CSV header is missing required columns. Found: [%s]. Required: [%s]',
                implode(', ', array_keys($columnMap)),
                implode(', ', self::REQUIRED_COLUMNS)
            ));
            fclose($handle);
            return Command::FAILURE;
        }

        $solrClient  = $this->getSolrClient();
        $updateQuery = $solrClient->createUpdate();
        $batch       = [];
        $count       = 0;
        $imported    = 0;
        $skipped     = 0;

        while (($data = fgetcsv($handle)) !== false) {
            if (!self::isValidRow($data, $columnMap)) {
                $skipped++;
                continue;
            }

            $doc     = $updateQuery->createDocument(self::mapRowToDocument($data, $columnMap));
            $batch[] = $doc;
            $count++;

            if ($count >= self::BATCH_SIZE) {
                $updateQuery->addDocuments($batch);
                try {
                    $solrClient->update($updateQuery);
                } catch (\Solarium\Exception\ExceptionInterface $e) {
                    $progressBar->finish();
                    $io->newLine(2);
                    $io->error(sprintf('Solr update failed after %d documents: %s', $imported, $e->getMessage()));
                    fclose($handle);
                    return Command::FAILURE;
                }
                $updateQuery = $solrClient->createUpdate();
                $batch       = [];
                $imported   += $count;
                $count       = 0;
                $progressBar->advance(self::BATCH_SIZE);
            }
        }

        if ($count > 0) {
            $updateQuery->addDocuments($batch);
            try {
                $solrClient->update($updateQuery);
            } catch (\Solarium\Exception\ExceptionInterface $e) {
                $progressBar->finish();
                $io->newLine(2);
                $io->error(sprintf('Solr update failed on final batch after %d documents: %s', $imported, $e->getMessage()));
                fclose($handle);
                return Command::FAILURE;
            }
            $imported += $count;
            $progressBar->advance($count);
        }

        $finalUpdate = $solrClient->createUpdate();
        $finalUpdate->addCommit();
        try {
            $solrClient->update($finalUpdate);
        } catch (\Solarium\Exception\ExceptionInterface $e) {
            $progressBar->finish();
            $io->newLine(2);
            $io->error(sprintf(
                'Solr commit failed after sending %d documents. Data may not be fully committed: %s',
                $imported,
                $e->getMessage()
            ));
            fclose($handle);
            return Command::FAILURE;
        }

        $progressBar->finish();
        fclose($handle);

        $io->newLine(2);
        if ($skipped > 0) {
            $io->warning("Skipped {$skipped} rows with insufficient fields.");
        }
        $io->success("Import completed: {$imported} documents imported into ref_pps core.");

        return Command::SUCCESS;
    }

    /**
     * Builds a lowercase name→index map from the CSV header row.
     *
     * @param list<string|null> $header
     * @return array<string, int>
     */
    public static function buildColumnMap(array $header): array
    {
        $map = [];
        foreach ($header as $index => $name) {
            $map[strtolower(trim((string) $name))] = $index;
        }
        return $map;
    }

    /** @param array<string, int> $columnMap */
    public static function validateColumnMap(array $columnMap): bool
    {
        foreach (self::REQUIRED_COLUMNS as $col) {
            if (!isset($columnMap[$col])) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param list<string|null> $data
     * @param array<string, int> $columnMap
     */
    public static function isValidRow(array $data, array $columnMap): bool
    {
        foreach ($columnMap as $index) {
            if (!array_key_exists($index, $data)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Maps a CSV data row to a Solr document array.
     *
     * @param list<string|null> $data
     * @param array<string, int> $columnMap
     * @return array<string, string|list<string>>
     */
    public static function mapRowToDocument(array $data, array $columnMap): array
    {
        $doi = trim((string) ($data[$columnMap['doi']] ?? ''));
        return [
            'id'           => strtolower($doi),
            'detectors'    => self::splitMultiValue((string) ($data[$columnMap['detectors']] ?? '')),
            'doi'          => $doi,
            'title'        => trim((string) ($data[$columnMap['title']] ?? '')),
            'pubpeerusers' => self::splitMultiValue((string) ($data[$columnMap['pubpeerusers']] ?? '')),
            'pubpeerurl'   => trim((string) ($data[$columnMap['pubpeerurl']] ?? '')),
            'status'       => trim((string) ($data[$columnMap['status']] ?? '')),
        ];
    }

    /** @return list<string> */
    private static function splitMultiValue(string $value): array
    {
        if (trim($value) === '' || trim($value) === '-') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    public function countDataLines(string $csvFile): ?int
    {
        $handle = fopen($csvFile, 'rb');
        if ($handle === false) {
            return null;
        }
        $lines = 0;
        while (fgets($handle) !== false) {
            $lines++;
        }
        fclose($handle);
        return max(0, $lines - 1); // subtract header
    }

    protected function getSolrClient(): \Solarium\Client
    {
        $adapter = new \Solarium\Core\Client\Adapter\Curl();
        $adapter->setTimeout(ENDPOINTS_SEARCH_TIMEOUT);
        $eventDispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();

        $config = [
            'endpoint' => [
                'localhost' => [
                    'host'     => ENDPOINTS_SEARCH_HOST,
                    'port'     => ENDPOINTS_SEARCH_PORT,
                    'timeout'  => ENDPOINTS_SEARCH_TIMEOUT,
                    'username' => ENDPOINTS_SEARCH_USERNAME,
                    'password' => ENDPOINTS_SEARCH_PASSWORD,
                    'core'     => 'ref_pps',
                ]
            ]
        ];

        return new \Solarium\Client($adapter, $eventDispatcher, $config);
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
        defineApplicationConstants();

        $libraries = [realpath(APPLICATION_PATH . '/../library')];
        set_include_path(implode(PATH_SEPARATOR, array_merge($libraries, [get_include_path()])));
        require_once 'Zend/Application.php';

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);
    }
}
