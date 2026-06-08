<?php
declare(strict_types=1);

use Episciences\Next\RevalidationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Immediately trigger Next.js cache revalidation for a specific journal and tag.
 * Bypasses the queue for manual/urgent use.
 *
 * Usage: php scripts/console.php next:revalidate-cache epijinfo article-42
 */
class RevalidateNextCacheCommand extends Command
{
    protected static $defaultName = 'next:revalidate-cache';

    private const HTTP_TIMEOUT = 5.0;

    protected function configure(): void
    {
        $this
            ->setDescription('Immediately trigger Next.js cache revalidation for a specific journal and tag (bypasses queue).')
            ->addArgument('rvcode', InputArgument::REQUIRED, 'Journal code (e.g. epijinfo)')
            ->addArgument('tag',    InputArgument::REQUIRED, 'Cache tag to invalidate (e.g. article-42)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $rvcode = (string) $input->getArgument('rvcode');
        $tag    = (string) $input->getArgument('tag');

        $this->bootstrap();

        if (!defined('NEXT_BASE_URL') || NEXT_BASE_URL === '') {
            $io->error('NEXT_BASE_URL is not defined in config/pwd.json.');
            return Command::FAILURE;
        }

        $io->text("Revalidating tag <info>{$tag}</info> for journal <info>{$rvcode}</info>");
        $io->text('Endpoint: ' . rtrim(NEXT_BASE_URL, '/') . '/api/revalidate');

        $status = RevalidationService::postRevalidation($rvcode, $tag, self::HTTP_TIMEOUT);

        if ($status === 200) {
            $io->success('Revalidation succeeded.');
            return Command::SUCCESS;
        }

        $statusLabel = $status > 0 ? "HTTP {$status}" : 'network/timeout error';
        $io->warning("Non-200 response: {$statusLabel} (see error log for details)");
        return Command::FAILURE;
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

        $libraries = [realpath(APPLICATION_PATH . '/../library')];
        set_include_path(implode(PATH_SEPARATOR, array_merge($libraries, [get_include_path()])));
        require_once 'Zend/Application.php';

        $application = new Zend_Application('production', APPLICATION_PATH . '/configs/application.ini');

        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->setFallbackAutoloader(true);

        $db = Zend_Db::factory('PDO_MYSQL', $application->getOption('resources')['db']['params']);
        Zend_Db_Table::setDefaultAdapter($db);
    }
}
