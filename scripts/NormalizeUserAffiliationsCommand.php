<?php
declare(strict_types=1);

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: normalize legacy `affiliations` entries stored as raw
 * "Label #rorId" strings (bug in UserDefaultController::createAction(), fixed by
 * routing new-account affiliations through the same disassemble step used on profile edits)
 * back into the canonical `{"label": ..., "rorId": ...}` object shape used everywhere else.
 */
class NormalizeUserAffiliationsCommand extends Command
{
    protected static $defaultName = 'users:normalize-affiliations';

    public const DEFAULT_BUFFER = 500;

    private const SEPARATOR = '#';

    protected function configure(): void
    {
        $this
            ->setDescription('Normalize legacy string-based USER.affiliations entries into {label, rorId} objects.')
            ->addOption('uid', null, InputOption::VALUE_REQUIRED, 'Process only this UID.')
            ->addOption('buffer', null, InputOption::VALUE_REQUIRED, 'Number of users to process per page.', self::DEFAULT_BUFFER)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change without writing to the database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Normalizing USER.affiliations to {label, rorId} objects');

        $isDryRun = (bool) $input->getOption('dry-run');
        $uid      = $input->getOption('uid');
        $buffer   = $this->validateBuffer($input->getOption('buffer'));

        $this->bootstrap();

        $db = \Zend_Db_Table_Abstract::getDefaultAdapter();

        if ($db === null) {
            $io->error('Database adapter not initialized.');
            return Command::FAILURE;
        }

        $countQuery = $db->select()->from(T_USERS, [new \Zend_Db_Expr('COUNT(*)')])
            ->where('ADDITIONAL_PROFILE_INFORMATION LIKE ?', '%affiliations%');
        $dataQuery = $db->select()->from(T_USERS, ['UID', 'ADDITIONAL_PROFILE_INFORMATION'])
            ->where('ADDITIONAL_PROFILE_INFORMATION LIKE ?', '%affiliations%')
            ->order('UID ASC');

        if ($uid !== null) {
            $countQuery->where('UID = ?', (int) $uid);
            $dataQuery->where('UID = ?', (int) $uid);
        }

        $count = (int) $db->fetchOne($countQuery);

        if ($count === 0) {
            $io->success('No user affiliations to inspect.');
            return Command::SUCCESS;
        }

        $paginator = \Zend_Paginator::factory($dataQuery);
        $paginator->setItemCountPerPage($buffer);
        $totalPages = $paginator->count();

        $inspected = 0;
        $fixed     = 0;
        $failures  = 0;

        $io->progressStart($count);

        for ($page = 1; $page <= $totalPages; $page++) {
            $paginator->setCurrentPageNumber($page);

            $updates = [];

            foreach ($paginator->getCurrentItems() as $row) {
                $inspected++;
                $io->progressAdvance();

                try {
                    $normalizedJson = $this->normalizeAffiliationsJson((string) $row['ADDITIONAL_PROFILE_INFORMATION']);
                } catch (\JsonException $e) {
                    $io->writeln('');
                    $io->warning(sprintf('[UID %d] Invalid JSON, skipped: %s', (int) $row['UID'], $e->getMessage()));
                    $failures++;
                    continue;
                }

                if ($normalizedJson === null) {
                    continue;
                }

                $fixed++;
                $updates[(int) $row['UID']] = $normalizedJson;
            }

            if (empty($updates)) {
                continue;
            }

            if ($isDryRun) {
                continue;
            }

            try {
                $db->beginTransaction();

                foreach ($updates as $rowUid => $json) {
                    $db->update(T_USERS, ['ADDITIONAL_PROFILE_INFORMATION' => $json], ['UID = ?' => $rowUid]);
                }

                $db->commit();
            } catch (\Exception $e) {
                $db->rollBack();
                $io->writeln('');
                $io->error(sprintf('Page %d: transaction failed: %s', $page, $e->getMessage()));
                $failures += count($updates);
                $fixed -= count($updates);
            }
        }

        $io->progressFinish();

        $summary = sprintf(
            'Inspected: %d | Normalized: %d | Failures: %d%s',
            $inspected,
            $fixed,
            $failures,
            $isDryRun ? ' (dry-run, nothing written)' : ''
        );

        if ($failures > 0) {
            $io->warning($summary);
            return Command::FAILURE;
        }

        $io->success($summary);
        return Command::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Public pure helpers — testable without bootstrap or DB
    // -------------------------------------------------------------------------

    /**
     * Normalize the `affiliations` entry of a USER.ADDITIONAL_PROFILE_INFORMATION JSON blob.
     * Returns the re-encoded JSON when a change was needed, or null when the row is
     * already in the canonical {label, rorId} shape (or has no affiliations at all).
     *
     * @throws \JsonException
     */
    public function normalizeAffiliationsJson(string $json): ?string
    {
        if (trim($json) === '') {
            return null;
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data) || empty($data['affiliations']) || !is_array($data['affiliations'])) {
            return null;
        }

        $needsNormalization = false;

        foreach ($data['affiliations'] as $affiliation) {
            if (!is_array($affiliation) || !isset($affiliation['label'], $affiliation['rorId'])) {
                $needsNormalization = true;
                break;
            }
        }

        if (!$needsNormalization) {
            return null;
        }

        $data['affiliations'] = array_map([$this, 'disassembleAffiliationEntry'], $data['affiliations']);

        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    /**
     * Mirrors UserDefaultController::disassembleAffiliation(): turns a raw "Label #rorId"
     * string into a {label, rorId} array; leaves an already-correct array untouched.
     *
     * @return array{label: string, rorId: string}
     */
    public function disassembleAffiliationEntry(mixed $value): array
    {
        if (is_array($value) && isset($value['label'], $value['rorId'])) {
            return $value;
        }

        if (is_string($value)) {
            [$label, $rorId] = array_pad(explode(self::SEPARATOR, $value), 2, '');
            $label = trim($label);
            $rorId = trim($rorId);

            if (!\Episciences_Tools::isRorIdentifier($rorId)) {
                $rorId = '';
            }

            return ['label' => $label, 'rorId' => $rorId];
        }

        return ['label' => '', 'rorId' => ''];
    }

    /**
     * Normalise the --buffer option value.
     * Returns DEFAULT_BUFFER when the value is missing, zero, negative, or non-integer.
     */
    public function validateBuffer(mixed $value): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);

        if ($int === false || $int <= 0) {
            return self::DEFAULT_BUFFER;
        }

        return $int;
    }

    // -------------------------------------------------------------------------
    // Private helpers
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

        $application = new \Zend_Application('production', APPLICATION_PATH . '/configs/application.ini');

        $autoloader = \Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);

        $db = \Zend_Db::factory('PDO_MYSQL', $application->getOption('resources')['db']['params']);
        \Zend_Db_Table::setDefaultAdapter($db);
    }
}