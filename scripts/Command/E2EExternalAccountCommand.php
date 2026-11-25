<?php

declare(strict_types=1);

namespace scripts\Command;

use Ccsd_User_Models_User;
use Ccsd_User_Models_UserMapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Creates, for the Playwright E2E tests, an account as another application sharing the accounts table would: a row of
 * the accounts table only, without any Episciences data (no profile, no role).
 */
#[AsCommand(name: 'app:e2e-external-account', description: 'Creates an E2E account without any Episciences data, as another application would.')]
class E2EExternalAccountCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'Login of the account (must not exist yet)')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'E-mail address of the account')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'Password of the account', 'password123')
            ->addOption('inactive', null, InputOption::VALUE_NONE, 'Leave the account not activated');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = (string)$input->getOption('username');
        $email = (string)$input->getOption('email');

        if ($username === '' || $email === '') {
            $io->error('--username and --email are required.');
            return Command::INVALID;
        }

        new Bootstrapper();

        $mapper = new Ccsd_User_Models_UserMapper();

        if ($mapper->usernameExists($username)) {
            $io->error(sprintf('The login "%s" is already used.', $username));
            return Command::FAILURE;
        }

        $account = new Ccsd_User_Models_User([
            'USERNAME' => $username,
            'EMAIL' => $email,
            'FIRSTNAME' => 'E2E',
            'LASTNAME' => 'External',
            'PASSWORD' => (string)$input->getOption('password'),
            'VALID' => $input->getOption('inactive') ? 0 : 1,
        ]);
        $account->setTime_registered();
        $account->setTime_modified();

        $uid = $mapper->save($account);

        $io->success(sprintf('Account "%s" created (UID %d), %s.', $username, $uid, $input->getOption('inactive') ? 'not activated' : 'active'));

        return Command::SUCCESS;
    }
}
