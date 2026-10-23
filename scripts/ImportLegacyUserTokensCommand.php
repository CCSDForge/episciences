<?php

declare(strict_types=1);

use Episciences\User\Token\AccountTokenRepository;
use Episciences\User\Token\LegacyTokenImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One-off: imports the account links sent before tokens were stored in the application database, so that recent
 * activation and password links keep working. Run once, right after deploying the user_token table.
 * The CAS table is only read (it is shared with other applications).
 */
#[AsCommand(name: 'user:import-legacy-tokens', description: 'One-off import of the still valid account links from the CAS token table')]
class ImportLegacyUserTokensCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count the tokens to import without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Import legacy account link tokens');

        $dryRun = (bool)$input->getOption('dry-run');

        if ($dryRun) {
            $io->note('Dry run: nothing is written.');
        }

        $this->bootstrap();

        $result = $this->getImporter()->import($dryRun);

        $io->success(sprintf(
            '%d legacy token(s) read, %d %s, %d skipped (not an Episciences account, expired or already imported).',
            $result['candidates'],
            $result['imported'],
            $dryRun ? 'to import' : 'imported',
            $result['skipped']
        ));

        return Command::SUCCESS;
    }

    protected function getImporter(): LegacyTokenImporter
    {
        return new LegacyTokenImporter(
            Ccsd_Db_Adapter_Cas::getAdapter(),
            Zend_Db_Table_Abstract::getDefaultAdapter(),
            new AccountTokenRepository(),
            T_USERS
        );
    }

    protected function bootstrap(): void
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
