<?php

declare(strict_types=1);

use Episciences\Paper\Spdx\LicenseSpdxResolver;
use scripts\Command\AbstractCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 * To run the normalization command in dry-run mode: php console.php papers:licenses --resolve --dry-run
 * To update license(s):
 * php console.php papers:licenses --update  --license CC0-1.0   --new-license cc-by-1.0
 * php console.php papers:licenses --update  --license https://about.hal.science/hal-authorisation-v1   --new-license cc-by-1.0
 */
#[AsCommand(
        name: 'papers:licenses',
        description: 'This command automatically normalizes licenses to SPDX format using `paper_licences` table. Individual or batch updates are also supported'
)]
class UpdateLicensesCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this
                ->addOption('document', 'd', InputOption::VALUE_REQUIRED, 'Document ID')
                ->addOption('new-license', null, InputOption::VALUE_REQUIRED, 'The SPDX identifier for the new license')
                ->addOption('license', null, InputOption::VALUE_REQUIRED, 'Code of the existing license to target')
                ->addOption('rvcode', null, InputOption::VALUE_REQUIRED, 'Restrict processing to one journal (RV code)')
                ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation mode (Dry Run)')
                ->addOption('resolve', 'r', InputOption::VALUE_NONE, 'Convert licenses to the SPDX standard')
                ->addOption('update', 'u', InputOption::VALUE_NONE, 'Update a license with a valid SPDX code')
                ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force the update, including manually modified licenses');
    }

    protected function runLogic(InputInterface $input, OutputInterface $output): int
    {
        $filterDocId = $input->getOption('document');
        $filterLicense = $input->getOption('license');
        $newLicense = $input->getOption('new-license');
        $update = $input->getOption('update');
        $rvCode = $input->getOption('rvcode');

        $resolve = (bool)$input->getOption('resolve');
        $isVerbose = (bool)$input->getOption('verbose');
        $isDryRun = (bool)$input->getOption('dry-run');
        $isForced = (bool)$input->getOption('force');

        $options = [
                'document' => $filterDocId,
                'license' => $filterLicense,
                'new-license' => $newLicense,
                'update' => $update,
                'rvcode' => $rvCode,
                'resolve' => $resolve,
                'verbose' => $isVerbose,
                'dry-run' => $isDryRun,
                'force' => $isForced,
        ];


        if ($resolve && $update) { // Exclusive validation
            $this->io->error("The --resolve and --update options are mutually exclusive. Please choose only one.");
            return Command::FAILURE;
        }

        if (!$resolve && !$update) {
            $this->io->error("You must specify either --resolve or --update.");
            return Command::FAILURE;
        }

        $this->showConfiguration($options);

        if ($resolve) {
            if ($isForced || $newLicense) {
                $this->io->warning("Warning: --resolve ignores --new-license and --force options.");
            }
            return $this->resolveLicenses($options);
        }

        if ($update && !$filterLicense) {
            $this->io->error("You must specify --licence option.");
            return Command::FAILURE;
        }


        if ($update && !$newLicense) {
            $this->io->error("You must specify the --new-licence option.");
            return Command::FAILURE;
        }

        if (!$update && $newLicense) {
            $this->io->error("You must specify the --update option.");
            return Command::FAILURE;
        }

        if (!$filterLicense && !$rvCode && !$filterDocId) {
            $this->io->error("You must specify at least one of the following filters: --rvcode, --license, --document.");
            return Command::FAILURE;
        }

        return $this->updateLicences($options);

    }

    private function resolveLicenses(array $options = []): int
    {
        $tableName = T_PAPER_LICENSE_CODE;
        $sqlDump = '';
        $isVerbose = $options['verbose'];
        $isDryRun = $options['dry-run'];

        $result = [];

        $query = $this->buildQuery($options);
        $licenses = $this->db->fetchPairs($query);

        $count = count($licenses);
        $isProgressBarStarted = $count > 0;

        $noAssertion = [];
        $resolvedLicences = [];

        if ($isProgressBarStarted) {

            $this->io->progressStart($count);

            foreach ($licenses as $docId => $url) {

                $spdxResolver = new LicenseSpdxResolver();

                $resolved = $spdxResolver->resolve($url);
                $isResolved = $resolved !== LicenseSpdxResolver::NO_ASSERTION;

                if ($isResolved) {
                    $resolvedLicences[$resolved][] = $docId;
                    $sqlDump .= "INSERT INTO `$tableName` (`docid`, `code`) VALUES ($docId, '$resolved') ON DUPLICATE KEY UPDATE `code`= '$resolved';";
                    $sqlDump .= PHP_EOL;
                } else {
                    $noAssertion[$url][] = $docId;
                }

                $result[] = [
                        $docId,
                        $url,
                        $isResolved ? sprintf('%s%s.html', LicenseSpdxResolver::SPDX_LICENSE_LIST_URL, $resolved) : LicenseSpdxResolver::NO_ASSERTION
                ];

                $this->io->progressAdvance();

            }

        }

        if ($isVerbose) {
            $this->io->info('The relevant documents:');
            $this->io->info('Building query: ' . $query->__toString());

            if ($sqlDump === '') {
                $this->io->info('The SQL script for normalization is empty: there is no match with the SPDX standard.');
            }
        }

        $res = $this->applyTransaction($isDryRun, $sqlDump);

        if ($isProgressBarStarted) {
            $this->io->progressFinish();
        }

        if ($isDryRun || $isVerbose) {

            $pageSize = self::IO_TABLE_ITEM_PER_PAGE;
            $totalLines = count($result);
            $maxPage = $totalLines > 0 ? (int)ceil($totalLines / $pageSize) : 0;

            if ($maxPage === 0) {
                $this->io->success("No results found; it is likely that all licenses have an SPDX match.");
                return Command::SUCCESS;
            }

            $page = 1;

            $headers = ['DOCID', 'PREVIOUS_LICENSE_URL', 'RESOLVED LICENSE (SPDX_LICENSE_URL)'];

            while ($page <= $maxPage) {
                $startIndex = ($page - 1) * $pageSize;
                $countToFetch = min($pageSize, $totalLines - $startIndex);
                $currentRows = array_slice($result, $startIndex, $countToFetch);

                if (empty($currentRows)) {
                    $this->io->warning("There is no data to display on this page.");
                    break;
                }

                $this->showTable($headers, $currentRows);
                $to = min($pageSize * $page, $totalLines);

                $this->io->writeln(sprintf(
                        "\n<comment>[Page %d of %d]</comment> | Lines %d to %d of %d",
                        $page,
                        $maxPage,
                        $startIndex + 1,
                        $to,
                        $totalLines
                ));

                if ($page < $maxPage) {

                    $answer = $this->io->ask(
                            '<info>Type "n" for Next or "q" for Quit</info>',
                            'n', // Valeur par défaut
                            function ($value) {
                                $value = strtolower(trim($value));
                                if (!in_array($value, ['n', 'q'])) {
                                    throw new \InvalidArgumentException("Invalid input. Type 'n' or 'q'.");
                                }
                                return $value;
                            }
                    );

                    if ($answer === 'q') {
                        $this->io->newLine();
                        $this->io->success("User-initiated shutdown.");
                        break; // Sortie propre
                    }

                    $page++;
                } else {
                    $this->io->newLine();
                    $this->io->success("End of results.");

                    $restart = $this->io->confirm("Would you like to start again from the first page?", false);
                    if ($restart) {
                        $page = 1;
                        // A short delay to prevent rapid flickering
                        usleep(500000);
                        continue;
                    }
                    break;
                }
            }

        }

        if ($res === Command::SUCCESS) {
            $this->io->success('The standardization process is complete.');
            $this->standardizationAudit(array_keys($resolvedLicences), array_keys($noAssertion));
        }

        return $res;
    }

    /**
     * Command configuration
     * @param array $options
     * @return void
     */

    private function showConfiguration(array $options = []): void
    {

        $allTableStr = 'ALL TABLE';
        $isAll = $options['all'] ?? false;

        if (isset($options['where'])) {
            $target = isset($options['all']) ? $allTableStr : ("Condition");
        } elseif (isset($options['license'])) {
            $target = $isAll ? $allTableStr : (("Code"));
        } else {
            $target = $isAll ? $allTableStr : (("ID Doc"));
        }

        $this->showTable(
                ['Parameter', 'Value'],
                [
                        ['Environment', $this->env],
                        ['Action', $options['update'] ? 'Licenses updating' : 'SPDX standardisation'],
                        ['Verbose', isset($options['verbose']) && $options['verbose'] ? 'YES' : 'NO'],
                        ['Mode Dry Run', isset($options['dry-run']) && $options['dry-run'] ? 'YES' : 'NO'],
                        ['New License', $options['new-license'] ?? 'NONE'],
                        ['Target', $target],
                        ['Log File', basename($this->logFile)],
                ]
        );
    }

    private function buildQuery(array $options = [], string $action = 'resolve'): ?Zend_Db_Select
    {
        $docId = isset($options['document']) ? (int)$options['document'] : null;
        $rvCode = isset($options['rvcode']) ? strtolower(trim($options['rvcode'])) : null;
        $licenseFilter = isset($options['license']) ? trim($options['license']) : null;

        $isForced = isset($options['force']) && $options['force'];

        $selectedCols = $action === 'update' ? ['pl.docid'] : ['pl.docid', 'pl.licence'];

        $query = $this->db
                ?->select()
                ->from(['pl' => T_PAPER_LICENCES], $selectedCols);


        $query->joinLeft(
                ['plc' => T_PAPER_LICENSE_CODE],
                'plc.docid = pl.docid',
                []
        );

        if ($action === 'resolve') {
            $query->where('plc.docid IS NULL');  // to retrieve only those records from table T_PAPER_LICENCES that have no match in tableT_PAPER_LICENSE_CODE
        } elseif ($action === 'update' && !$isForced) {
            $query->where('pl.uid IS NULL'); // so as not to overwrite a manual change
        }

        if ($docId) {
            $query->where('pl.docid = ?', $docId);

        } else {

            if ($rvCode) {
                $query->join(
                        ['p' => T_PAPERS],
                        'p.DOCID = pl.docid',
                        ['p.RVID'],
                );

                $query->join(
                        ['r' => T_REVIEW],
                        'r.rvid = p.RVID',
                        ['r.CODE'],

                );

                $query->where('r.code = ?', $rvCode);

            }

            if ($licenseFilter) {
                $query->where('pl.licence = ?', $licenseFilter);

                if ($action === 'update') {
                    $query->orWhere('plc.code = ?', $licenseFilter);
                }

            } else {
                $query->order('pl.licence ASC');
            }

        }

        return $query;
    }

    private function updateLicences(array $options = []): int
    {
        $tableName = T_PAPER_LICENSE_CODE;
        $sqlDump = '';
        $isVerbose = $options['verbose'];
        $isDryRun = $options['dry-run'];

        $spdxResolver = new LicenseSpdxResolver();

        $newLicense = $options['new-license'];

        if (!$spdxResolver->isValid($newLicense)) {
            $this->io->error(sprintf('The license code [%s] is invalid', $newLicense));
            $this->io->info(sprintf('@see %s to select a valid Identifier', LicenseSpdxResolver::SPDX_LICENSE_LIST_URL));
            return Command::FAILURE;
        }

        if ($newLicense === $options['license']) {
            $this->io->warning(sprintf('Nothing to update: the new licence [%s] is identical to the previous one.', $newLicense));
            return Command::FAILURE;
        }

        $query = $this->buildQuery($options, 'update');


        if ($isVerbose) {
            $this->io->info('The relevant documents:');
            $this->io->info($query->__toString());
        }

        $docIds = $this->db->fetchCol($query);

        if (empty($docIds)) {
            $this->io->success("Nothing to update.");
            return Command::SUCCESS;
        }

        $count = count($docIds);
        $isProgressBarStarted = $count > 0;

        if ($isProgressBarStarted) {
            $this->io->progressStart($count);
            foreach ($docIds as $docId) {
                $sqlDump .= "INSERT INTO `$tableName` (`docid`, `code`) VALUES ($docId, '$newLicense') ON DUPLICATE KEY UPDATE `code`= '$newLicense';";
                $sqlDump .= PHP_EOL;
                $this->io->progressAdvance();
            }
        }

        $res = $this->applyTransaction($isDryRun, $sqlDump);

        if ($isProgressBarStarted) {
            $this->io->progressFinish();
        }

        if ($res === Command::SUCCESS) {
            $this->io->success('The Update process is complete');
            $this->standardizationAudit($this->fetchLicensesFromPaperLicenceCode(), $this->fetchLicensesNoYetNormalized());
        }

        return $res;

    }


    private function applyTransaction(bool $isDryRun = false, string $sql = ''): ?int
    {

        if ($isDryRun || $sql === '') {
            return Command::SUCCESS;
        }

        try {
            $this->db->beginTransaction();
            $this->db->query($sql);
            $this->db->commit();
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $message = 'Transaction failed: ' . $e->getMessage();
            $this->db->rollBack();
            $this->logger->error($message);
            $this->io->error($message);
            return Command::FAILURE;
        }
    }

    private function standardizationAudit(array $licensesResolved, array $noAssertion): void
    {
        $this->io->writeln('List of Licenses Available After Standardization:');
        $this->showTable(['Licenses'], array_map(static fn($key) => [$key], $licensesResolved));

        if (!empty($noAssertion)) {
            $this->io->writeln('Licences identified with no SPDX match:');
            $this->showTable(['No SPDX Match'], array_map(static fn($key) => [$key], $noAssertion));

        } else {
            $this->io->writeln('<comment>All licenses have an SPDX match.</comment>');
        }
    }

    private function fetchLicensesFromPaperLicenceCode(): array
    {

        $query = $this->db
                ?->select()
                ->distinct()
                ->from(['pc' => T_PAPER_LICENSE_CODE], ['pc.code']);

        return $this->db->fetchCol($query);

    }

    private function fetchLicensesNoYetNormalized(): array
    {

        $query = $this->db
                ?->select()
                ->distinct()
                ->from(['pl' => T_PAPER_LICENCES], ['pl.licence']);

        $query->joinLeft(
                ['plc' => T_PAPER_LICENSE_CODE],
                'plc.docid = pl.docid',
                []
        );

        $query->where('plc.docid IS NULL');

        return $this->db->fetchCol($query);

    }
}