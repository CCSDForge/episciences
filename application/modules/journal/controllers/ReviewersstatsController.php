<?php

declare(strict_types=1);

use Episciences\Reviewer\StatsQuery;

class ReviewersstatsController extends Zend_Controller_Action
{
    private const LIMIT = 50;

    /**
     * Display-only pseudo status (not one of Episciences_Reviewer_Reviewing's real status
     * codes): a superseded attempt that was never individually resolved (no report, not
     * declined) and whose paper no longer canBeReviewed(). Reusing STATUS_OBSOLETE /
     * STATUS_UNANSWERED there is misleading once a *later* attempt for the same reviewer/
     * paper did produce a completed report — canBeReviewed() is false simply because the
     * paper's review concluded successfully (accepted, revision requested, published, ...),
     * not because the article went stale. Typical case: an unanswered first invitation
     * superseded a few days later by a second one that was accepted and completed; the
     * paper is now STATUS_WAITING_FOR_MAJOR_REVISION, a normal successful outcome, not an
     * obsolete paper.
     */
    public const STATUS_SUPERSEDED_REPLACED = 100;

    public function indexAction(): void
    {
        // The filter form's checkboxes are stateless (always driven by the server-rendered
        // `checked` attribute); some browsers otherwise restore a checkbox to whatever the
        // user last set it to from their own page cache, silently overriding an unchecking
        // that was just submitted. Disabling caching of this page prevents that.
        header('Cache-Control: no-store');

        $rvid = (int)RVID;
        $currentUserId = (int)Episciences_Auth::getUid();

        // Role-based restriction (guest editor, encapsulated editor) always applies.
        // Anyone else can additionally opt in via the "only my papers" checkbox to
        // manually narrow the global view down to the same scope.
        $isRoleRestricted = $this->isRestrictedView($rvid);

        $request = $this->getRequest();
        $page = max(1, (int)$request->getParam('page', 1));
        $period = min(24, max(1, (int)$request->getParam('period', 12)));
        $search = $request->getParam('search');
        $search = is_string($search) && $search !== '' ? $search : null;

        $onlyPendingInv = (bool)$request->getParam('only_pending_inv', false);
        $onlyPendingRev = (bool)$request->getParam('only_pending_rev', false);
        $onlyOverdue = (bool)$request->getParam('only_overdue', false);
        $onlyMyResponsibility = !$isRoleRestricted && $request->getParam('only_my_responsibility', false);
        $isRestricted = $isRoleRestricted || $onlyMyResponsibility;

        $sort = (string)$request->getParam('sort', 'name');
        if (!array_key_exists($sort, StatsQuery::SORTABLE_COLUMNS)) {
            $sort = 'name';
        }
        $direction = strtolower((string)$request->getParam('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $offset = ($page - 1) * self::LIMIT;

        $queryService = new StatsQuery();
        $results = $queryService->getReviewersGlobalStats(
            $rvid,
            $isRestricted,
            $currentUserId,
            $period,
            $search,
            $onlyPendingInv,
            $onlyPendingRev,
            $onlyOverdue,
            false,
            self::LIMIT,
            $offset,
            $sort,
            $direction
        );

        $emails = [];
        $itemIds = [];
        foreach ($results['data'] as $row) {
            if (!empty($row['EMAIL'])) {
                $emails[] = (string)$row['EMAIL'];
            }
            if (!empty($row['item_ids'])) {
                foreach (explode(',', (string)$row['item_ids']) as $itemId) {
                    $itemIds[] = (int)$itemId;
                }
            }
        }
        $remindersCount = $queryService->getReminderCounts($rvid, $itemIds, $period, $emails);

        $totalPages = (int)ceil($results['total'] / self::LIMIT);

        $this->view->stats = $results['data'];
        $this->view->remindersCount = $remindersCount;
        $this->view->totalCount = $results['total'];
        $this->view->currentPage = $page;
        $this->view->totalPages = $totalPages;
        $this->view->pageRange = self::buildPageRange($page, $totalPages);
        $this->view->period = $period;
        $this->view->search = $search;
        $this->view->onlyPendingInv = $onlyPendingInv;
        $this->view->onlyPendingRev = $onlyPendingRev;
        $this->view->onlyOverdue = $onlyOverdue;
        $this->view->onlyMyResponsibility = $onlyMyResponsibility;
        $this->view->canFilterByResponsibility = !$isRoleRestricted;
        $this->view->sort = $sort;
        $this->view->direction = $direction;
    }

    /**
     * Drill-down behind one aggregate row of indexAction(): every individual invitation/
     * assignment for a single reviewer, so an editor can see exactly which papers made up
     * the numbers (e.g. why "pending invitations" shows 11).
     */
    public function detailAction(): void
    {
        header('Cache-Control: no-store');

        $rvid = (int)RVID;
        $currentUserId = (int)Episciences_Auth::getUid();
        $isRoleRestricted = $this->isRestrictedView($rvid);

        $request = $this->getRequest();
        $email = $request->getParam('email');
        $email = is_string($email) && $email !== '' ? $email : null;
        $uid = (int)$request->getParam('uid', 0);
        $tmpUser = (int)$request->getParam('tmp_user', 0);
        $reviewerName = (string)$request->getParam('name', '');
        $period = min(24, max(1, (int)$request->getParam('period', 24)));
        $onlyMyResponsibility = !$isRoleRestricted && $request->getParam('only_my_responsibility', false);
        $isRestricted = $isRoleRestricted || $onlyMyResponsibility;

        if ($email === null && $uid === 0) {
            $this->_helper->redirector->gotoUrl('/reviewersstats');
            return;
        }

        $queryService = new StatsQuery();
        $rows = $queryService->getReviewerInvitationDetails(
            $rvid,
            $email,
            $uid,
            $tmpUser,
            $isRestricted,
            $currentUserId,
            $period
        );

        $docIds = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['docid'], $rows)));
        $papers = Episciences_PapersManager::getByDocIds($docIds);

