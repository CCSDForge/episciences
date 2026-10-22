<?php
declare(strict_types=1);

use Episciences\Journal\Provisioning\ClonableWebsiteHeader;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command: regenerate the layout/header.<lang>.html files of the journals
 * from the WEBSITE_HEADER table.
 *
 * The layout serves these files as is. They are only rewritten when a journal saves its header,
 * so files generated before the header output was escaped keep their old content until this
 * command runs.
 *
 * Journals are handled without the RVID / REVIEW_PATH constants, which can only be defined once
 * per process: each journal gets its own header instance built from its code and RVID.
 */
#[AsCommand(name: 'website:regenerate-headers', description: 'Regenerate the website header files of one journal or all journals from WEBSITE_HEADER.')]
class RegenerateWebsiteHeadersCommand extends Command
{
    /** No header file for this language: not created, since the layout shows no header today */
    public const STATUS_MISSING = 'missing';
    public const STATUS_UPDATED = 'updated';
    public const STATUS_UNCHANGED = 'unchanged';
    /** A header file exists but the journal has no WEBSITE_HEADER row: left untouched */
    public const STATUS_ORPHAN = 'orphan';
    /** A header file exists for a language the journal does not offer: left untouched */
    public const STATUS_STALE = 'stale';

    protected function configure(): void
    {
        $this
            ->addOption('rvcode', null, InputOption::VALUE_REQUIRED, 'The RV code of the journal — mutually exclusive with --all.')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Process every journal of the REVIEW table, whatever its status — mutually exclusive with --rvcode.')
            ->addOption('protocol', null, InputOption::VALUE_REQUIRED, 'Protocol of the journal URLs used for the logo images (https or http).', 'https')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report the files that would change without writing them.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rvcode = $input->getOption('rvcode');
        $all = (bool)$input->getOption('all');
        $protocol = (string)$input->getOption('protocol');
        $dryRun = (bool)$input->getOption('dry-run');

        if ($rvcode && $all) {
            $io->error('--rvcode and --all are mutually exclusive.');
            return Command::FAILURE;
        }

        if (!$rvcode && !$all) {
            $io->error('Specify either --rvcode=CODE or --all.');
            return Command::FAILURE;
        }

        if (!in_array($protocol, ['https', 'http'], true)) {
            $io->error('--protocol must be https or http.');
            return Command::FAILURE;
        }

        $io->title('Website header regeneration');
        if ($dryRun) {
            $io->note('Dry-run mode enabled — no file will be written.');
        }

        $this->bootstrap($protocol);

        $logger = new Logger('websiteHeaders');
        $logger->pushHandler(new StreamHandler(
            EPISCIENCES_LOG_PATH . 'websiteHeaders_' . date('Y-m-d') . '.log', Level::Info
        ));
        if (!$io->isQuiet() && !$io->isSilent()) {
            $logger->pushHandler(new StreamHandler('php://stdout', Level::Info));
        }

        $journals = $this->fetchJournals($all ? null : (string)$rvcode);
        if (empty($journals)) {
            $io->error($all ? 'No journal found.' : "Journal '{$rvcode}' not found.");
            return Command::FAILURE;
        }

        $counts = array_fill_keys(
            [self::STATUS_UPDATED, self::STATUS_MISSING, self::STATUS_UNCHANGED, self::STATUS_ORPHAN, self::STATUS_STALE],
            0
        );
        $toReview = [];
        $failures = [];

        foreach ($journals as $rvid => $code) {
            $journalDir = dirname(APPLICATION_PATH) . '/data/' . $code . '/';
            if (!is_dir($journalDir)) {
                $logger->info("[{$code}] No data directory, skipped.");
                continue;
            }

            try {
                $header = new ClonableWebsiteHeader(
                    $rvid,
                    $journalDir . 'public/',
                    $protocol . '://' . $code . '.' . DOMAIN . '/public/',
                    $journalDir . 'layout/'
                );
                $header->load();

                $results = $this->syncHeaderFiles($header, $journalDir . 'layout/', $this->fetchLanguages($rvid), $dryRun);
            } catch (\Throwable $e) {
                $logger->error("[{$code}] Header regeneration failed: " . $e->getMessage());
                $failures[] = $code;
                continue;
            }

            foreach ($results as $file => $status) {
                $counts[$status]++;
                if ($status === self::STATUS_UNCHANGED) {
                    continue;
                }
                $verb = $dryRun && $status === self::STATUS_UPDATED ? 'would be ' : '';
                $logger->info("[{$code}] {$file}: {$verb}{$status}");
                if (in_array($status, [self::STATUS_ORPHAN, self::STATUS_STALE], true)) {
                    $toReview[] = $code . '/layout/' . $file;
                }
            }
        }

        $io->table(['Status', 'Files'], array_map(null, array_keys($counts), array_values($counts)));

        if (!empty($toReview)) {
            $io->warning(
                "These files are not generated from WEBSITE_HEADER and were left untouched; check their content:\n- "
                . implode("\n- ", $toReview)
            );
        }

        if (!empty($failures)) {
            $io->warning('Failed journals: ' . implode(', ', $failures));
            return Command::FAILURE;
        }

        $io->success($dryRun ? 'Dry run completed.' : 'Header regeneration completed.');
        return Command::SUCCESS;
    }

