<?php

declare(strict_types=1);

use Episciences\User\Token\AccountTokenRepository;
use scripts\Command\Bootstrapper;
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

    /**
     * @throws Zend_Application_Exception
     * @throws Zend_Db_Exception
     */
    protected function bootstrap(): void
    {
        new Bootstrapper();
    }
}
