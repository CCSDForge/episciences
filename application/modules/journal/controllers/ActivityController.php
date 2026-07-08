<?php
require_once APPLICATION_PATH . '/modules/common/controllers/PaperDefaultController.php';

/**
 * Class ActivityController
 * Displays the full chronological activity timeline of a paper (all versions combined).
 * Built entirely on top of Episciences_Paper::getHistory() (PAPER_LOG), the very same
 * data source as the "Historique" panel in AdministratepaperController — no new queries,
 * no duplicated permission logic (see Episciences_Paper_AccessControlControllerTrait).
 */
class ActivityController extends PaperDefaultController
{
    use Episciences_Paper_AccessControlControllerTrait;

    // resolveVersionPdfUrl() memoization: a paper with N versions can otherwise trigger
    // up to one Episciences_PapersManager::get() DB fetch per recap/review-table block
    // for the very same docid (already known from the log data)
    /** @var array<int, string|null> */
    private array $pdfUrlCache = [];

    /**
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     */
    public function viewAction(): void
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $docId = (int)$request->getParam('id');

        $review = Episciences_ReviewsManager::find(RVID);
        $review->loadSettings();
        $this->view->review = $review;

        $paper = Episciences_PapersManager::get($docId);

        if (!$paper || $paper->getRvid() !== RVID) {
            $actionName = Episciences_Auth::isAllowedToManagePaper() ? 'list' : 'assigned';
            $this->_helper->FlashMessenger->setNamespace('warning')->addMessage($this->view->translate(self::MSG_PAPER_DOES_NOT_EXIST));
            $redirectUrl = $this->_helper->url->url(['action' => $actionName, 'controller' => self::ADMINISTRATE_PAPER_CONTROLLER]);
            $this->_helper->redirector->gotoUrl($redirectUrl);
        }

        $this->redirectWithFlashMessageIfPaperIsRemovedOrDeleted($paper, false);
        $this->redirectWithFlashMessageIfConflictDetected($paper, $review);

        if ($paper->isObsolete()) {
            // the timeline covers every version of the paper regardless of which docid it was opened from:
            // always redirect to the canonical, latest-version URL
            $activityUrl = $this->_helper->url->url(['action' => 'view', 'controller' => 'activity', 'id' => $paper->getLatestVersionId()]);
            $this->_helper->redirector->gotoUrl($activityUrl);
        }

        $this->checkPermissions($review, $paper);

