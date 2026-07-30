<?php

/**
 * Access control for rating report attachments.
 *
 * Centralizes the logic that determines whether a user may download
 * a report attachment, aligning the download guard with the display
 * gate in PaperController::viewAction().
 *
 * @see PaperController::viewAction() for $isVisibleRatings (line 222)
 * @see PaperController::viewAction() for $commonTest (line 240)
 */
class Episciences_Rating_Report_Access
{
    // Role constants for access resolution
    public const ROLE_EDITORIAL_STAFF = 'editorial_staff';
    public const ROLE_REPORT_AUTHOR = 'report_author';
    public const ROLE_PAPER_AUTHOR = 'paper_author';
    public const ROLE_PUBLIC = 'public';

    /**
     * Determines whether a user may download a specific attachment from a report.
     *
     * @param Episciences_Paper $paper The paper the report belongs to
     * @param Episciences_Rating_Report $report The rating report
     * @param string $filename The attachment filename being requested
     * @param int|null $uid The user ID (null if not logged in)
     * @param Episciences_Review $review The review with loaded settings
     * @param bool|null $isSecretary Override for testing (null = use Auth)
     * @param bool|null $isGuestEditor Override for testing (null = use Auth)
     * @param bool|null $isEditor Override for testing (null = use Auth)
     * @param bool|null $isCopyEditor Override for testing (null = use Auth)
     * @return bool True if the user may download the attachment
     */
    public static function mayDownloadAttachment(
        Episciences_Paper $paper,
        Episciences_Rating_Report $report,
        string $filename,
        ?int $uid,
        Episciences_Review $review,
        ?bool $isSecretary = null,
        ?bool $isGuestEditor = null,
        ?bool $isEditor = null,
        ?bool $isCopyEditor = null
    ): bool {
        $role = self::resolveRole(
            $paper,
            $report,
            $uid,
            $review,
            $isSecretary,
            $isGuestEditor,
            $isEditor,
            $isCopyEditor
        );

        // Editorial staff and report author have full access to the report
        if ($role === self::ROLE_EDITORIAL_STAFF || $role === self::ROLE_REPORT_AUTHOR) {
            return true;
        }

        // Paper author: check status visibility and criterion visibility
        if ($role === self::ROLE_PAPER_AUTHOR) {
            if (!$paper->isReportsVisibleToAuthor()) {
                return false;
            }
            $visibility = self::findAttachmentVisibility($report, $filename);
            return self::mayReadCriterion(self::ROLE_PAPER_AUTHOR, $visibility);
        }

        // Public: check open peer review settings and criterion visibility
        if ($role === self::ROLE_PUBLIC) {
            $showRatings = $review->getSetting(Episciences_Review::SETTING_SHOW_RATINGS);
            if (!$showRatings || !$paper->isPublished()) {
                return false;
            }
            $visibility = self::findAttachmentVisibility($report, $filename);
            return self::mayReadCriterion(self::ROLE_PUBLIC, $visibility);
        }

        return false;
    }

    /**
     * Resolves the effective role of a user with respect to a report.
     *
     * Precedence (highest to lowest):
     * 1. Editorial staff (secretary, editor, copy editor, guest editor)
     * 2. Report author (reviewer who authored or on-behalf)
     * 3. Paper author (depositor)
     * 4. Public (everyone else, including anonymous)
     *
     * @param Episciences_Paper $paper The paper
     * @param Episciences_Rating_Report|null $report The report (null if checking general access)
     * @param int|null $uid The user ID (null if not logged in)
     * @param Episciences_Review $review The review with loaded settings
     * @param bool|null $isSecretary Override for testing (null = use Auth)
     * @param bool|null $isGuestEditor Override for testing (null = use Auth)
     * @param bool|null $isEditor Override for testing (null = use Auth)
     * @param bool|null $isCopyEditor Override for testing (null = use Auth)
     * @return string One of the ROLE_* constants
     */
    public static function resolveRole(
        Episciences_Paper $paper,
        ?Episciences_Rating_Report $report,
        ?int $uid,
        Episciences_Review $review,
        ?bool $isSecretary = null,
        ?bool $isGuestEditor = null,
        ?bool $isEditor = null,
        ?bool $isCopyEditor = null
    ): string {
        // Check editorial staff first
        if (self::isEditorialStaff($paper, $uid, $review, $isSecretary, $isGuestEditor, $isEditor, $isCopyEditor)) {
            return self::ROLE_EDITORIAL_STAFF;
        }

        // Check report author (reviewer)
        if ($report !== null && $uid !== null && $uid !== 0) {
            if ((int)$report->getUid() === $uid || (int)$report->getOnbehalf_uid() === $uid) {
                return self::ROLE_REPORT_AUTHOR;
            }
        }

        // Check paper author
        if ($uid !== null && $uid !== 0 && (int)$paper->getUid() === $uid) {
            return self::ROLE_PAPER_AUTHOR;
        }

        return self::ROLE_PUBLIC;
    }

