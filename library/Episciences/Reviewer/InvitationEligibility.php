<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

use Episciences_Paper;
use Episciences_User_Assignment;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Expr;
use Zend_Db_Table_Abstract;

/**
 * Whether a person may be invited to review a version, and why not (git #1387): the people who may not are shown with
 * the reason rather than hidden, so that the editor does not take them for unknown people.
 */
final class InvitationEligibility
{
    public const REASON_AUTHOR = 'author';
    public const REASON_SELF = 'self';
    public const REASON_CONFLICT = 'conflict';
    public const REASON_ALREADY_INVITED = 'already_invited';

    /** @var array<int, true> */
    private readonly array $conflictUids;

    /** @var array<int, true> */
    private readonly array $invitedUids;

    /** @var array<string, true> */
    private readonly array $invitedEmails;

    /**
     * @param list<int> $conflictUids accounts that declared a conflict of interest with the paper
     * @param list<int> $invitedUids accounts with a pending or accepted invitation on the version
     * @param list<string> $invitedEmails addresses of the people with a pending or accepted invitation on the version
     */
    public function __construct(
        private readonly int $authorUid,
        private readonly int $loggedUid,
        array $conflictUids,
        array $invitedUids,
        array $invitedEmails
    ) {
        $this->conflictUids = array_fill_keys($conflictUids, true);
        $this->invitedUids = array_fill_keys($invitedUids, true);
        $this->invitedEmails = array_fill_keys(array_map(self::normalizedEmail(...), $invitedEmails), true);
    }

    /**
     * @param list<int> $conflictUids accounts that declared a conflict of interest with the paper
     */
    public static function forVersion(Episciences_Paper $paper, int $loggedUid, array $conflictUids, ?Zend_Db_Adapter_Abstract $db = null): self
    {
        $db ??= Zend_Db_Table_Abstract::getDefaultAdapter();
        $invitedUids = [];
        $invitedEmails = [];

        foreach ($db->fetchAll(self::invitedQuery($db, (int)$paper->getDocid())) as $row) {
            if (!(int)$row['TMP_USER']) {
                $invitedUids[] = (int)$row['UID'];
            }
            if ((string)$row['EMAIL'] !== '') {
                $invitedEmails[] = (string)$row['EMAIL'];
            }
        }

        return new self((int)$paper->getUid(), $loggedUid, $conflictUids, $invitedUids, $invitedEmails);
    }

    /**
     * Why this person may not be invited, null when they may
     *
     * @param int|null $uid account, null for a person without an account
     */
    public function refusalFor(?int $uid, string $email = ''): ?string
    {
        return match (true) {
            $uid !== null && $uid === $this->authorUid => self::REASON_AUTHOR,
            $uid !== null && $uid === $this->loggedUid => self::REASON_SELF,
            $uid !== null && isset($this->conflictUids[$uid]) => self::REASON_CONFLICT,
            $uid !== null && isset($this->invitedUids[$uid]),
            $email !== '' && isset($this->invitedEmails[self::normalizedEmail($email)]) => self::REASON_ALREADY_INVITED,
            default => null,
        };
    }

    private static function normalizedEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * The people with a pending or accepted invitation on the version (latest assignment of each), with their address
     */
    private static function invitedQuery(Zend_Db_Adapter_Abstract $db, int $docId): \Zend_Db_Select
    {
        $latest = $db->select()
            ->from(T_ASSIGNMENTS, ['LAST_ID' => new Zend_Db_Expr('MAX(ID)')])
            ->where('ITEMID = ?', $docId)
            ->where('ITEM = ?', Episciences_User_Assignment::ITEM_PAPER)
            ->where('ROLEID = ?', Episciences_User_Assignment::ROLE_REVIEWER)
            ->group(['UID', 'TMP_USER']);

        return $db->select()
            ->from(['a' => T_ASSIGNMENTS], ['UID' => 'a.UID', 'TMP_USER' => 'a.TMP_USER'])
            ->join(['l' => $latest], 'a.ID = l.LAST_ID', [])
            ->joinLeft(['u' => T_USERS], 'a.TMP_USER = 0 AND u.UID = a.UID', [])
            ->joinLeft(['t' => T_TMP_USER], 'a.TMP_USER = 1 AND t.ID = a.UID', [])
            ->columns(['EMAIL' => new Zend_Db_Expr("COALESCE(u.EMAIL, t.EMAIL, '')")])
            ->where('a.STATUS IN (?)', [Episciences_User_Assignment::STATUS_PENDING, Episciences_User_Assignment::STATUS_ACTIVE]);
    }
}