        $this->view->paper = $paper;
        $this->view->timelineEvents = $this->buildTimelineEvents($paper);
    }

    // Categories bundled into a single per-version recap block instead of one row per log
    // entry, mapped to their sort priority within the same version: within the same version,
    // Review must sort above Editorial regardless of either block's own timestamp — editorial
    // actions routinely trail behind review (status change, DOI, wrap-up emails, ...), which
    // would otherwise push "Éditorial" above "Évaluation" purely because it happened to be
    // touched more recently.
    // A single map (category => priority) instead of two separate lists: the set of recap
    // categories and their priorities can no longer drift apart, since there both live in the
    // exact same array — the previous split (a plain category list + a separate priority map)
    // is what let a category be added to one but not the other, which usort()'s comparator
    // needs to be a consistent total order for. Leaving one category (e.g. "Messages &
    // notifications") without a priority means it falls back to date-based comparison against
    // the other two, which — with three categories in play instead of two — can produce a
    // cycle (A before B, B before C, C before A) that makes the sort result undefined. That's
    // why this looked fixed for a 2-block version and broken as soon as a version had all
    // three category blocks.
    private const RECAP_CATEGORY_PRIORITY = [
        Episciences_Paper_Logger::CATEGORY_REVIEW => 0,
        Episciences_Paper_Logger::CATEGORY_EDITORIAL => 1,
        Episciences_Paper_Logger::CATEGORY_COMMUNICATION => 2,
    ];

    // submission/version events (new version submitted, document imported, ...) are folded into
    // the "Éditorial" recap block rather than getting their own block or standalone timeline rows
    private const RECAP_CATEGORY_ALIASES = [
        Episciences_Paper_Logger::CATEGORY_SUBMISSION => Episciences_Paper_Logger::CATEGORY_EDITORIAL,
    ];

    // review event types, in the order their columns should appear in the per-reviewer recap table
    private const REVIEW_EVENT_COLUMN_ORDER = [
        Episciences_Paper_Logger::CODE_REVIEWER_ASSIGNMENT,
        Episciences_Paper_Logger::CODE_REVIEWER_INVITATION,
        Episciences_Paper_Logger::CODE_REVIEWER_INVITATION_ACCEPTED,
        Episciences_Paper_Logger::CODE_REVIEWER_INVITATION_DECLINED,
        Episciences_Paper_Logger::CODE_REVIEWING_IN_PROGRESS,
        Episciences_Paper_Logger::CODE_REVIEWING_COMPLETED,
        Episciences_Paper_Logger::CODE_ALTER_REPORT_STATUS,
        Episciences_Paper_Logger::CODE_REVIEWER_UNASSIGNMENT,
    ];

    /**
     * Fails loudly, instead of silently mis-sorting or dropping a table column, the moment
     * one of these hand-maintained lists falls out of sync with the others:
     * - every RECAP_CATEGORY_ALIASES target must itself be a RECAP_CATEGORY_PRIORITY entry,
     *   or an aliased category (e.g. CATEGORY_SUBMISSION) would be bundled toward a recap
     *   category buildTimelineEvents()'s usort() has no priority for (see the comment on
     *   RECAP_CATEGORY_PRIORITY for the exact failure mode this causes);
     * - every REVIEW_EVENT_COLUMN_ORDER action must actually be categorized as
     *   CATEGORY_REVIEW by Episciences_Paper_Logger, or a newly added review action would
     *   silently vanish from the per-reviewer table's columns (array_intersect() in
     *   buildReviewTableBlock() only keeps actions present in this list).
     * @throws LogicException
     */
    private static function assertConstantsAreConsistent(): void
    {
        $unresolvedAliasTargets = array_diff(self::RECAP_CATEGORY_ALIASES, array_keys(self::RECAP_CATEGORY_PRIORITY));

        if ($unresolvedAliasTargets !== []) {
            throw new LogicException(
                'RECAP_CATEGORY_ALIASES targets a category missing from RECAP_CATEGORY_PRIORITY: ' . implode(', ', $unresolvedAliasTargets)
            );
        }

        foreach (self::REVIEW_EVENT_COLUMN_ORDER as $action) {
            if (Episciences_Paper_Logger::getCategory($action) !== Episciences_Paper_Logger::CATEGORY_REVIEW) {
                throw new LogicException(
                    "REVIEW_EVENT_COLUMN_ORDER lists action '$action', which Episciences_Paper_Logger no longer categorizes as CATEGORY_REVIEW"
                );
            }
        }
    }

    /**
     * Flatten Episciences_Paper::getHistory() (grouped by version) into a single
     * reverse-chronological list, tagging each entry with its version number and category.
     * Every RECAP_CATEGORY_PRIORITY entry (editorial actions, review actions, messages &
     * notifications) is bundled into a single recap block per version and per category,
     * instead of one timeline row per log entry. Submission/version events are aliased to
     * CATEGORY_EDITORIAL via RECAP_CATEGORY_ALIASES so they end up in the same "Éditorial"
     * recap block.
     * @param Episciences_Paper $paper
     * @return array<int, array<string, mixed>>
     * @throws Zend_Db_Statement_Exception
     */
    private function buildTimelineEvents(Episciences_Paper $paper): array
    {
        self::assertConstantsAreConsistent();

        $events = [];
        $logsToRecap = [];

        foreach ($paper->getHistory() ?: [] as $version => $versionData) {
            foreach ($versionData['logs'] ?? [] as $log) {
                $log['VERSION'] = $version;
                $log['CATEGORY'] = Episciences_Paper_Logger::getCategory($log['ACTION']);

                $recapCategory = self::RECAP_CATEGORY_ALIASES[$log['CATEGORY']] ?? $log['CATEGORY'];

                // Every category Episciences_Paper_Logger::getCategory() can return today
                // (CATEGORY_SUBMISSION, EDITORIAL, REVIEW, COMMUNICATION) ends up in
                // RECAP_CATEGORY_PRIORITY after aliasing, so this branch never fires in
                // practice — it's kept as a safety net for a future Logger category that
                // isn't wired into RECAP_CATEGORY_ALIASES/RECAP_CATEGORY_PRIORITY yet,
                // rendered as a standalone row via activity_timeline_item.phtml instead of
                // being silently dropped.
                if (array_key_exists($recapCategory, self::RECAP_CATEGORY_PRIORITY)) {
                    $logsToRecap[$version][$recapCategory][] = $log;
                    continue;
                }

                $events[] = $log;
            }
        }

        foreach ($logsToRecap as $version => $logsByCategory) {
            foreach ($logsByCategory as $category => $logs) {
                if ($category === Episciences_Paper_Logger::CATEGORY_REVIEW) {
                    $events[] = $this->buildReviewTableBlock((string)$version, $logs);
                    continue;
                }
                $events[] = $this->buildRecapBlock((string)$version, $category, $logs);
            }
        }

        usort($events, static function (array $a, array $b): int {
            if (($a['VERSION'] ?? null) === ($b['VERSION'] ?? null)) {
                $aPriority = self::RECAP_CATEGORY_PRIORITY[$a['CATEGORY'] ?? null] ?? null;
                $bPriority = self::RECAP_CATEGORY_PRIORITY[$b['CATEGORY'] ?? null] ?? null;

                if ($aPriority !== null && $bPriority !== null && $aPriority !== $bPriority) {
                    return $aPriority <=> $bPriority;
                }
            }

            return [$b['DATE'], $b['LOGID'] ?? 0] <=> [$a['DATE'], $a['LOGID'] ?? 0];
        });

        return $events;
    }

    /**
     * Build a single recap block for one version/category, bundling every matching log
     * entry so the timeline shows one card per version instead of one per action.
     * @param array<int, array<string, mixed>> $logs
     * @return array<string, mixed>
     * @throws Zend_Db_Statement_Exception
     */
    private function buildRecapBlock(string $version, string $category, array $logs): array
    {
        usort($logs, static function (array $a, array $b): int {
            return [$b['DATE'], $b['LOGID']] <=> [$a['DATE'], $a['LOGID']];
        });

        $docId = (int)$logs[0]['DOCID'];

        return [
            'IS_RECAP' => true,
            'VERSION' => $version,
            'CATEGORY' => $category,
            'DATE' => $logs[0]['DATE'],
            // every log in this bucket was grouped under the same version, i.e. the same
            // DOCID (see Episciences_Paper::loadHistory()) — used to link to that version's
            // PDF/admin page from the timeline's date column (activity_timeline_date.phtml)
            'DOCID' => $docId,
            'PDF_URL' => $this->resolveVersionPdfUrl($docId),
            'LOGS' => $logs,
        ];
    }

    /**
     * Build a per-reviewer recap table for one version: one row per reviewer, one column
     * per event type they went through (with its date), plus a clearly visible "status"
     * column reflecting their most recent event.
     * @param array<int, array<string, mixed>> $logs review-category log entries for this version
     * @return array<string, mixed>
     * @throws Zend_Db_Statement_Exception
     */
    private function buildReviewTableBlock(string $version, array $logs): array
    {
        // ascending: each reviewer's last write below ends up being their most recent event
        usort($logs, static function (array $a, array $b): int {
            return [$a['DATE'], $a['LOGID']] <=> [$b['DATE'], $b['LOGID']];
        });

        $reviewers = [];
        $actionsPresent = [];
        $maxDate = $logs[0]['DATE'];

        foreach ($logs as $log) {
            $data = Episciences_Paper_Logger::extractLogDisplayData($log);
            // Three different shapes carry a reviewer's identity, depending on which
            // action wrote the log:
            // - CODE_REVIEWER_INVITATION stores 'uid' as a sibling of 'detail.user' (see
            //   AdministratepaperController::invitereviewerAction()), not nested under
            //   detail.user.uid;
            // - most other review-action logs nest it under detail.user.uid, via
            //   Episciences_User::toArray() (Ccsd_User_Models_User::toArray());
            // - a reviewer who hasn't registered an account yet (invited via a
            //   provisional USER_TMP row) is logged via Episciences_User_Tmp::toArray()
            //   (see ReviewerController::decline()), which uses 'id' instead of 'uid'.
            // Only used per-event below (e.g. to deep-link a completed review to its
            // report) — NOT for grouping rows (see $reviewerKey below).
            $uid = $data['detail']['user']['uid'] ?? $data['detail']['uid'] ?? $data['detail']['user']['id'] ?? null;

            // Grouped by name, not uid: accepting an invitation sent to a not-yet-registered
            // reviewer (tmp_user) mints a brand new real account with a real uid that has no
            // numeric relationship to the provisional USER_TMP id used at invitation time —
            // e.g. tmp uid "6371" at invitation vs real uid 1614081 once accepted, for the
            // same physical reviewer (confirmed against production PAPER_LOG rows). No
            // identifier survives that transition except the name, so name is the only signal
            // that reliably re-unites a reviewer's pre- and post-registration events into one
            // row. The trade-off: two genuinely different reviewers who happen to share the
            // exact same displayed name will incorrectly merge into a single row — judged far
            // rarer in practice than the tmp-to-real-account split above.
            $reviewerKey = self::reviewerMatchKey($data['fullName']);

            if (!isset($reviewers[$reviewerKey])) {
                $reviewers[$reviewerKey] = [
                    'name' => $data['fullName'],
                    'events' => [],
                ];
            }

            $reviewers[$reviewerKey]['events'][$log['ACTION']] = [
                'date' => $log['DATE'],
                'logid' => $log['LOGID'],
                'docid' => $log['DOCID'],
                // Ccsd_User_Models_User::toArray() (used when this log entry was written)
                // stores the uid in lowercase, unlike most PAPER_LOG/DB columns
                'uid' => $uid,
            ];
            // overwritten on every iteration: ends up holding the chronologically last event's
            // action and display name — the invitation-time name is often the raw text typed
            // into the invite form (stray whitespace, inconsistent capitalization), while a
            // later event's name comes from the reviewer's own registered account once they
            // have one, which is the more trustworthy display value
            $reviewers[$reviewerKey]['statusAction'] = $log['ACTION'];
            $reviewers[$reviewerKey]['name'] = $data['fullName'];

            $actionsPresent[$log['ACTION']] = true;

            if ($log['DATE'] > $maxDate) {
                $maxDate = $log['DATE'];
            }
        }

        uasort($reviewers, static fn(array $a, array $b): int => $a['name'] <=> $b['name']);

        $docId = (int)$logs[0]['DOCID'];

        return [
            'IS_REVIEW_TABLE' => true,
            'VERSION' => $version,
            'CATEGORY' => Episciences_Paper_Logger::CATEGORY_REVIEW,
            'DATE' => $maxDate,
            'DOCID' => $docId,
            'PDF_URL' => $this->resolveVersionPdfUrl($docId),
            'COLUMNS' => array_values(array_intersect(self::REVIEW_EVENT_COLUMN_ORDER, array_keys($actionsPresent))),
            'REVIEWERS' => $reviewers,
        ];
    }

    /**
     * Where to send the timeline's "PDF" quick link for a given version. Mirrors the public
     * article page's own rule (see `episciences/status = 16` check in public/xsl/full_paper.xsl):
     * a published version is served through Episciences' own PDF proxy (/paper/pdf), but any
     * other version (in progress, obsolete, ...) has no local copy to proxy, so this links
     * straight to that version's file on the open archive instead.
     * @throws Zend_Db_Statement_Exception
     */
    private function resolveVersionPdfUrl(int $docId): ?string
    {
        if (array_key_exists($docId, $this->pdfUrlCache)) {
            return $this->pdfUrlCache[$docId];
        }

        $versionPaper = Episciences_PapersManager::get($docId, false);

        if (!$versionPaper) {
            return $this->pdfUrlCache[$docId] = null;
        }

        if ($versionPaper->getStatus() === Episciences_Paper::STATUS_PUBLISHED) {
            return $this->pdfUrlCache[$docId] = '/paper/pdf?id=' . $docId;
        }

        return $this->pdfUrlCache[$docId] = $versionPaper->getMainPaperUrl();
    }

    /**
     * Normalizes a reviewer's display name for grouping purposes: case, whitespace
     * (incl. non-breaking spaces), accents, and word order insensitive.
     *
     * This is buildReviewTableBlock()'s only grouping key (not just a uid fallback): a
     * reviewer's uid is not a stable identifier across their whole history on a paper —
     * accepting an invitation sent to a not-yet-registered reviewer mints a brand new real
     * account whose uid has no relationship to the provisional id used at invitation time.
     * The name is the only signal that survives that transition. Trade-off accepted: two
     * distinct reviewers who happen to share the exact same normalized name (e.g. one named
     * "Marie Dupont", another "Dupont Marie") would incorrectly merge into one row.
     * @param string $fullName
     * @return string
     */
    private static function reviewerMatchKey(string $fullName): string
    {
        $normalized = str_replace("\xC2\xA0", ' ', $fullName);
        // stripped the same way as Episciences_Reviewer_AccountResolver::normalize()'s own
        // name-matching key, so a reviewer isn't deduped on one code path and not the other
        // purely because of an accented character
        $normalized = Ccsd_Tools::stripAccents($normalized);
        $words = preg_split('/\s+/', mb_strtolower(trim($normalized)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        sort($words);

        return implode(' ', $words);
    }
}
