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
 * Expected CSV format (with header row):
 *   Detectors,Doi,Title,Pubpeerusers,Pubpeerurl,Status
 */
class ImportRefPpsCommand extends Command
{
    protected static $defaultName = 'import:ref-pps';

    private const BATCH_SIZE = 1000;

    protected function configure(): void
    {
        $this
            ->setDescription('Import PPS data from a CSV file into the ref_pps Solr core.')
            ->addArgument('csv-file', InputArgument::OPTIONAL, 'Path to the CSV file', 'data/ref_pps/pps-current.csv');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $csvFile = (string) $input->getArgument('csv-file');

        if (!file_exists($csvFile) || !is_readable($csvFile)) {
            $io->error("CSV file not found or not readable: {$csvFile}");
            return Command::FAILURE;
        }

        $io->title("Importing PPS data from {$csvFile}");

        $this->bootstrap();

        $handle = fopen($csvFile, 'rb');
        if ($handle === false) {
            $io->error("Failed to open CSV file: {$csvFile}");
            return Command::FAILURE;
        }

        // Detect total lines for progress bar (optional, can be slow for 1.1M lines)
        // For now, let's use a dynamic progress bar or skip total count if it's too slow.
        $io->note("Counting lines...");
        $totalLines = 0;
        $lineCountHandle = fopen($csvFile, 'rb');
        while (!feof($lineCountHandle)) {
            fgets($lineCountHandle);
            $totalLines++;
        }
        fclose($lineCountHandle);
        $totalLines--; // Subtract header

        $progressBar = new ProgressBar($output, $totalLines);
        $progressBar->start();

        // Skip header
        $header = fgetcsv($handle);
        
        $solrClient = $this->getSolrClient();
        $updateQuery = $solrClient->createUpdate();
        
        $batch = [];
        $count = 0;
        $imported = 0;

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 6) {
                continue;
            }

            // Detectors,Doi,Title,Pubpeerusers,Pubpeerurl,Status
            $docData = [
                'detectors'    => $data[0],
                'doi'          => $data[1],
                'title'        => $data[2],
                'pubpeerusers' => $data[3],
                'pubpeerurl'   => $data[4],
                'status'       => $data[5],
            ];

            // Generate unique ID based on row content
            $docData['id'] = md5(implode('|', $data));

            $doc = $updateQuery->createDocument($docData);
            $batch[] = $doc;
            $count++;

            if ($count >= self::BATCH_SIZE) {
                $updateQuery->addDocuments($batch);
                $solrClient->update($updateQuery);
                
                // Reset for next batch
                $updateQuery = $solrClient->createUpdate();
                $batch = [];
                $imported += $count;
                $count = 0;
                $progressBar->advance(self::BATCH_SIZE);
            }
        }

        // Last batch
        if ($count > 0) {
            $updateQuery->addDocuments($batch);
            $solrClient->update($updateQuery);
            $imported += $count;
            $progressBar->advance($count);
        }

        // Commit changes
        $finalUpdate = $solrClient->createUpdate();
        $finalUpdate->addCommit();
        $solrClient->update($finalUpdate);

        $progressBar->finish();
        fclose($handle);

        $io->newLine(2);
        $io->success("Import completed: {$imported} documents imported into ref_pps core.");

        return Command::SUCCESS;
    }

    private function getSolrClient(): \Solarium\Client
    {
        $adapter = new \Solarium\Core\Client\Adapter\Curl();
        // Adjust timeout for large updates
        $adapter->setTimeout(300); 
        $eventDispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();
        
        $config = [
            'endpoint' => [
                'localhost' => [
                    'host' => ENDPOINTS_SEARCH_HOST,
                    'port' => ENDPOINTS_SEARCH_PORT,
                    'core' => 'ref_pps',
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

        defineApplicationConstants();
        // Load additional constants if needed
    }
}
