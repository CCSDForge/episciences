<?php

declare(strict_types=1);

namespace Episciences\Mail;

use Episciences_Acl;
use Episciences_User_Assignment;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Expr;
use Zend_Db_Select;

/**
 * Bounded search of the recipients of a mail, among the users of a journal.
 *
 * Plain SQL on the user tables, no Episciences_User instantiation. Returned values are raw (not HTML-escaped):
 * escaping is done when rendering.
 */
final class RecipientSearchService implements RecipientDirectory
{
    public const DEFAULT_LIMIT = 20;
    public const STAFF_LIMIT = 50;
    public const MAX_LIMIT = 50;
    public const MIN_QUERY_LENGTH = 2;
    public const MIN_EMAIL_QUERY_LENGTH = 3;
    /** Escape character of the LIKE patterns (a backslash would need a second level of escaping) */
    public const LIKE_ESCAPE = '!';

    /** Editorial roles that can be listed by group without a text query */
    public const BROWSEABLE_STAFF_ROLES = [
        Episciences_Acl::ROLE_CHIEF_EDITOR,
        Episciences_Acl::ROLE_SECRETARY,
        Episciences_Acl::ROLE_EDITOR,
        Episciences_Acl::ROLE_GUEST_EDITOR,
        Episciences_Acl::ROLE_COPY_EDITOR,
        Episciences_Acl::ROLE_WEBMASTER,
    ];

    /** Roles of the participants of a paper, ordered by precedence */
    private const PAPER_ROLES = [
        Episciences_Acl::ROLE_AUTHOR,
        Episciences_Acl::ROLE_CO_AUTHOR,
        Episciences_Acl::ROLE_EDITOR,
        Episciences_Acl::ROLE_COPY_EDITOR,
        Episciences_Acl::ROLE_REVIEWER,
    ];

    public function __construct(private readonly Zend_Db_Adapter_Abstract $db)
    {
    }

    /**
     * Text search (name, username, email) within the scope.
     *
     * @param ?string $staffRole restricts the search to an editorial role (see BROWSEABLE_STAFF_ROLES)
     * @return list<array{uid: int, fullname: string, username: string, mail: string, roles: list<string>, group: string, paperRole: ?string}>
     */
    public function search(int $rvid, RecipientScope $scope, string $query, ?string $staffRole = null, int $limit = self::DEFAULT_LIMIT): array
    {
        $query = trim($query);

        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return [];
        }

        $participants = $this->contextParticipants($rvid, $scope);
        $select = $this->searchQuery($rvid, $scope, $query, $staffRole, array_keys($participants), self::boundLimit($limit));

