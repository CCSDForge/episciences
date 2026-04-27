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
 * Downloads the CSV, handles the 48h rate limit, and keeps timestamped backups.
 */
class DownloadRefPpsCommand extends Command
{
    protected static $defaultName = 'download:ref-pps';

    private const URL = 'https://dbrech.irit.fr/pls/apex/f?p=9999:3::CSV::::';
    private const LIMIT_HOURS = 48;

    protected function configure(): void
    {
        $this
            ->setDescription('Download the PPS CSV file from IRIT.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force download even if the 48h limit is not reached');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        $downloadDir = dirname(__DIR__) . '/data/ref_pps';
        $currentFile = $downloadDir . '/pps-current.csv';

        if (!is_dir($downloadDir)) {
            if (!mkdir($downloadDir, 0755, true) && !is_dir($downloadDir)) {
                $io->error(sprintf('Cannot create download directory "%s". Check filesystem permissions.', $downloadDir));
                return Command::FAILURE;
            }
        }

        if (!$force && file_exists($currentFile)) {
            $hoursSinceLast = $this->hoursSince((int) filemtime($currentFile));
            if ($this->isRateLimited($hoursSinceLast)) {
                $io->note(sprintf(
                    'The last download was %.1f hours ago. The limit is %d hours. Use --force to override.',
                    $hoursSinceLast,
                    self::LIMIT_HOURS
                ));
                return Command::SUCCESS;
            }
        }

        $io->title('Downloading PPS CSV from IRIT');

        $tempFile = null;
        try {
            $client = new Client([
                'timeout' => 1200, // 20 minutes timeout for large CSV export
                'verify'  => true,
                'headers' => ['User-Agent' => 'Mozilla/5.0 (Episciences-GPL Download Script)'],
            ]);

            $tempFile = $downloadDir . '/pps-download-' . bin2hex(random_bytes(8)) . '.tmp';
            $io->text('Fetching: ' . self::URL . ' (this may take up to 20 minutes)');
            $client->get(self::URL, ['sink' => $tempFile]);

            if (file_exists($currentFile)) {
                $newHash     = md5_file($tempFile);
                $currentHash = md5_file($currentFile);

                if ($newHash === false || $currentHash === false) {
                    $io->error('Cannot read file(s) for MD5 comparison. Check permissions.');
                    unlink($tempFile);
                    $tempFile = null;
                    return Command::FAILURE;
                }

                if ($newHash === $currentHash) {
                    $io->info("The file hasn't changed. Skipping versioning.");
                    unlink($tempFile);
                    $tempFile = null;
                    // Update mtime to reset the 48h clock even if content is same
                    touch($currentFile);
                    return Command::SUCCESS;
                }

                $backupFile = $this->buildBackupPath($downloadDir, (int) filemtime($currentFile));
                if (!rename($currentFile, $backupFile)) {
                    $io->error(sprintf('Failed to archive previous version to: %s', $backupFile));
                    unlink($tempFile);
                    $tempFile = null;
                    return Command::FAILURE;
                }
                $io->note('Previous version saved to: ' . $backupFile);
            }

            if (!rename($tempFile, $currentFile)) {
                $io->error(sprintf('Failed to install downloaded file as "%s".', $currentFile));
                if (isset($backupFile) && file_exists($backupFile) && !file_exists($currentFile)) {
                    rename($backupFile, $currentFile);
                }
                return Command::FAILURE;
            }
            $tempFile = null;

            $io->success('New version downloaded successfully: ' . $currentFile);

        } catch (GuzzleException $e) {
            $io->error('Download failed: ' . $e->getMessage());
            if ($tempFile !== null && file_exists($tempFile)) {
                unlink($tempFile);
            }
            return Command::FAILURE;
        } catch (\Throwable $e) {
            $io->error(sprintf('Unexpected %s: %s', get_class($e), $e->getMessage()));
            if ($tempFile !== null && file_exists($tempFile)) {
                unlink($tempFile);
            }
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    public function isRateLimited(float $hoursSinceLast): bool
    {
        return $hoursSinceLast < self::LIMIT_HOURS;
    }

    public function hoursSince(int $timestamp): float
    {
        return (time() - $timestamp) / 3600;
    }

    public function buildBackupPath(string $dir, int $mtime): string
    {
        return $dir . '/pps-' . date('Ymd_His', $mtime) . '.csv';
    }
}
