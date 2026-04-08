<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Faker\Factory;

/**
 * Modern Symfony Command to generate test users for Episciences
 */
class GenerateUsersCommand extends Command
{
    protected static $defaultName = 'app:generate-users';

    protected function configure(): void
    {
        $this
            ->setDescription('Generates random test users using Faker')
            ->addOption('count', 'c', InputOption::VALUE_OPTIONAL, 'Number of users to generate', 5)
            ->addOption('role', 'r', InputOption::VALUE_OPTIONAL, 'Role to assign (member, editor, admin, chiefeditor)', 'member')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'Fixed password for all generated users', 'password123')
            ->addOption('rvcode', null, InputOption::VALUE_OPTIONAL, 'Journal code for roles', 'dev');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = (int)$input->getOption('count');
        $roleName = $input->getOption('role');
        $rvcode = $input->getOption('rvcode');
        $password = $input->getOption('password');

        $io->title("Generating $count Test Users");

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
        defineJournalConstants($rvcode);

        // Setup ZF1 Autoloader & Application
        $libraries = [realpath(APPLICATION_PATH . '/../library')];
        set_include_path(implode(PATH_SEPARATOR, array_merge($libraries, [get_include_path()])));
        require_once 'Zend/Application.php';
        $application = new Zend_Application('development', APPLICATION_PATH . '/configs/application.ini');

        // Initialize Locale manually as it's required by models but not in application.ini
        Zend_Registry::set('Zend_Locale', new \Zend_Locale('en'));

        $application->bootstrap();

        $faker = Factory::create();
        $successCount = 0;

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
                // lastInsertId() is not updated for explicit-UID inserts. Fall back to getUid().
                // We use saveNewRoles() instead of addRole() because addRole() calls
                // multipleRolesUnsetMemberRole() which strips the 'member' role before saving.
                $user->save(false, true, RVID);
                $uid = $user->getUid();

                if ($uid) {
                    // Map role string to Acl constant
                    $role = match($roleName) {
                        'admin' => Episciences_Acl::ROLE_ADMIN,
                        'editor' => Episciences_Acl::ROLE_EDITOR,
                        'chiefeditor' => Episciences_Acl::ROLE_CHIEF_EDITOR,
                        default => Episciences_Acl::ROLE_MEMBER,
                    };

                    $user->saveNewRoles($uid, $role, RVID);
                    $successCount++;
                }
            } catch (\Exception $e) {
                $io->error("Error creating user $i: " . $e->getMessage());
            }
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success("Successfully generated $successCount users with password: $password");

        return Command::SUCCESS;
    }
}