        $this->view->rows = self::enrichAndGroupRows($rows, $papers);
        $this->view->papers = $papers;
        $this->view->reviewerName = $reviewerName;
        $this->view->reviewerEmail = $email;
        $this->view->period = $period;
        $this->view->onlyMyResponsibility = $onlyMyResponsibility;
        $this->view->reviewerProfile = self::loadReviewerProfile($rows, $email === null ? $uid : 0, $tmpUser);
    }

    /**
     * Identity shown on the detail page's profile card. Rows are grouped by e-mail, so the same
     * person may appear both as an account-less invitee (TMP_USER = 1) and, after registering,
     * as a real account: the registered account wins since it carries the full profile.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param int $fallbackUid reviewer reached by UID (no e-mail on record), 0 otherwise
     * @return array<string, mixed>|null Episciences_User::toArray() or Episciences_User_Tmp::toArray()
     */
    private static function loadReviewerProfile(array $rows, int $fallbackUid, int $fallbackTmpUser): ?array
    {
        $registeredUid = 0;
        $tmpUserId = 0;
        foreach ($rows as $row) {
            if ((int)$row['tmp_user'] === 0) {
                $registeredUid = (int)$row['uid'];
                break;
            }
            $tmpUserId = $tmpUserId ?: (int)$row['uid'];
        }
        if ($registeredUid === 0 && $tmpUserId === 0 && $fallbackUid > 0) {
            if ($fallbackTmpUser === 0) {
                $registeredUid = $fallbackUid;
            } else {
                $tmpUserId = $fallbackUid;
            }
        }

        if ($registeredUid > 0) {
            $user = new Episciences_User();
            if ($user->find($registeredUid) !== []) {
                // CAS data: e-mail, names, registration date — same as UserDefaultController::viewAction()
                (new Ccsd_User_Models_UserMapper())->find($registeredUid, $user);
                return $user->toArray() + ['editorSections' => null];
            }
        }

        if ($tmpUserId > 0) {
            $tmpUser = new Episciences_User_Tmp();
            if ($tmpUser->find($tmpUserId) !== []) {
                $tmpUser->generateScreen_name();
                return $tmpUser->toArray();
            }
        }

        return null;
    }

    /**
     * Same reviewer, same paper, invited more than once (re-invitation, reassignment after
     * a cancellation, ...) creates a brand new USER_ASSIGNMENT row every time rather than
     * updating the existing one, so a reviewer can hold several assignment rows for the
     * exact same paper version. Left as raw rows, that
     * reads as unexplained duplicate titles. This groups by paper (all versions of the same
     * paper together, newest version first), and within one version orders attempts newest
     * first, flagging every attempt except the latest as superseded so the view can grey
     * them out instead of presenting them as equally "current".
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, Episciences_Paper> $papers
     * @return array<int, array<string, mixed>>
     */
    private static function enrichAndGroupRows(array $rows, array $papers): array
    {
        foreach ($rows as &$row) {
            $paper = $papers[(int)$row['docid']] ?? null;
            $row['version'] = $paper?->getVersion();
            $row['concept_identifier'] = $paper?->getConcept_identifier() ?? ('docid:' . $row['docid']);
        }
        unset($row);

        $latestDateByDocId = [];
        $latestDateByConcept = [];
        foreach ($rows as $row) {
            $date = (string)($row['assignment_date'] ?? '');
            $docId = $row['docid'];
            $concept = $row['concept_identifier'];
            if (!isset($latestDateByDocId[$docId]) || $date > $latestDateByDocId[$docId]) {
                $latestDateByDocId[$docId] = $date;
            }
            if (!isset($latestDateByConcept[$concept]) || $date > $latestDateByConcept[$concept]) {
                $latestDateByConcept[$concept] = $date;
            }
        }

        // REVIEWER_REPORT has no foreign key to a specific assignment attempt — only
        // (UID, DOCID) — so joining it fans the *same* single report out across every
        // attempt for that reviewer/paper: a paper re-invited/reassigned several times
        // showed "completed review" on every attempt even though only one report was ever
        // submitted. The most *recent* attempt is not necessarily the right owner either:
        // a report can be completed while the first assignment is active, then a *later*,
        // unrelated event (a new version requested) auto-unassigns the reviewer, creating one more row with no INVITATION_ID at all
        // and the latest WHEN — naively picking "latest" attached the completed report to
        // that unassignment row instead of the one actually active when it was submitted.
        // The correct owner is the attempt with the latest WHEN that is still <= the
        // report's own completion date; falls back to the latest attempt overall when the
        // report isn't completed yet (no date to compare against).
        $reportOwnerDateByDocId = [];
        foreach ($rows as $row) {
            $reportDate = $row['review_update_date'] !== null ? (string)$row['review_update_date'] : null;
            if ($reportDate === null) {
                continue;
            }
            $date = (string)($row['assignment_date'] ?? '');
            $docId = $row['docid'];
            if ($date <= $reportDate && (!isset($reportOwnerDateByDocId[$docId]) || $date > $reportOwnerDateByDocId[$docId])) {
                $reportOwnerDateByDocId[$docId] = $date;
            }
        }

        foreach ($rows as &$row) {
            $docId = $row['docid'];
            $date = (string)($row['assignment_date'] ?? '');
            $row['is_superseded'] = $date < $latestDateByDocId[$docId];

            $reportOwnerDate = $reportOwnerDateByDocId[$docId] ?? $latestDateByDocId[$docId];
            if ($date !== $reportOwnerDate) {
                $row['review_status'] = null;
                $row['review_update_date'] = null;
            }

            $row['status_code'] = self::reviewingStatusCode($row, $papers[(int)$docId] ?? null);

            // A superseded attempt that was never individually resolved (no report of its
            // own, not declined) reads as misleading once relabelled OBSOLETE/UNANSWERED by
            // reviewingStatusCode() — that computation reflects the *paper's current* status,
            // which is also false (canBeReviewed() === false) for a paper whose review simply
            // concluded successfully (accepted, revision requested, published, ...). Since
            // it's superseded, its outcome is definitionally superseded by whatever the later
            // attempt(s) show — a real DECLINED or COMPLETE stays as-is (a fact about this
            // specific attempt), everything else becomes a neutral "replaced" marker.
            if (
                $row['is_superseded']
                && in_array(
                    $row['status_code'],
                    [Episciences_Reviewer_Reviewing::STATUS_OBSOLETE, Episciences_Reviewer_Reviewing::STATUS_UNANSWERED],
                    true
                )
            ) {
                $row['status_code'] = self::STATUS_SUPERSEDED_REPLACED;
            }
        }
        unset($row);

        usort($rows, static function (array $a, array $b) use ($latestDateByConcept): int {
            return [$latestDateByConcept[$b['concept_identifier']], $b['concept_identifier'], (float)$b['version'], (string)$b['assignment_date']]
                <=> [$latestDateByConcept[$a['concept_identifier']], $a['concept_identifier'], (float)$a['version'], (string)$a['assignment_date']];
        });

        $previousConcept = null;
        foreach ($rows as &$row) {
            $row['is_new_paper_group'] = $row['concept_identifier'] !== $previousConcept;
            $previousConcept = $row['concept_identifier'];
        }
        unset($row);

        return $rows;
    }

    /**
     * Reproduces Episciences_Reviewer_Reviewing::loadStatus() exactly (same decision tree,
     * same Episciences_User_Assignment::STATUS_DECLINED / Episciences_Paper::canBeReviewed()
     * checks) so the detail page uses the identical vocabulary as the "Mes relectures"
     * dashboard panel, from a single raw SQL row instead of a hydrated Reviewing object.
     *
     * @param array<string, mixed> $row
     */
    private static function reviewingStatusCode(array $row, ?Episciences_Paper $paper): int
    {
        $canBeReviewed = $paper !== null && $paper->canBeReviewed();
        $reviewStatus = $row['review_status'] !== null ? (int)$row['review_status'] : null;

        if ($reviewStatus !== null) {
            if ($reviewStatus === 2) {
                return Episciences_Reviewer_Reviewing::STATUS_COMPLETE;
            }
            if (!$canBeReviewed) {
                return Episciences_Reviewer_Reviewing::STATUS_NOT_NEED_REVIEWING;
            }
            return $reviewStatus === 1 ? Episciences_Reviewer_Reviewing::STATUS_WIP : Episciences_Reviewer_Reviewing::STATUS_PENDING;
        }

        if (!$canBeReviewed) {
            return Episciences_Reviewer_Reviewing::STATUS_OBSOLETE;
        }

        return $row['assignment_status'] === Episciences_User_Assignment::STATUS_DECLINED
            ? Episciences_Reviewer_Reviewing::STATUS_DECLINED
            : Episciences_Reviewer_Reviewing::STATUS_UNANSWERED;
    }

    /**
     * Builds a compact page list for the pagination widget: first page, last page,
     * a small window around the current page, and `null` markers for the gaps
     * (rendered as an ellipsis) — avoids listing every page when there are many.
     *
     * @return list<int|null>
     */
    private static function buildPageRange(int $current, int $totalPages, int $delta = 2): array
    {
        if ($totalPages <= 1) {
            return $totalPages === 1 ? [1] : [];
        }

        $windowStart = max(2, $current - $delta);
        $windowEnd = min($totalPages - 1, $current + $delta);

        // Collapsing a single skipped page into an ellipsis saves nothing: extend the
        // window to include it instead of showing "…" in place of one page number.
        if ($windowStart === 3) {
            $windowStart = 2;
        }
        if ($windowEnd === $totalPages - 2) {
            $windowEnd = $totalPages - 1;
        }

        $range = [1];
        if ($windowStart > 2) {
            $range[] = null;
        }
        for ($page = $windowStart; $page <= $windowEnd; $page++) {
            $range[] = $page;
        }
        if ($windowEnd < $totalPages - 1) {
            $range[] = null;
        }
        $range[] = $totalPages;

        return $range;
    }

    /**
     * Administrators, chief editors and secretaries always get the global view.
     * Guest editors are always restricted; standard editors are restricted only
     * when the journal has "encapsulateEditors" enabled.
     */
    private function isRestrictedView(int $rvid): bool
    {
        if (Episciences_Auth::isAdministrator() || Episciences_Auth::isChiefEditor() || Episciences_Auth::isSecretary()) {
            return false;
        }

        if (Episciences_Auth::isGuestEditor(RVID, true)) {
            return true;
        }

        if (Episciences_Auth::isEditor(RVID, true)) {
            $review = Episciences_ReviewsManager::find($rvid);
            return $review instanceof Episciences_Review && $review->getSetting('encapsulateEditors');
        }

        return true;
    }
}
