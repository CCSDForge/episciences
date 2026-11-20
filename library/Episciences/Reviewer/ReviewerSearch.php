<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

use Ccsd_Db_Adapter_Cas;
use Episciences_Acl;
use Episciences_User_Assignment;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Select;
use Zend_Db_Table_Abstract;

/**
 * Finds the people an editor may invite to review (git #900), in one search: the reviewers of the journal, the other
 * Episciences accounts, and the people the journal invited without an account (found by their address only).
 *
 * An address (a term with "@") is matched exactly, whatever its case. Otherwise every word of the term must be in the
 * first name, the last name or the username, in any order ("Claire Voisin" finds "Voisin Claire"): anywhere among
 * the reviewers of the journal, at the start of a name in the accounts directory (1.5 million accounts: only the start
 * of a name uses its indexes; the collation ignores case and accents).
 */
final class ReviewerSearch
{
    public const GROUP_JOURNAL = 'journal';
    public const GROUP_ACCOUNTS = 'accounts';
    public const GROUP_WITHOUT_ACCOUNT = 'without_account';

    public const ORDER_NAME = 'name';
    public const ORDER_LAST_INVITATION = 'last_invitation';
    public const ORDER_LEAST_REQUESTED = 'least_requested';

    /** Shortest term searched */
    public const MIN_LENGTH = 2;
    /** People of each group at most */
    public const LIMIT = 50;
    /** Words of a term taken into account */
    private const MAX_WORDS = 5;
    /** The accounts directory is only searched with a word of this length at least */
    public const MIN_DIRECTORY_WORD_LENGTH = 3;

    private Zend_Db_Adapter_Abstract $db;
    private Zend_Db_Adapter_Abstract $accountsDb;

    public function __construct(?Zend_Db_Adapter_Abstract $db = null, ?Zend_Db_Adapter_Abstract $accountsDb = null)
    {
        $this->db = $db ?? Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->accountsDb = $accountsDb ?? Ccsd_Db_Adapter_Cas::getAdapter();
    }

    /**
     * @return array<string, list<array<string, mixed>>> people of each group (GROUP_*), empty for a term too short
     */
    public function search(string $term, int $rvid): array
    {
        $groups = [self::GROUP_JOURNAL => [], self::GROUP_ACCOUNTS => [], self::GROUP_WITHOUT_ACCOUNT => []];
        $term = trim($term);

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return $groups;
        }

        $email = self::isEmail($term) ? mb_strtolower($term) : null;
        $words = $email === null ? self::words($term) : [];

        $groups[self::GROUP_JOURNAL] = array_map(
            static fn(array $row): array => self::person($row, self::GROUP_JOURNAL),
            $this->db->fetchAll($this->journalReviewersQuery($rvid, $words, $email)->limit(self::LIMIT))
        );

        $groups[self::GROUP_ACCOUNTS] = $this->otherAccounts($rvid, $words, $email);

        if ($email !== null) {
            $groups[self::GROUP_WITHOUT_ACCOUNT] = array_map(
                static fn(array $row): array => self::person($row, self::GROUP_WITHOUT_ACCOUNT),
                $this->db->fetchAll($this->invitedWithoutAccountQuery($rvid, $email))
            );
        }

