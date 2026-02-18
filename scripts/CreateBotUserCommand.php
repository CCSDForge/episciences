<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Modern Symfony Command to create a fixed 'episciences-bot' user.
 */
class CreateBotUserCommand extends Command
{
    protected static $defaultName = 'app:create-bot-user';

    protected function configure(): void
    {
        $this->setDescription('Creates a fixed episciences-bot user with a predefined UID and credentials.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("Creating episciences-bot user");

        // Bootstrap Episciences environment
        if (!defined('APPLICATION_PATH')) {
            define('APPLICATION_PATH', realpath(__DIR__ . '/../application'));
        }
        require_once __DIR__ . '/../public/const.php';
        require_once __DIR__ . '/../public/bdd_const.php';

        defineProtocol();
        defineSimpleConstants();
        defineSQLTableConstants();
        defineApplicationConstants();
        defineJournalConstants('dev'); // Assuming 'dev' journal context for initial setup

        $libraries = [realpath(APPLICATION_PATH . '/../library')];
        set_include_path(implode(PATH_SEPARATOR, array_merge($libraries, [get_include_path()])));
        require_once 'Zend/Application.php';
        $application = new Zend_Application('development', APPLICATION_PATH . '/configs/application.ini');
        $application->bootstrap();

        Zend_Registry::set('Zend_Locale', new \Zend_Locale('en'));

        $botUid = EPISCIENCES_UID; // Use configured UID for the bot
        $botUsername = 'episciences-bot';
        $botEmail = 'bot@episciences.org';
        $botPassword = 'botPassword123';
        $botRole = Episciences_Acl::ROLE_MEMBER; // Assign member role
        $rvid = 1; // Assuming the 'dev' journal has RVID 1

        try {
            $user = new Episciences_User();
            // Check if user with this UID already exists in Episciences DB
            $existingUser = $user->find($botUid);

            // Check if user with this username already exists in CAS DB
            $ccsdUserMapper = new Ccsd_User_Models_UserMapper();
            $existingCasUser = $ccsdUserMapper->findByUsername($botUsername);

            if (!empty($existingUser) || count($existingCasUser) > 0) {
                $io->warning("User '$botUsername' (UID: $botUid) already exists. Skipping creation.");
                return Command::SUCCESS;
            }

            $user->setUid($botUid); // Set fixed UID
            $user->setUsername($botUsername);
            $user->setEmail($botEmail);
            $user->setFirstname('Episciences');
            $user->setLastname('Bot');
            $user->setPassword($botPassword);
            $user->setValid(1);
            $user->setIs_valid(1);

            // Save user (creates in both databases)
            $saveResult = $user->save(true, true, $rvid); // forceInsert=true

            if ($saveResult !== false) {
                $uid = $user->getUid();
                // Assign role (explicitly, as library save() might have used UID 0 for default role)
                $user->addRole($botRole, $rvid);
                $io->success("Successfully created bot user: $botUsername (UID: $uid) with password: $botPassword and role: $botRole");
            } else {
                $io->error("Failed to create bot user: $botUsername");
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error("Error creating bot user: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
