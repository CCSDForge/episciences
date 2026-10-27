<?php

declare(strict_types=1);

use Episciences\Section\SectionHasPapersException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Symfony Console command to seed deterministic test users and roles for Playwright E2E tests.
 *
 * With --reset, it also removes what previous E2E runs left in the journal: every paper submitted by
 * the E2E author (with its assignments and invitations), then every volume and section whose title
 * starts with E2E_TITLE_PREFIX, together with the rating grid of each removed volume.
 * Nothing else is touched: data that does not belong to the E2E accounts stays as it is.
 */
#[AsCommand(name: 'app:e2e-seed', description: 'Seeds deterministic E2E test users with known credentials and roles.')]
class E2ESeedCommand extends Command
{
    /** Volumes and sections created by the E2E suite carry this title prefix */
    public const E2E_TITLE_PREFIX = 'E2E ';

    /** The E2E account that submits every paper of the suite */
    private const E2E_AUTHOR_USERNAME = 'e2e_author';

    protected function configure(): void
    {
        $this
            ->addOption('rvcode', null, InputOption::VALUE_OPTIONAL, 'Journal code for roles', 'dev')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'Fixed password for all generated users', 'password123')
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Also remove the papers, volumes and sections left by previous E2E runs');
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

                if ($existingCas !== null) {
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

        if ($input->getOption('reset')) {
            $this->resetJournalData($io, $rvid);
        }

        $io->success("E2E seed finished. All test user passwords are: $password");

        return Command::SUCCESS;
    }

    /**
     * Removes the papers, volumes and sections left in the journal by previous E2E runs.
     */
    private function resetJournalData(SymfonyStyle $io, int $rvid): void
    {
        $authorUid = $this->findE2eAuthorUid();
        $deletedPapers = $authorUid === null ? 0 : $this->deleteE2ePapers($authorUid, $rvid);
        $deletedVolumes = $this->deleteE2eVolumes($rvid);
        $deletedSections = $this->deleteE2eSections($rvid);

        $io->writeln(sprintf(
            'Reset: %d paper(s), %d volume(s) and %d section(s) left by previous E2E runs removed.',
            $deletedPapers,
            $deletedVolumes,
            $deletedSections
        ));
    }

    private function findE2eAuthorUid(): ?int
    {
        $account = (new Ccsd_User_Models_UserMapper())->findByUsernameIncludingInactive(self::E2E_AUTHOR_USERNAME);

        return $account === null ? null : (int)$account['UID'];
    }

    /**
     * Deletes every version of every paper submitted by the E2E author in the journal.
     * Episciences_PapersManager::delete() leaves assignments and invitations behind, they are purged here.
     */
    private function deleteE2ePapers(int $authorUid, int $rvid): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $docIds = $db->fetchCol(
            $db->select()->from(T_PAPERS, ['DOCID'])->where('UID = ?', $authorUid)->where('RVID = ?', $rvid)
        );

        $deleted = 0;

        foreach ($docIds as $docId) {
            $assignmentIds = $db->fetchCol(
                $db->select()
                    ->from(T_ASSIGNMENTS, ['ID'])
                    ->where('ITEM = ?', Episciences_User_Assignment::ITEM_PAPER)
                    ->where('ITEMID = ?', (int)$docId)
            );

            if ($assignmentIds !== []) {
                $db->delete(T_USER_INVITATIONS, ['AID IN (?)' => $assignmentIds]);
                $db->delete(T_ASSIGNMENTS, ['ID IN (?)' => $assignmentIds]);
            }

            if (Episciences_PapersManager::delete((int)$docId)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Deletes the E2E volumes and their rating grids. A volume that still holds papers is kept.
     */
    private function deleteE2eVolumes(int $rvid): int
    {
        $deleted = 0;

        foreach ($this->findE2eItemIds(T_VOLUMES, 'VID', $rvid) as $vid) {
            ob_start(); // VolumesManager::delete() echoes its refusal message
            $isDeleted = Episciences_VolumesManager::delete($vid);
            ob_end_clean();

            if (!$isDeleted) {
                continue;
            }

            $deleted++;
            $gridFile = 'grid_' . $vid . '.xml';

            if (Episciences_GridsManager::gridExists($gridFile)) {
                Episciences_GridsManager::delete($gridFile);
            }
        }

        return $deleted;
    }

    private function deleteE2eSections(int $rvid): int
    {
        $deleted = 0;

        foreach ($this->findE2eItemIds(T_SECTIONS, 'SID', $rvid) as $sid) {
            try {
                if (Episciences_SectionsManager::delete($sid)) {
                    $deleted++;
                }
            } catch (SectionHasPapersException) {
                // Papers of other accounts were moved into it: the section is kept
            }
        }

        return $deleted;
    }

    /**
     * @return int[] identifiers of the volumes or sections whose English title starts with E2E_TITLE_PREFIX
     */
    private function findE2eItemIds(string $table, string $idColumn, int $rvid): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from($table, [$idColumn])
            ->where('RVID = ?', $rvid)
            ->where("JSON_UNQUOTE(JSON_EXTRACT(titles, '$.en')) LIKE ?", self::E2E_TITLE_PREFIX . '%');

        return array_map('intval', $db->fetchCol($select));
    }
}
