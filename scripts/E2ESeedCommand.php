<?php

declare(strict_types=1);

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command to seed deterministic test users and roles for Playwright E2E tests.
 */
#[AsCommand(name: 'app:e2e-seed', description: 'Seeds deterministic E2E test users with known credentials and roles.')]
class E2ESeedCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('rvcode', null, InputOption::VALUE_OPTIONAL, 'Journal code for roles', 'dev')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'Fixed password for all generated users', 'password123');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rvcode = (string)$input->getOption('rvcode');
        $password = (string)$input->getOption('password');

        $io->title("Seeding Deterministic E2E Test Users for Journal '$rvcode'");

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
        defineJournalConstants($rvcode);

        $libraries = [realpath(APPLICATION_PATH . '/../library')];
        set_include_path(implode(PATH_SEPARATOR, array_merge($libraries, [get_include_path()])));
        require_once 'Zend/Application.php';
        $application = new Zend_Application('development', APPLICATION_PATH . '/configs/application.ini');
        $application->bootstrap();

        Zend_Registry::set('Zend_Locale', new Zend_Locale('en'));

        $rvid = defined('RVID') ? RVID : 1;
        $ccsdUserMapper = new Ccsd_User_Models_UserMapper();

        $testUsers = [
            [
                'username'  => 'e2e_author',
                'email'     => 'e2e_author@example.org',
                'firstname' => 'E2E',
                'lastname'  => 'Author',
                'roles'     => [Episciences_Acl::ROLE_MEMBER],
            ],
            [
                'username'  => 'e2e_reviewer',
                'email'     => 'e2e_reviewer@example.org',
                'firstname' => 'E2E',
                'lastname'  => 'Reviewer',
                'roles'     => [Episciences_Acl::ROLE_MEMBER, Episciences_Acl::ROLE_REVIEWER],
            ],
            [
                'username'  => 'e2e_editor',
                'email'     => 'e2e_editor@example.org',
                'firstname' => 'E2E',
                'lastname'  => 'Editor',
                'roles'     => [Episciences_Acl::ROLE_EDITOR],
            ],
            [
                'username'  => 'e2e_secretary',
                'email'     => 'e2e_secretary@example.org',
                'firstname' => 'E2E',
                'lastname'  => 'Secretary',
                'roles'     => [Episciences_Acl::ROLE_SECRETARY],
            ],
            [
                'username'  => 'e2e_admin',
                'email'     => 'e2e_admin@example.org',
                'firstname' => 'E2E',
                'lastname'  => 'Admin',
                'roles'     => [Episciences_Acl::ROLE_ADMIN, Episciences_Acl::ROLE_CHIEF_EDITOR],
            ],
        ];

        $results = [];

        foreach ($testUsers as $userData) {
            $username = $userData['username'];
            $email = $userData['email'];
            $firstname = $userData['firstname'];
            $lastname = $userData['lastname'];
            $roles = $userData['roles'];

            try {
                $existingCas = $ccsdUserMapper->findByUsernameIncludingInactive($username);

                if (!empty($existingCas) && isset($existingCas['UID'])) {
                    $uid = (int)$existingCas['UID'];
                    $user = new Episciences_User();
                    $user->find($uid);
                    $user->setUid($uid);
                    $user->setPassword($password);
                    $ccsdUserMapper->savePassword($user);

                    // Ensure roles for current journal
                    $user->saveNewRoles($uid, $roles, $rvid);
                    $results[] = [$username, implode(', ', $roles), $email, "Updated (UID $uid)"];
                } else {
                    $user = new Episciences_User();
                    $user->setUsername($username);
                    $user->setEmail($email);
                    $user->setFirstname($firstname);
                    $user->setLastname($lastname);
                    $user->setPassword($password);
                    $user->setValid(1);
                    $user->setIs_valid(1);

                    $user->save(false, true, $rvid);
                    $uid = $user->getUid();

                    if ($uid) {
                        $user->saveNewRoles($uid, $roles, $rvid);
                        $results[] = [$username, implode(', ', $roles), $email, "Created (UID $uid)"];
                    } else {
                        $results[] = [$username, implode(', ', $roles), $email, "Failed to retrieve UID"];
                    }
                }
            } catch (\Exception $e) {
                $results[] = [$username, implode(', ', $roles), $email, "Error: " . $e->getMessage()];
            }
        }

        $io->table(['Username', 'Roles', 'Email', 'Status'], $results);
        $io->success("E2E seed finished. All test user passwords are: $password");

        return Command::SUCCESS;
    }
}
