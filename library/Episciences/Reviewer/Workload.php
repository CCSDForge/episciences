<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

use Episciences_Paper;
use Episciences_Rating_Report;
use Episciences_User_Assignment;
use Episciences_User_Invitation;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Expr;
use Zend_Db_Table_Abstract;

/**
 * How much a reviewer is already asked in the journal (git #320): reviews in progress, pending invitations, and the
 * latest invitation with its answer. Shown to the editor who is about to invite them.
 */
final class Workload
{
    public const ANSWER_PENDING = 'pending';
    public const ANSWER_ACCEPTED = 'accepted';
    public const ANSWER_DECLINED = 'declined';
    public const ANSWER_CANCELLED = 'cancelled';
    public const ANSWER_EXPIRED = 'expired';

    /** Statuses of a paper still being reviewed: only its reviews and invitations are in progress */
    public const REVIEWING_STATUSES = [
        Episciences_Paper::STATUS_SUBMITTED,
        Episciences_Paper::STATUS_OK_FOR_REVIEWING,
        Episciences_Paper::STATUS_REVIEWERS_INVITED,
        Episciences_Paper::STATUS_BEING_REVIEWED,
        Episciences_Paper::STATUS_REVIEWED,
        Episciences_Paper::STATUS_TMP_VERSION,
    ];

    /** Kinds of the papers of a workload */
    public const PAPER_REVIEW = 'review';
    public const PAPER_INVITATION = 'invitation';

    /**
     * @param list<array{docid: int, kind: string, date: string|null}> $papers the reviews in progress (date: due date)
     *        and the pending invitations (date: sending date)
     */
    public function __construct(
        private readonly int $reviewsInProgress = 0,
        private readonly int $pendingInvitations = 0,
        private readonly ?string $lastInvitationDate = null,
        private readonly ?string $lastInvitationAnswer = null,
        private readonly array $papers = [],
        private readonly ?int $lastInvitationDocId = null
    ) {
    }

    /**
     * @return list<array{docid: int, kind: string, date: string|null}>
     */
    public function getPapers(): array
    {
        return $this->papers;
    }

    /** Paper of the latest invitation, null if never invited */
    public function getLastInvitationDocId(): ?int
    {
        return $this->lastInvitationDocId;
    }

    /**
     * Papers of the reviews in progress and of the pending invitations of all the given workloads
     *
     * @param array<int|string, self> $workloads
     * @return list<int>
     */
    public static function paperDocIds(array $workloads): array
    {
        $docIds = [];

        foreach ($workloads as $workload) {
            foreach ($workload->getPapers() as $paper) {
                $docIds[$paper['docid']] = $paper['docid'];
            }
        }

        return array_values($docIds);
    }

    public function getReviewsInProgress(): int
    {
        return $this->reviewsInProgress;
    }

    public function getPendingInvitations(): int
    {
        return $this->pendingInvitations;
    }

    /** Sending date ("Y-m-d H:i:s") of the latest invitation in the journal, null if never invited */
    public function getLastInvitationDate(): ?string
    {
        return $this->lastInvitationDate;
    }

    /** One of the ANSWER_* values, null if never invited */
    public function getLastInvitationAnswer(): ?string
    {
        return $this->lastInvitationAnswer;
    }

    public function isEmpty(): bool
    {
        return $this->reviewsInProgress === 0 && $this->pendingInvitations === 0 && $this->lastInvitationDate === null;
    }

    /**
     * @return array{reviews_in_progress: int, pending_invitations: int, last_invitation_date: string|null, last_invitation_answer: string|null}
     */
    public function toArray(): array
    {
        return [
            'reviews_in_progress' => $this->reviewsInProgress,
            'pending_invitations' => $this->pendingInvitations,
            'last_invitation_date' => $this->lastInvitationDate,
            'last_invitation_answer' => $this->lastInvitationAnswer,
        ];
    }

    /** Key prefix of the people invited without an account, followed by their e-mail address in lower case */
    public const EMAIL_KEY_PREFIX = 'email:';