        return $this->toSuggestions($this->db->fetchAll($select), $participants);
    }

    /**
     * Users of an editorial role, without text query.
     *
     * @return list<array{uid: int, fullname: string, username: string, mail: string, roles: list<string>, group: string, paperRole: ?string}>
     */
    public function staff(int $rvid, RecipientScope $scope, string $staffRole, int $limit = self::STAFF_LIMIT): array
    {
        if (!in_array($staffRole, self::BROWSEABLE_STAFF_ROLES, true)) {
            return [];
        }

        $participants = $this->contextParticipants($rvid, $scope);
        $select = $this->baseQuery($rvid, array_keys($participants))
            ->where('u.UID IN ?', $this->roleQuery($rvid, [$staffRole]))
            ->limit(self::boundLimit($limit));

        return $this->toSuggestions($this->db->fetchAll($select), $participants);
    }

    /**
     * Participants of the paper of the mail (authors, editors, copy editors and, if allowed, reviewers).
     *
     * @return list<array{uid: int, fullname: string, username: string, mail: string, roles: list<string>, group: string, paperRole: ?string}>
     */
    public function paperParticipants(int $rvid, RecipientScope $scope): array
    {
        $participants = $this->contextParticipants($rvid, $scope);

        if ($participants === []) {
            return [];
        }

        $select = $this->baseQuery($rvid, array_keys($participants))
            ->where('u.UID IN (?)', array_keys($participants))
            ->limit(self::MAX_LIMIT);

        return $this->toSuggestions($this->db->fetchAll($select), $participants);
    }

    /**
     * Accounts of the journal matching the given addresses (case-insensitive). An address shared by
     * several accounts is ambiguous and is not resolved.
     *
     * @param list<string> $emails
     * @return array<string, int> lower-cased email => UID
     */
    public function resolveEmails(int $rvid, array $emails): array
    {
        $emails = array_values(array_unique(array_filter(array_map(
            static fn(string $email): string => mb_strtolower(trim($email)),
            $emails
        ))));

        if ($emails === []) {
            return [];
        }

        $select = $this->db->select()
            ->from(['u' => T_USERS], ['UID', 'EMAIL'])
            ->where('u.IS_VALID = ?', 1)
            ->where('u.EMAIL IN (?)', $emails)
            ->where('u.UID IN ?', $this->reviewUsersQuery($rvid));

        $uidsByEmail = [];

        foreach ($this->db->fetchAll($select) as $row) {
            $uidsByEmail[mb_strtolower((string)$row['EMAIL'])][] = (int)$row['UID'];
        }

        $resolved = [];

        foreach ($uidsByEmail as $email => $uids) {
            if (count(array_unique($uids)) === 1) {
                $resolved[$email] = $uids[0];
            }
        }

        return $resolved;
    }

    /**
     * Email address of the given accounts, for the users of the journal only.
     *
     * @param list<int> $uids
     * @return array<int, string> UID => lower-cased email
     */
    public function reviewUserEmails(int $rvid, array $uids): array
    {
        $uids = array_values(array_unique(array_filter($uids, static fn(int $uid): bool => $uid > 0)));

        if ($uids === []) {
            return [];
        }

        $select = $this->db->select()
            ->from(['u' => T_USERS], ['UID', 'EMAIL'])
            ->where('u.IS_VALID = ?', 1)
            ->where('u.UID IN (?)', $uids)
            ->where('u.UID IN ?', $this->reviewUsersQuery($rvid));

        $emails = [];

        foreach ($this->db->fetchAll($select) as $row) {
            $emails[(int)$row['UID']] = mb_strtolower((string)$row['EMAIL']);
        }

        return $emails;
    }

    /**
     * Role of each participant of a paper (all versions), the most significant role first.
     * Editors, copy editors and reviewers are only kept while their assignment is active (or pending
     * for reviewers): assignments are never updated, the latest row of each one tells its status.
     *
     * @return array<int, string> UID => role
     */
    public function paperRoles(int $rvid, int $paperId, bool $withReviewers): array
    {
        $roles = [];

        $authors = $this->db->select()
            ->from(T_PAPERS, ['UID'])
            ->where('RVID = ?', $rvid)
            ->where('PAPERID = ?', $paperId);

        foreach ($this->db->fetchCol($authors) as $uid) {
            $roles[(int)$uid] = Episciences_Acl::ROLE_AUTHOR;
        }

        $assignmentRoles = [Episciences_Acl::ROLE_CO_AUTHOR, Episciences_Acl::ROLE_EDITOR, Episciences_Acl::ROLE_COPY_EDITOR];

        if ($withReviewers) {
            $assignmentRoles[] = Episciences_Acl::ROLE_REVIEWER;
        }

        $latest = self::latestPaperAssignmentsQuery($this->db, $rvid, $assignmentRoles)
            ->where('ITEMID IN ?', $this->db->select()->from(T_PAPERS, ['DOCID'])->where('PAPERID = ?', $paperId));

        $select = $this->db->select()
            ->from(['a' => T_ASSIGNMENTS], ['UID', 'ROLEID', 'STATUS'])
            ->join(['l' => $latest], 'a.ID = l.LAST_ID', []);

        foreach ($this->db->fetchAll($select) as $row) {
            if (!self::isCurrentAssignment((string)$row['ROLEID'], (string)$row['STATUS'])) {
                continue;
            }

            $uid = (int)$row['UID'];
            $role = (string)$row['ROLEID'];

            if (!isset($roles[$uid]) || self::rolePrecedence($role) < self::rolePrecedence($roles[$uid])) {
                $roles[$uid] = $role;
            }
        }

        return $roles;
    }

    public function temporaryReviewerEmails(int $rvid, int $paperId): array
    {
        $latest = self::latestPaperAssignmentsQuery($this->db, $rvid, [Episciences_Acl::ROLE_REVIEWER], true)
            ->where('ITEMID IN ?', $this->db->select()->from(T_PAPERS, ['DOCID'])->where('PAPERID = ?', $paperId));

        $select = $this->db->select()
            ->from(['a' => T_ASSIGNMENTS], [])
            ->join(['l' => $latest], 'a.ID = l.LAST_ID', [])
            ->join(['t' => T_TMP_USER], 't.ID = a.UID', ['EMAIL'])
            ->where('a.STATUS IN (?)', [Episciences_User_Assignment::STATUS_ACTIVE, Episciences_User_Assignment::STATUS_PENDING]);

        return array_values(array_unique(array_map(
            static fn(mixed $email): string => mb_strtolower(trim((string)$email)),
            $this->db->fetchCol($select)
        )));
    }

    /**
     * IDs of the latest paper assignment of each (document, user, role): assignments are never updated, a new row
     * is added instead, so the latest one tells the current status.
     *
     * @param list<string> $roles
     * @param bool $temporaryUsers false: accounts only; true: users invited without an account only (their ID is
     *                             not a UID)
     */
    public static function latestPaperAssignmentsQuery(Zend_Db_Adapter_Abstract $db, int $rvid, array $roles, bool $temporaryUsers = false): Zend_Db_Select
    {
        return $db->select()
            ->from(T_ASSIGNMENTS, ['LAST_ID' => new Zend_Db_Expr('MAX(ID)')])
            ->where('RVID = ?', $rvid)
            ->where('ITEM = ?', Episciences_User_Assignment::ITEM_PAPER)
            ->where('TMP_USER = ?', $temporaryUsers ? 1 : 0)
            ->where('ROLEID IN (?)', $roles)
            ->group(['ITEMID', 'UID', 'ROLEID']);
    }

    /**
     * Escapes the LIKE metacharacters of a value, for patterns using LIKE_ESCAPE
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(
            [self::LIKE_ESCAPE, '%', '_'],
            [self::LIKE_ESCAPE . self::LIKE_ESCAPE, self::LIKE_ESCAPE . '%', self::LIKE_ESCAPE . '_'],
            $value
        );
    }

    public static function boundLimit(int $limit): int
    {
        return max(1, min($limit, self::MAX_LIMIT));
    }

    /**
     * @param list<int> $contextUids participants of the paper of the mail, listed first
     */
    public function searchQuery(int $rvid, RecipientScope $scope, string $query, ?string $staffRole, array $contextUids, int $limit): Zend_Db_Select
    {
        $select = $this->baseQuery($rvid, $contextUids, $query)
            ->where($this->visibilityCondition($rvid, $scope, $contextUids))
            ->where($this->textCondition($query))
            ->limit($limit);

        if ($staffRole !== null) {
            $select->where('u.UID IN ?', $this->roleQuery($rvid, [$staffRole]));
        }

        return $select;
    }

    /**
     * @param list<int> $contextUids
     */
    private function baseQuery(int $rvid, array $contextUids, string $query = ''): Zend_Db_Select
    {
        $select = $this->db->select()
            ->from(['u' => T_USERS], ['UID', 'SCREEN_NAME', 'FIRSTNAME', 'LASTNAME', 'USERNAME', 'EMAIL'])
            ->joinLeft(
                ['r' => T_USER_ROLES],
                $this->db->quoteInto('r.UID = u.UID AND r.RVID = ?', $rvid),
                ['ROLES' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT r.ROLEID ORDER BY r.ROLEID SEPARATOR ',')")]
            )
            ->where('u.IS_VALID = ?', 1)
            // u.UID is the primary key: the other user columns are functionally dependent (ONLY_FULL_GROUP_BY)
            ->group('u.UID');

        $order = [];

        if ($contextUids !== []) {
            $order[] = new Zend_Db_Expr('CASE WHEN ' . $this->inList('u.UID', $contextUids) . ' THEN 0 ELSE 1 END');
        }

        $order[] = new Zend_Db_Expr('CASE WHEN ' . $this->db->quoteInto('SUM(r.ROLEID IN (?)) > 0', self::BROWSEABLE_STAFF_ROLES) . ' THEN 0 ELSE 1 END');

        if ($query !== '') {
            $order[] = new Zend_Db_Expr('CASE WHEN ' . $this->likePrefix('u.LASTNAME', $query) . ' THEN 0 ELSE 1 END');
        }

        $order[] = 'u.LASTNAME ASC';
        $order[] = 'u.FIRSTNAME ASC';
        $order[] = 'u.UID ASC';

        return $select->order($order);
    }

    /**
     * @param list<int> $contextUids
     */
    private function visibilityCondition(int $rvid, RecipientScope $scope, array $contextUids): string
    {
        // Starting from the users of the journal (indexed) rather than from every account of the platform
        $conditions = [$scope->isAllReviewUsers()
            ? $this->db->quoteInto('u.UID IN ?', $this->reviewUsersQuery($rvid))
            : $this->db->quoteInto('u.UID IN ?', $this->roleQuery($rvid, self::BROWSEABLE_STAFF_ROLES))];

        if ($contextUids !== []) {
            $conditions[] = $this->inList('u.UID', $contextUids);
        }

        if (!$scope->isAllReviewUsers() && $scope->paperIds !== []) {
            $conditions[] = $this->db->quoteInto('u.UID IN ?', $this->paperAuthorsQuery($rvid, $scope->paperIds));
        }

        return '(' . implode(' OR ', $conditions) . ')';
    }

    private function textCondition(string $query): string
    {
        $conditions = [];

        foreach (['u.LASTNAME', 'u.FIRSTNAME', 'u.SCREEN_NAME', 'u.USERNAME'] as $column) {
            $conditions[] = $this->likePrefix($column, $query);
        }

        if (filter_var($query, FILTER_VALIDATE_EMAIL) !== false) {
            $conditions[] = $this->db->quoteInto('u.EMAIL = ?', $query);
        } elseif (mb_strlen($query) >= self::MIN_EMAIL_QUERY_LENGTH) {
            $conditions[] = $this->likePrefix('u.EMAIL', $query);
        }

        return '(' . implode(' OR ', $conditions) . ')';
    }

    private function likePrefix(string $column, string $query): string
    {
        return $this->db->quoteInto($column . ' LIKE ?', self::escapeLike($query) . '%')
            . " ESCAPE '" . self::LIKE_ESCAPE . "'";
    }

    /**
     * @param list<int> $values
     */
    private function inList(string $column, array $values): string
    {
        return $values === [] ? '0 = 1' : $this->db->quoteInto($column . ' IN (?)', $values);
    }

    /**
     * @param list<string> $roles
     */
    private function roleQuery(int $rvid, array $roles): Zend_Db_Select
    {
        return $this->db->select()
            ->from(T_USER_ROLES, ['UID'])
            ->where('RVID = ?', $rvid)
            ->where('ROLEID IN (?)', $roles);
    }

    private function reviewUsersQuery(int $rvid): Zend_Db_Select
    {
        return $this->db->select()->from(T_USER_ROLES, ['UID'])->where('RVID = ?', $rvid);
    }

    /**
     * Submitters and co-authors of the given papers
     *
     * @param list<int> $paperIds
     */
    private function paperAuthorsQuery(int $rvid, array $paperIds): Zend_Db_Select
    {
        $submitters = $this->db->select()
            ->from(T_PAPERS, ['UID'])
            ->where('RVID = ?', $rvid)
            ->where('PAPERID IN (?)', $paperIds);

        $coAuthors = $this->db->select()
            ->from(['a' => T_ASSIGNMENTS], ['UID'])
            ->join(['p' => T_PAPERS], 'p.DOCID = a.ITEMID', [])
            ->where('a.RVID = ?', $rvid)
            ->where('a.ITEM = ?', Episciences_User_Assignment::ITEM_PAPER)
            ->where('a.ROLEID = ?', Episciences_Acl::ROLE_CO_AUTHOR)
            ->where('a.TMP_USER = ?', 0)
            ->where('p.PAPERID IN (?)', $paperIds);

        return $this->db->select()->union([$submitters, $coAuthors]);
    }

    /**
     * @return array<int, string> UID => role in the paper of the mail
     */
    private function contextParticipants(int $rvid, RecipientScope $scope): array
    {
        return $scope->contextPaperId === null ? [] : $this->paperRoles($rvid, $scope->contextPaperId, $scope->withContextReviewers);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $participants
     * @return list<array{uid: int, fullname: string, username: string, mail: string, roles: list<string>, group: string, paperRole: ?string}>
     */
    private function toSuggestions(array $rows, array $participants): array
    {
        return array_values(array_map(
            static fn(array $row): array => RecipientSuggestions::fromRow($row, $participants[(int)$row['UID']] ?? null),
            $rows
        ));
    }

    private static function isCurrentAssignment(string $role, string $status): bool
    {
        if ($role === Episciences_Acl::ROLE_CO_AUTHOR) {
            return true;
        }

        $current = [Episciences_User_Assignment::STATUS_ACTIVE];

        if ($role === Episciences_Acl::ROLE_REVIEWER) {
            $current[] = Episciences_User_Assignment::STATUS_PENDING;
        }

        return in_array($status, $current, true);
    }

    private static function rolePrecedence(string $role): int
    {
        $precedence = array_search($role, self::PAPER_ROLES, true);

        return $precedence === false ? PHP_INT_MAX : $precedence;
    }
}