    /**
     * Checks whether a role may read a criterion with the given visibility.
     *
     * Access rules:
     * - editorial_staff: all visibilities (including null)
     * - report_author: all visibilities (including null)
     * - paper_author: public, contributor only
     * - public: public only
     *
     * @param string $role One of the ROLE_* constants
     * @param string|null $visibility The criterion visibility (null if unknown)
     * @return bool True if the role may read the criterion
     */
    public static function mayReadCriterion(string $role, ?string $visibility): bool
    {
        // Editorial staff and report author can read everything
        if ($role === self::ROLE_EDITORIAL_STAFF || $role === self::ROLE_REPORT_AUTHOR) {
            return true;
        }

        // Paper author can read PUBLIC and CONTRIBUTOR
        if ($role === self::ROLE_PAPER_AUTHOR) {
            return $visibility === Episciences_Rating_Criterion::VISIBILITY_PUBLIC
                || $visibility === Episciences_Rating_Criterion::VISIBILITY_CONTRIBUTOR;
        }

        // Public can only read PUBLIC
        if ($role === self::ROLE_PUBLIC) {
            return $visibility === Episciences_Rating_Criterion::VISIBILITY_PUBLIC;
        }

        return false;
    }

    /**
     * Finds the visibility of the criterion that carries a specific attachment.
     *
     * @param Episciences_Rating_Report $report The report containing criteria
     * @param string $filename The attachment filename to find
     * @return string|null The visibility constant, or null if not found
     */
    public static function findAttachmentVisibility(
        Episciences_Rating_Report $report,
        string $filename
    ): ?string {
        $criteria = $report->getCriteria();
        if (!is_array($criteria)) {
            return null;
        }

        foreach ($criteria as $criterion) {
            if (!$criterion instanceof Episciences_Rating_Criterion) {
                continue;
            }
            if ($criterion->getAttachment() === $filename) {
                return $criterion->getVisibility();
            }
        }

        return null;
    }

    /**
     * Checks whether the user is part of editorial staff for the paper.
     *
     * Respects encapsulation settings for editors and copy editors.
     *
     * @param Episciences_Paper $paper The paper
     * @param int|null $uid The user ID
     * @param Episciences_Review $review The review with loaded settings
     * @param bool|null $isSecretary Override for testing (null = use Auth)
     * @param bool|null $isGuestEditor Override for testing (null = use Auth)
     * @param bool|null $isEditor Override for testing (null = use Auth)
     * @param bool|null $isCopyEditor Override for testing (null = use Auth)
     * @return bool True if the user is editorial staff
     */
    private static function isEditorialStaff(
        Episciences_Paper $paper,
        ?int $uid,
        Episciences_Review $review,
        ?bool $isSecretary = null,
        ?bool $isGuestEditor = null,
        ?bool $isEditor = null,
        ?bool $isCopyEditor = null
    ): bool {
        // Use Auth if not overridden (for testing)
        $isSecretary = $isSecretary ?? Episciences_Auth::isSecretary();
        $isGuestEditor = $isGuestEditor ?? Episciences_Auth::isGuestEditor();
        $isEditor = $isEditor ?? Episciences_Auth::isEditor();
        $isCopyEditor = $isCopyEditor ?? Episciences_Auth::isCopyEditor();

        // Secretary always has access
        if ($isSecretary) {
            return true;
        }

        // Guest editor (global, not paper-specific)
        if ($isGuestEditor) {
            return true;
        }

        // Editor: check encapsulation setting
        $encapsulateEditors = $review->getSetting(Episciences_Review::SETTING_ENCAPSULATE_EDITORS);
        if (!$encapsulateEditors && $isEditor) {
            return true;
        }

        // Copy editor: check encapsulation setting
        $encapsulateCopyEditors = $review->getSetting(Episciences_Review::SETTING_ENCAPSULATE_COPY_EDITORS);
        if (!$encapsulateCopyEditors && $isCopyEditor) {
            return true;
        }

        // Paper-specific assignments (always allowed regardless of encapsulation)
        if ($uid !== null && $uid !== 0) {
            try {
                if ($paper->getEditor($uid)) {
                    return true;
                }
                if ($paper->getCopyEditor($uid)) {
                    return true;
                }
            } catch (Zend_Db_Statement_Exception $e) {
                trigger_error($e->getMessage());
            }
        }

        return false;
    }
}