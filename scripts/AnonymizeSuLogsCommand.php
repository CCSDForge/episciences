<?php

declare(strict_types=1);

use Episciences\User\SuLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command to anonymize IP addresses in user_su_log records
 * older than a retention threshold (default 365 days / 1 year).
 *
 * Supports IPv4 (/16 mask, e.g. 1.1.1.1 -> 1.1.0.0) and IPv6 (/48 mask).
 */
class AnonymizeSuLogsCommand extends Command
{
    protected static $defaultName = 'user:anonymize-su-logs';

    protected function configure(): void
    {
        $this
            ->setDescription('Anonymize IP addresses in user_su_log records older than the retention threshold (default 1 year)')
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Retention threshold in days', '365');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Anonymize Switch User (user_su_log) IP addresses');

        // Validated before anything else: a mistyped value must not anonymize records irreversibly
        $days = filter_var($input->getOption('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($days === false) {
            $io->error('The retention threshold in days must be a positive integer.');
            return Command::FAILURE;
        }

        $this->bootstrap();

        $io->note(sprintf('Processing user_su_log records older than %d days...', $days));

        $count = SuLogger::anonymizeOldLogs($days);

        $io->success(sprintf('Successfully anonymized %d user_su_log record(s).', $count));

        return Command::SUCCESS;
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
        defineSQLTableConstants();
        defineApplicationConstants();
        defineJournalConstants();

        $libraries = [realpath(APPLICATION_PATH . '/../library')];
        set_include_path(implode(PATH_SEPARATOR, array_merge($libraries, [get_include_path()])));
        require_once 'Zend/Application.php';

        $application = new Zend_Application('production', APPLICATION_PATH . '/configs/application.ini');
        $application->bootstrap('db');

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);
    }
}