        return $groups;
    }

    /**
     * Every reviewer of the journal, for the browsable list, in the given order
     *
     * @param array<int, Workload> $workloads by UID, for the orders on the requests (git #320)
     * @return array{people: list<array<string, mixed>>, total: int}
     */
    public function browse(int $rvid, array $workloads, string $order, int $page, int $perPage): array
    {
        $people = array_map(
            static fn(array $row): array => self::person($row, self::GROUP_JOURNAL),
            $this->db->fetchAll($this->journalReviewersQuery($rvid, [], null))
        );

        if ($order !== self::ORDER_NAME) {
            $people = self::sortedByRequests($people, $workloads, $order);
        }

        $perPage = max(1, $perPage);
        $page = max(1, $page);

        return [
            'people' => array_slice($people, ($page - 1) * $perPage, $perPage),
            'total' => count($people),
        ];
    }

    public static function isEmail(string $term): bool
    {
        return str_contains($term, '@');
    }

    /**
     * @return list<string> the words of the term, lower case, without duplicates
     */
    public static function words(string $term): array
    {
        $words = preg_split('/\s+/u', mb_strtolower(trim($term)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice(array_values(array_unique($words)), 0, self::MAX_WORDS);
    }

    /**
     * @param list<string> $words
     */
    private function journalReviewersQuery(int $rvid, array $words, ?string $email): Zend_Db_Select
    {
        $select = $this->db->select()
            ->from(['u' => T_USERS], ['UID', 'FIRSTNAME', 'LASTNAME', 'EMAIL', 'USERNAME', 'SCREEN_NAME', 'LANGUEID'])
            ->join(['r' => T_USER_ROLES], 'r.UID = u.UID', [])
            ->where('r.RVID = ?', $rvid)
            ->where('r.ROLEID = ?', Episciences_Acl::ROLE_REVIEWER)
            ->order(['u.LASTNAME ASC', 'u.FIRSTNAME ASC', 'u.UID ASC']);

        self::addTermConditions($select, $this->db, 'u', $words, $email, false);

        return $select;
    }

    /**
     * The accounts of the directory matching the term, except the reviewers of the journal (already in their group)
     *
     * @param list<string> $words
     * @return list<array<string, mixed>>
     */
    private function otherAccounts(int $rvid, array $words, ?string $email): array
    {
        if ($email === null && max(array_map('mb_strlen', $words) ?: [0]) < self::MIN_DIRECTORY_WORD_LENGTH) {
            return [];
        }

        $select = $this->accountsDb->select()
            ->from(T_CAS_USERS, ['UID', 'FIRSTNAME', 'LASTNAME', 'EMAIL', 'USERNAME'])
            ->where('VALID = 1')
            ->order(['LASTNAME ASC', 'FIRSTNAME ASC', 'UID ASC'])
            ->limit(self::LIMIT * 2);
        self::addTermConditions($select, $this->accountsDb, null, $words, $email, true);

        $rows = $this->accountsDb->fetchAll($select);
        if ($rows === []) {
            return [];
        }

        $journalReviewers = array_flip(array_map('intval', $this->db->fetchCol(
            $this->db->select()
                ->from(T_USER_ROLES, ['UID'])
                ->where('RVID = ?', $rvid)
                ->where('ROLEID = ?', Episciences_Acl::ROLE_REVIEWER)
        )));
        $rows = array_values(array_filter($rows, static fn(array $row): bool => !isset($journalReviewers[(int)$row['UID']])));
        $rows = array_slice($rows, 0, self::LIMIT);

        if ($rows === []) {
            return [];
        }

        // language of the accounts that already have one on Episciences
        $locales = $this->db->fetchPairs(
            $this->db->select()
                ->from(T_USERS, ['UID', 'LANGUEID'])
                ->where('UID IN (?)', array_map(static fn(array $row): int => (int)$row['UID'], $rows))
        );

        return array_map(
            static fn(array $row): array => self::person($row + ['LANGUEID' => $locales[$row['UID']] ?? ''], self::GROUP_ACCOUNTS),
            $rows
        );
    }

    /**
     * The people this journal invited without an account with this address (latest first, once)
     */
    private function invitedWithoutAccountQuery(int $rvid, string $email): Zend_Db_Select
    {
        return $this->db->select()
            ->from(['t' => T_TMP_USER], ['EMAIL' => 't.EMAIL', 'FIRSTNAME' => 't.FIRSTNAME', 'LASTNAME' => 't.LASTNAME', 'LANGUEID' => 't.LANG'])
            ->join(['a' => T_ASSIGNMENTS], 'a.UID = t.ID AND a.TMP_USER = 1', [])
            ->where('a.RVID = ?', $rvid)
            ->where('a.ROLEID = ?', Episciences_User_Assignment::ROLE_REVIEWER)
            ->where('t.EMAIL = ?', $email)
            ->order('t.ID DESC')
            ->limit(1);
    }

    /**
     * @param list<string> $words
     * @param bool $startOnly words at the start of the names only (indexed search)
     */
    private static function addTermConditions(Zend_Db_Select $select, Zend_Db_Adapter_Abstract $db, ?string $alias, array $words, ?string $email, bool $startOnly): void
    {
        $column = static fn(string $name): string => $alias === null ? $name : $alias . '.' . $name;

        if ($email !== null) {
            // the collation of the address columns ignores case; LOWER() would prevent the use of their index
            $select->where($column('EMAIL') . ' = ?', $email);
            return;
        }

        foreach ($words as $word) {
            $like = $db->quote(($startOnly ? '' : '%') . addcslashes($word, '%_\\') . '%');
            $select->where(sprintf(
                '(%s LIKE %s OR %s LIKE %s OR %s LIKE %s)',
                $column('FIRSTNAME'),
                $like,
                $column('LASTNAME'),
                $like,
                $column('USERNAME'),
                $like
            ));
        }
    }

    /**
     * @param list<array<string, mixed>> $people
     * @param array<int, Workload> $workloads
     * @return list<array<string, mixed>>
     */
    private static function sortedByRequests(array $people, array $workloads, string $order): array
    {
        $index = array_flip(array_keys($people));
        usort($people, static function (array $a, array $b) use ($workloads, $order, $index): int {
            $wa = $workloads[$a['uid']] ?? new Workload();
            $wb = $workloads[$b['uid']] ?? new Workload();

            $comparison = $order === self::ORDER_LAST_INVITATION
                // latest invitation first, never invited last
                ? strcmp((string)$wb->getLastInvitationDate(), (string)$wa->getLastInvitationDate())
                // fewest requests in progress first, then the oldest latest invitation
                : [$wa->getReviewsInProgress() + $wa->getPendingInvitations(), (string)$wa->getLastInvitationDate()]
                <=> [$wb->getReviewsInProgress() + $wb->getPendingInvitations(), (string)$wb->getLastInvitationDate()];

            return $comparison !== 0 ? $comparison : ($index[$a['key']] ?? 0) <=> ($index[$b['key']] ?? 0);
        });

        return $people;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function person(array $row, string $group): array
    {
        $withoutAccount = $group === self::GROUP_WITHOUT_ACCOUNT;
        $uid = $withoutAccount ? null : (int)$row['UID'];
        $email = (string)($row['EMAIL'] ?? '');

        return [
            'key' => $uid !== null ? 'uid:' . $uid : 'email:' . mb_strtolower(trim($email)),
            'group' => $group,
            'uid' => $uid,
            'firstname' => (string)($row['FIRSTNAME'] ?? ''),
            'lastname' => (string)($row['LASTNAME'] ?? ''),
            'email' => $email,
            'username' => (string)($row['USERNAME'] ?? ''),
            'screen_name' => (string)($row['SCREEN_NAME'] ?? ''),
            'locale' => (string)($row['LANGUEID'] ?? ''),
        ];
    }
}