    /**
     * Rewrite the existing header file of each language when its content changes.
     *
     * @param string[] $languages
     * @return array<string, string> status of each header file, keyed by file name
     */
    public function syncHeaderFiles(Ccsd_Website_Header $header, string $layoutDir, array $languages, bool $dryRun): array
    {
        $results = [];
        $hasLogos = !empty($header->_logos);

        foreach ($languages as $lang) {
            $file = 'header.' . $lang . '.html';
            $path = $layoutDir . $file;
            if (!is_file($path)) {
                if ($hasLogos) {
                    $results[$file] = self::STATUS_MISSING;
                }
                continue;
            }
            $current = file_get_contents($path);

            if (!$hasLogos) {
                // Never overwrite a file whose origin is unknown with an empty header
                if (trim((string)$current) !== '') {
                    $results[$file] = self::STATUS_ORPHAN;
                }
                continue;
            }

            $content = $header->renderHeader($lang);
            if ($current === $content) {
                $results[$file] = self::STATUS_UNCHANGED;
                continue;
            }

            if (!$dryRun && file_put_contents($path, $content, LOCK_EX) === false) {
                throw new \RuntimeException(sprintf('Unable to write "%s"', $path));
            }
            $results[$file] = self::STATUS_UPDATED;
        }

        foreach (glob($layoutDir . 'header.*.html') ?: [] as $path) {
            $file = basename($path);
            if (!isset($results[$file]) && !in_array(substr($file, 7, -5), $languages, true)) {
                $results[$file] = self::STATUS_STALE;
            }
        }

        return $results;
    }

    /**
     * Return the journals to process, as code keyed by RVID.
     *
     * @return array<int, string>
     */
    private function fetchJournals(?string $rvcode): array
    {
        $db = \Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()->from('REVIEW', ['RVID', 'CODE'])->order('CODE');
        if ($rvcode !== null) {
            $select->where('CODE = ?', $rvcode);
        }

        return array_map('strval', $db->fetchPairs($select));
    }

    /**
     * Interface languages of a journal, with the same fallback as the web application.
     *
     * @return string[]
     */
    private function fetchLanguages(int $rvid): array
    {
        $languages = (new \Ccsd_Website_Common($rvid, ['sidField' => 'SID']))->getLanguages();

        return count($languages) === 0 ? \Episciences_Translation_Plugin::getAvailableLanguages() : array_values($languages);
    }

    private function bootstrap(string $protocol): void
    {
        if (!defined('APPLICATION_PATH')) {
            define('APPLICATION_PATH', realpath(__DIR__ . '/../application'));
        }
        require_once __DIR__ . '/../public/const.php';
        require_once __DIR__ . '/../public/bdd_const.php';

        // From the CLI, defineProtocol() would fall back to http
        defined('SERVER_PROTOCOL') || define('SERVER_PROTOCOL', $protocol);
        defineProtocol();
        defineSimpleConstants();
        defineSQLTableConstants();
        defineApplicationConstants();

        // Deliberately NOT calling defineJournalConstants(): several journals are handled in one process

        $libraries = [realpath(APPLICATION_PATH . '/../library')];
        set_include_path(implode(PATH_SEPARATOR, array_merge($libraries, [get_include_path()])));
        require_once 'Zend/Application.php';

        $application = new Zend_Application('production', APPLICATION_PATH . '/configs/application.ini');

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);

        $db = Zend_Db::factory('PDO_MYSQL', $application->getOption('resources')['db']['params']);
        Zend_Db_Table::setDefaultAdapter($db);

        Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));
    }
}
