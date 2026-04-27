<?php
declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: download the PPS CSV file from IRIT.
 *
 * Downloads the CSV, handles the 48h limit, and keeps previous versions.
 */
class DownloadRefPpsCommand extends Command
{
    protected static $defaultName = 'download:ref-pps';

    private const URL = 'https://dbrech.irit.fr/pls/apex/f?p=9999:3::CSV::::';
    private const DOWNLOAD_DIR = 'data/ref_pps';
    private const CURRENT_FILE = 'data/ref_pps/pps-current.csv';
    private const LIMIT_HOURS = 48;

    protected function configure(): void
    {
        $this
            ->setDescription('Download the PPS CSV file from IRIT.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force download even if the 48h limit is not reached');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool)$input->getOption('force');

        if (!is_dir(self::DOWNLOAD_DIR)) {
            mkdir(self::DOWNLOAD_DIR, 0755, true);
        }

        if (!$force && file_exists(self::CURRENT_FILE)) {
            $lastDownload = filemtime(self::CURRENT_FILE);
            $hoursSinceLast = (time() - $lastDownload) / 3600;

            if ($hoursSinceLast < self::LIMIT_HOURS) {
                $io->note(sprintf(
                    "The last download was %.1f hours ago. The limit is %d hours. Use --force to override.",
                    $hoursSinceLast,
                    self::LIMIT_HOURS
                ));
                return Command::SUCCESS;
            }
        }

        $io->title("Downloading PPS CSV from IRIT");

        try {
            $client = new Client([
                'timeout' => 1200, // 20 minutes timeout for large CSV export
                'verify' => true,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Episciences-GPL Download Script)',
                ]
            ]);

            $tempFile = self::DOWNLOAD_DIR . '/pps-download-' . bin2hex(random_bytes(8)) . '.tmp';
            
            $io->text("Fetching: " . self::URL . " (this may take up to 10-15 minutes)");
            
            $client->get(self::URL, [
                'sink' => $tempFile,
            ]);

            if (file_exists(self::CURRENT_FILE)) {
                // Check if file content changed (MD5 comparison)
                if (md5_file($tempFile) === md5_file(self::CURRENT_FILE)) {
                    $io->info("The file hasn't changed. Skipping versioning.");
                    unlink($tempFile);
                    // Update mtime to reset the 48h clock even if content is same
                    touch(self::CURRENT_FILE);
                    return Command::SUCCESS;
                }

                // Keep previous version
                $timestamp = date('Ymd_His', filemtime(self::CURRENT_FILE));
                $backupFile = self::DOWNLOAD_DIR . '/pps-' . $timestamp . '.csv';
                rename(self::CURRENT_FILE, $backupFile);
                $io->note("Previous version saved to: " . $backupFile);
            }

            rename($tempFile, self::CURRENT_FILE);
            $io->success("New version downloaded successfully: " . self::CURRENT_FILE);

        } catch (GuzzleException $e) {
            $io->error("Download failed: " . $e->getMessage());
            if (isset($tempFile) && file_exists($tempFile)) {
                unlink($tempFile);
            }
            return Command::FAILURE;
        } catch (\Exception $e) {
            $io->error("An error occurred: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
