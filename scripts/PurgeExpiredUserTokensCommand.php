<?php

declare(strict_types=1);

use Episciences\User\Token\AccountTokenRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Deletes the expired account link tokens (account activation, password reset, account setup).
 * Expired tokens are already refused: purging them only keeps the user_token table small.
 */
#[AsCommand(name: 'user:purge-expired-tokens', description: 'Delete the expired account link tokens (activation, password reset, account setup)')]
class PurgeExpiredUserTokensCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Purge expired account link tokens');

        $this->bootstrap();

        $count = $this->getRepository()->purgeExpired();

        $io->success(sprintf('Deleted %d expired token(s).', $count));

        return Command::SUCCESS;
    }

    protected function getRepository(): AccountTokenRepository
    {
        return new AccountTokenRepository();
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