    /**
     * Workload of every reviewer of the journal, keyed by UID, and of every person invited without an account, keyed by
     * EMAIL_KEY_PREFIX + e-mail address (each invitation without an account creates a new temporary user: the address
     * is what they have in common). Reviews and invitations are only in progress on papers still being reviewed; the
     * paper being handled is left out of them.
     *
     * @return array<int|string, self>
     */
    public static function ofJournal(int $rvid, int $currentDocId, ?Zend_Db_Adapter_Abstract $db = null, ?string $now = null): array
    {
        $db ??= Zend_Db_Table_Abstract::getDefaultAdapter();

        return self::fromRows(
            $db->fetchAll(self::assignmentsQuery($db, $rvid)),
            $db->fetchAll(self::invitationsQuery($db, $rvid)),
            $currentDocId,
            $now ?? date('Y-m-d H:i:s')
        );
    }

    /**
     * @param list<array<string, mixed>> $assignments latest assignment of each (paper, reviewer): UID, ITEMID, PAPER_STATUS, STATUS, REPORT_DONE
     * @param list<array<string, mixed>> $invitations latest row of each invitation: UID, TMP_USER, TMP_EMAIL, ITEMID, PAPER_STATUS, STATUS, SENDING_DATE, EXPIRATION_DATE
     * @return array<int|string, self>
     */
    public static function fromRows(array $assignments, array $invitations, int $currentDocId, string $now): array
    {
        /** @var array<int|string, array{int, int, string|null, string|null, list<array{docid: int, kind: string, date: string|null}>, int|null}> $byUid in progress, pending, last date, last answer, papers, last paper */
        $byUid = [];

        foreach ($assignments as $row) {
            $uid = (int)$row['UID'];
            $byUid[$uid] ??= [0, 0, null, null, [], null];

            if (!self::isInProgressOn($row, $currentDocId)) {
                continue;
            }

            if ($row['STATUS'] === Episciences_User_Assignment::STATUS_ACTIVE && !(int)$row['REPORT_DONE']) {
                $byUid[$uid][0]++;
                $byUid[$uid][4][] = ['docid' => (int)$row['ITEMID'], 'kind' => self::PAPER_REVIEW, 'date' => self::dateOrNull($row['DEADLINE'] ?? null)];
            }
        }

        foreach ($invitations as $row) {
            $uid = self::keyOf($row);
            if ($uid === null) {
                continue;
            }
            $byUid[$uid] ??= [0, 0, null, null, [], null];
            $answer = self::answerOf((string)$row['STATUS'], (string)$row['EXPIRATION_DATE'], $now);
            $sendingDate = (string)$row['SENDING_DATE'];

            if ($answer === self::ANSWER_PENDING && self::isInProgressOn($row, $currentDocId)) {
                $byUid[$uid][1]++;
                $byUid[$uid][4][] = ['docid' => (int)$row['ITEMID'], 'kind' => self::PAPER_INVITATION, 'date' => $sendingDate];
            }

            if ($byUid[$uid][2] === null || $sendingDate > $byUid[$uid][2]) {
                $byUid[$uid][2] = $sendingDate;
                $byUid[$uid][3] = $answer;
                $byUid[$uid][5] = (int)$row['ITEMID'];
            }
        }

        return array_map(
            static fn(array $values): self => new self(...$values),
            $byUid
        );
    }

