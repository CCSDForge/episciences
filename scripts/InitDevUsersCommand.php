<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Faker\Factory;

/**
 * Modern Symfony Command to initialize the 'dev' journal with a specific distribution of 30 users.
 */
class InitDevUsersCommand extends Command
{
    protected static $defaultName = 'app:init-dev-users';

    protected function configure(): void
    {
        $this->setDescription('Initializes the dev journal (RVID 1) with 30 users (1 chief, 2 admins, 5 editors, 22 members)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("Initializing 30 Test Users for Journal 'dev' (RVID 1)");

        // Bootstrap Episciences environment
        if (!defined('APPLICATION_PATH')) {
            define('APPLICATION_PATH', realpath(__DIR__ . '/../application'));
        }
        require_once __DIR__ . '/../public/const.php';
        require_once __DIR__ . '/../public/bdd_const.php';

        // Initialize Constants for the specific journal BEFORE bootstrapping
        // This is necessary because Bootstrap::_initModule() depends on APPLICATION_MODULE
        defineProtocol();
        defineSimpleConstants();
        defineSQLTableConstants();
        defineApplicationConstants();
        defineJournalConstants('dev');

        $libraries = [realpath(APPLICATION_PATH . '/../library')];
        set_include_path(implode(PATH_SEPARATOR, array_merge($libraries, [get_include_path()])));
        require_once 'Zend/Application.php';
        $application = new Zend_Application('development', APPLICATION_PATH . '/configs/application.ini');

        // Initialize Locale manually as it's required by models but not in application.ini
        Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));

        $application->bootstrap();

        $rvid = 1;

        $distribution = [
            Episciences_Acl::ROLE_CHIEF_EDITOR => 1,
            Episciences_Acl::ROLE_ADMIN => 2,
            Episciences_Acl::ROLE_EDITOR => 5,
            Episciences_Acl::ROLE_MEMBER => 22
        ];

        $faker = Factory::create();
        $password = 'password123';
        $totalCreated = 0;
        $createdUsers = [];

        foreach ($distribution as $role => $count) {
            $io->section("Creating $count users with role: $role");
            $io->progressStart($count);

            for ($i = 0; $i < $count; $i++) {
                try {
                    $firstname = $faker->firstName;
                    $lastname = $faker->lastName;
                    $username = strtolower($firstname . '.' . $lastname . $faker->numberBetween(10, 99));
                    $email = $faker->unique()->safeEmail;

                    $user = new Episciences_User();
                    $user->setUsername($username);
                    $user->setEmail($email);
                    $user->setFirstname($firstname);
                    $user->setLastname($lastname);
                    $user->setPassword($password);
                    $user->setValid(1);
                    $user->setIs_valid(1);

                    // Save creates user in BOTH cas_users and episciences DBs.
                    // When using the MySQL CAS adapter (dev only), save() may return 0 because
                    // lastInsertId() is not updated for explicit-UID inserts. The UID was correctly
                    // set on the object via setUid() inside save(); fall back to it.
                    $user->save(false, true, RVID);
                    $uid = $user->getUid();

                    if ($uid) {
                        if ($role !== Episciences_Acl::ROLE_MEMBER) {
                            $user->addRole($role, RVID);
                        }
                        $totalCreated++;
                        $createdUsers[] = [$username, $role, $email];
                    }
                } catch (\Exception $e) {
                    $io->error("Error creating user: " . $e->getMessage());
                }
                $io->progressAdvance();
            }
            $io->progressFinish();
        }

        $io->table(['Username', 'Role', 'Email'], $createdUsers);
        $io->success("Successfully initialized $totalCreated users for journal 'dev'. All passwords are: $password");

        return Command::SUCCESS;
    }
}
