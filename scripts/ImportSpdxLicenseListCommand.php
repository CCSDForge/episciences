<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use scripts\Command\AbstractCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Import / update the official SPDX license list into the `license_spdx` table.
 *
 * Replaces: scripts/dumpingSPDXLicenseList.php
 *
 * Examples:
 *   php scripts/console.php import:spdx-license-list
 *   php scripts/console.php import:spdx-license-list --dry-run
 */
#[AsCommand(
    name: 'import:spdx-license-list',
    description: 'Import / update the official SPDX license list (https://spdx.org/licenses/licenses.json) into the `license_spdx` table'
)]
class ImportSpdxLicenseListCommand extends AbstractCommand
{
    private const SPDX_URL = 'https://spdx.org/licenses/licenses.json';

    private const TABLE_NAME = 'license_spdx';

    private const RECOMMENDED_REGEX = '#^CC-BY(?:-NC)?(?:-(?:ND|SA))?-4\.0$#i';

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate: write the SQL dump to a file without modifying the database')
            ->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'Directory where to write the SQL dump (only used with --dry-run)', sys_get_temp_dir());
    }

    protected function runLogic(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->io;
        $isDryRun = (bool) $input->getOption('dry-run');
        $outputDir = (string) $input->getOption('output-dir');

        $io->title('SPDX license list import');
        $io->writeln('Loading the SPDX base (official JSON: ' . self::SPDX_URL . ')');

        try {
            $licenses = json_decode($this->getLicenses(self::SPDX_URL), true, 512, JSON_THROW_ON_ERROR)['licenses'] ?? [];
        } catch (GuzzleException|JsonException $e) {
            $this->logger->critical($e->getMessage());
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $count = count($licenses);

        if ($count === 0) {
            $io->error('Empty result: no license could be retrieved from the SPDX base.');
            return Command::FAILURE;
        }

        $upserts = $this->buildUpsertStatements($licenses);

        if ($isDryRun) {
            $filePath = sprintf('%s/license_list_dump-%s.sql', rtrim($outputDir, '/'), date('Y-m-d-H-i-s'));
            $sqlDump = $this->buildSqlDump($upserts, $count);
            $written = @file_put_contents($filePath, $sqlDump);

            if ($written !== strlen($sqlDump)) {
                $message = sprintf('Dry-run: unable to write the SQL dump to %s', $filePath);
                $this->logger->critical($message);
                $io->error($message);
                return Command::FAILURE;
            }

            $io->success(sprintf('Dry-run: SQL dump written to %s', $filePath));
            $io->writeln(sprintf('Total licenses found (SPDX base): %d', $count));
            $io->writeln(sprintf('Total licenses processed: %d', $count));
            return Command::SUCCESS;
        }

        try {
            // CREATE TABLE causes an implicit commit in MySQL: run it before the transaction
            // so that rollBack() can undo all the upserts on failure
            $this->db->query($this->buildCreateTableStatement());
        } catch (Exception $e) {
            $message = 'Import failed (table creation): ' . $e->getMessage();
            $this->logger->critical($message);
            $io->error($message);
            return Command::FAILURE;
        }

        try {
            $this->db->beginTransaction();
            $this->db->query($upserts);
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            $message = 'Import failed: ' . $e->getMessage();
            $this->logger->critical($message);
            $io->error($message);
            return Command::FAILURE;
        }

        $this->logger->info(sprintf('SPDX license list imported: %d licenses processed.', $count));
        $io->success(sprintf('Import completed: %d licenses processed.', $count));

        return Command::SUCCESS;
    }

    /**
     * @throws GuzzleException
     */
    private function getLicenses(string $url): string
    {
        $client = new Client();
        $response = $client->get($url);
        return $response->getBody()->getContents();
    }

    private function buildCreateTableStatement(): string
    {
        $tableName = self::TABLE_NAME;

        return <<<SQL
CREATE TABLE IF NOT EXISTS `$tableName` (
  `code` varchar(64) COLLATE utf8mb4_general_ci NOT NULL PRIMARY KEY,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `recommended` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL;
    }

    /**
     * Standalone dump (dry-run): table structure followed by the upserts
     */
    private function buildSqlDump(string $upserts, int $count): string
    {
        $date = date('Y-m-d H:i:s');
        $createTable = $this->buildCreateTableStatement();
        $upserts = ltrim($upserts);

        return <<<SQL
--
-- Table structure
--

$createTable

--
--  [$date] Dumping data from the SPDX base (official JSON: https://spdx.org/licenses/licenses.json)
--
--  Total SPDX licenses found: $count

-- Data export

$upserts
SQL;
    }

    private function buildUpsertStatements(array $licenses): string
    {
        $sqlDump = '';

        foreach ($licenses as $licenseInfo) {
            $rawCode = (string) ($licenseInfo['licenseId'] ?? '');
            // SQL-quote the values with the adapter (keeps the original text, unlike HTML escaping)
            $code = $this->db->quote($rawCode);
            $name = $this->db->quote((string) ($licenseInfo['name'] ?? ''));
            $isRecommended = (int) $this->isRecommended($rawCode);

            $sqlDump .= "\n";
            $sqlDump .= "INSERT INTO `" . self::TABLE_NAME . "` (`code`, `name`, `recommended`) VALUES ($code, $name, $isRecommended) ON DUPLICATE KEY UPDATE `name` = $name, `recommended` = $isRecommended;";
        }

        return $sqlDump;
    }

    private function isRecommended(string $code): int
    {
        return (int) preg_match(self::RECOMMENDED_REGEX, $code);
    }
}