    /**
     * Workloads of the people with an account (by UID), and of the people invited without one, by SHA-256 hash of their
     * e-mail address in lower case: the page can match the address typed for a new reviewer without listing addresses.
     *
     * @param array<int|string, self> $workloads
     * @return array{array<int, self>, array<string, self>}
     */
    public static function splitByAccount(array $workloads): array
    {
        $byUid = [];
        $byEmailHash = [];

        foreach ($workloads as $key => $workload) {
            if (is_int($key)) {
                $byUid[$key] = $workload;
            } else {
                $byEmailHash[hash('sha256', substr($key, strlen(self::EMAIL_KEY_PREFIX)))] = $workload;
            }
        }

        return [$byUid, $byEmailHash];
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function keyOf(array $row): int|string|null
    {
        if (!(int)($row['TMP_USER'] ?? 0)) {
            return (int)$row['UID'];
        }

        $email = mb_strtolower(trim((string)($row['TMP_EMAIL'] ?? '')));

        return $email === '' ? null : self::EMAIL_KEY_PREFIX . $email;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function isInProgressOn(array $row, int $currentDocId): bool
    {
        return (int)$row['ITEMID'] !== $currentDocId
            && isset($row['PAPER_STATUS'])
            && in_array((int)$row['PAPER_STATUS'], self::REVIEWING_STATUSES, true);
    }

    private static function dateOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function answerOf(string $status, string $expirationDate, string $now): string
    {
        return match ($status) {
            Episciences_User_Invitation::STATUS_ACCEPTED => self::ANSWER_ACCEPTED,
            Episciences_User_Invitation::STATUS_DECLINED => self::ANSWER_DECLINED,
            Episciences_User_Invitation::STATUS_CANCELLED => self::ANSWER_CANCELLED,
            default => ($expirationDate !== '' && $expirationDate < $now) ? self::ANSWER_EXPIRED : self::ANSWER_PENDING,
        };
    }

    /**
     * Latest assignment of each (paper, reviewer) of the journal, reviewers with an account only, and whether the
     * reviewer completed their report on that paper.
     */
    private static function assignmentsQuery(Zend_Db_Adapter_Abstract $db, int $rvid): \Zend_Db_Select
    {
        $latest = $db->select()
            ->from(T_ASSIGNMENTS, ['LAST_ID' => new Zend_Db_Expr('MAX(ID)')])
            ->where('RVID = ?', $rvid)
            ->where('ITEM = ?', Episciences_User_Assignment::ITEM_PAPER)
            ->where('ROLEID = ?', Episciences_User_Assignment::ROLE_REVIEWER)
            ->where('TMP_USER = 0')
            // a reviewer may hold one request of each type on a paper (#319)
            ->group(['ITEMID', 'UID', 'REQUEST_TYPE_ID']);

        $reportDone = $db->select()
            ->from(['r' => T_REVIEWER_REPORTS], [new Zend_Db_Expr('1')])
            ->where('r.UID = a.UID')
            ->where('r.DOCID = a.ITEMID')
            // the report of this request type; an assignment saved before the types matches any report
            ->where('a.REQUEST_TYPE_ID IS NULL OR r.REQUEST_TYPE_ID = a.REQUEST_TYPE_ID')
            ->where('r.STATUS = ?', Episciences_Rating_Report::STATUS_COMPLETED);

        return $db->select()
            ->from(['a' => T_ASSIGNMENTS], [
                'UID' => 'a.UID',
                'ITEMID' => 'a.ITEMID',
                'STATUS' => 'a.STATUS',
                'DEADLINE' => 'a.DEADLINE',
                'REPORT_DONE' => new Zend_Db_Expr('EXISTS(' . $reportDone->assemble() . ')'),
            ])
            ->join(['l' => $latest], 'a.ID = l.LAST_ID', [])
            ->joinLeft(['p' => T_PAPERS], 'p.DOCID = a.ITEMID', ['PAPER_STATUS' => 'p.STATUS']);
    }

    /**
     * Latest row of each invitation sent to a reviewer in the journal, with the address of the people invited without an
     * account.
     */
    private static function invitationsQuery(Zend_Db_Adapter_Abstract $db, int $rvid): \Zend_Db_Select
    {
        $latest = $db->select()
            ->from(['i' => T_USER_INVITATIONS], ['LAST_ID' => new Zend_Db_Expr('MAX(i.ID)')])
            ->join(['a' => T_ASSIGNMENTS], 'a.ID = i.AID', [])
            ->where('a.RVID = ?', $rvid)
            ->where('a.ROLEID = ?', Episciences_User_Assignment::ROLE_REVIEWER)
            ->group('i.AID');

        return $db->select()
            ->from(['i' => T_USER_INVITATIONS], ['STATUS' => 'i.STATUS', 'SENDING_DATE' => 'i.SENDING_DATE', 'EXPIRATION_DATE' => 'i.EXPIRATION_DATE'])
            ->join(['l' => $latest], 'i.ID = l.LAST_ID', [])
            ->join(['a' => T_ASSIGNMENTS], 'a.ID = i.AID', ['UID' => 'a.UID', 'TMP_USER' => 'a.TMP_USER', 'ITEMID' => 'a.ITEMID'])
            ->joinLeft(['t' => T_TMP_USER], 'a.TMP_USER = 1 AND t.ID = a.UID', ['TMP_EMAIL' => 't.EMAIL'])
            ->joinLeft(['p' => T_PAPERS], 'p.DOCID = a.ITEMID', ['PAPER_STATUS' => 'p.STATUS']);
    }
}
