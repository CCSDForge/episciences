<?php

declare(strict_types=1);

namespace scripts\Command;

use Episciences_User_Invitation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Zend_Db_Adapter_Exception;
use Zend_Db_Expr;

/**
 * Makes, for the Playwright E2E tests, a pending reviewer invitation expired: its expiration date is moved to the day
 * before. No user action can do it: the expiration date is set when the invitation is sent, in the future.
 */
#[AsCommand(name: 'app:e2e-expire-invitation', description: 'Makes a pending E2E reviewer invitation expired.')]
class E2EExpireInvitationCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('id', null, InputOption::VALUE_REQUIRED, 'Id of the pending invitation (the one of the invitation link)');
    }

    /**
     * @throws Zend_Db_Adapter_Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = filter_var($input->getOption('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($id === false) {
            $io->error('--id must be a positive integer.');
            return Command::INVALID;
        }

        $db = (new Bootstrapper())->getDb();

        if ($db === null) {
            $io->error('No database connection.');
            return Command::FAILURE;
        }

        // Only the pending row is reached through an invitation link: the status rows are left as they are
        $updated = $db->update(
            T_USER_INVITATIONS,
            ['EXPIRATION_DATE' => new Zend_Db_Expr('DATE_SUB(NOW(), INTERVAL 1 DAY)')],
            ['ID = ?' => $id, 'STATUS = ?' => Episciences_User_Invitation::STATUS_PENDING]
        );

        if ($updated !== 1) {
            $io->error(sprintf('No pending invitation with the id %d.', $id));
            return Command::FAILURE;
        }

        $io->success(sprintf('Invitation %d expired.', $id));

        return Command::SUCCESS;
    }
}
