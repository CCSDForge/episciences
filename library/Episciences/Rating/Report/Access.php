<?php

/**
 * Access control for rating report attachments.
 *
 * Centralizes the logic that determines whether a user may download
 * a report attachment. This logic aligns with the report display gate
 * that filters reports by user role and criterion visibility.
 *
 * Règles d'accès aux pièces jointes des rapports :
 *
 * - Conflit d'intérêts (COI) : si l'utilisateur a déclaré un conflit
 *   d'intérêts pour ce papier, l'accès est refusé quel que soit son rôle.
 *
 * - Auteur du rapport (reviewer) : accès complet à son propre rapport,
 *   y compris en brouillon (WIP). Le reviewer peut toujours accéder
 *   à ses propres pièces jointes pendant la rédaction.
 *
 * - Staff éditorial (secrétaire, rédacteur, etc.) : accès uniquement
 *   aux rapports finalisés (COMPLETED). Les rapports en brouillon (WIP)
 *   sont privés et ne sont pas visibles par l'équipe éditoriale.
 *
 * - Auteur de l'article : accès aux rapports finalisés (COMPLETED)
 *   uniquement lorsque le statut de l'article le permet (après décision
 *   éditoriale). L'accès est limité aux critères avec visibilité
 *   PUBLIC ou CONTRIBUTOR.
 *
 * - Utilisateur anonyme : pas d'accès (authentification requise).
 *
 * @see AdministratepaperController::isAllowedToSeeReportDetails() for editorial staff access
 * @see Episciences_Paper::filterReportsByUserRole() for visibility filtering
 */
class Episciences_Rating_Report_Access
{
    // Role constants for access resolution
    public const ROLE_EDITORIAL_STAFF = 'editorial_staff';
    public const ROLE_REPORT_AUTHOR = 'report_author';
    public const ROLE_PAPER_AUTHOR = 'paper_author';

    /**
     * Determines whether a user may download a specific attachment from a report.
     *
     * All role-related parameters are resolved by the caller (typically the controller)
     * to keep this class free of static dependencies and fully testable.
     *
     * @param Episciences_Paper $paper The paper the report belongs to
     * @param Episciences_Rating_Report $report The rating report
     * @param string $filename The attachment filename being requested
     * @param int|null $uid The user ID (null if not logged in)
     * @param Episciences_Review $review The review with loaded settings
     * @param bool $isSecretary Whether the user is a secretary
     * @param bool $isGuestEditor Whether the user is a guest editor
     * @param bool $isEditor Whether the user is an editor (global role)
     * @param bool $isCopyEditor Whether the user is a copy editor (global role)
     * @param bool $isReportsVisibleToAuthor Whether reports are visible to the paper author
     * @param bool $hasConflict Whether the user has declared a conflict of interest for this paper
     * @return bool True if the user may download the attachment
     */
    public static function mayDownloadAttachment(
        Episciences_Paper $paper,
        Episciences_Rating_Report $report,
        string $filename,
        ?int $uid,
        Episciences_Review $review,
        bool $isSecretary,
        bool $isGuestEditor,
        bool $isEditor,
        bool $isCopyEditor,
        bool $isReportsVisibleToAuthor,
        bool $hasConflict = false
    ): bool {
        // User with declared conflict of interest cannot access reports
        if ($hasConflict) {
            return false;
        }

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

        // Auteur du rapport (reviewer) : accès complet à son propre rapport, y compris en brouillon
        if ($role === self::ROLE_REPORT_AUTHOR) {
            return true;
        }

        // Staff éditorial : accès uniquement aux rapports finalisés (COMPLETED)
        // Les rapports en brouillon (WIP) sont privés pour le reviewer
        if ($role === self::ROLE_EDITORIAL_STAFF) {
            return $report->getStatus() === Episciences_Rating_Report::STATUS_COMPLETED;
        }

        // Paper author: check report status, paper status visibility, and criterion visibility
        if ($role === self::ROLE_PAPER_AUTHOR) {
            // Block access to reopened (WIP) or pending reports
            if ($report->getStatus() !== Episciences_Rating_Report::STATUS_COMPLETED) {
                return false;
            }
            if (!$isReportsVisibleToAuthor) {
                return false;
            }
            $visibility = self::findAttachmentVisibility($report, $filename);
            return self::mayReadCriterion(self::ROLE_PAPER_AUTHOR, $visibility);
        }

        // No matching role: deny access (requires authentication)
        return false;
    }

    /**
     * Resolves the effective role of a user with respect to a report.
     *
     * Precedence (highest to lowest):
     * 1. Editorial staff (secretary, editor, copy editor, guest editor)
     * 2. Report author (reviewer who authored or on-behalf)
     * 3. Paper author (depositor)
     *
     * Returns null for unauthenticated users or users with no recognized role.
     *
     * @param Episciences_Paper $paper The paper
     * @param Episciences_Rating_Report $report The report
     * @param int|null $uid The user ID (null if not logged in)
     * @param Episciences_Review $review The review with loaded settings
     * @param bool $isSecretary Whether the user is a secretary
     * @param bool $isGuestEditor Whether the user is a guest editor
     * @param bool $isEditor Whether the user is an editor (global role)
     * @param bool $isCopyEditor Whether the user is a copy editor (global role)
     * @return string|null One of the ROLE_* constants, or null if no role applies
     */
    public static function resolveRole(
        Episciences_Paper $paper,
        Episciences_Rating_Report $report,
        ?int $uid,
        Episciences_Review $review,
        bool $isSecretary,
        bool $isGuestEditor,
        bool $isEditor,
        bool $isCopyEditor
    ): ?string {
        // Check editorial staff first
        if (self::isEditorialStaff($paper, $uid, $review, $isSecretary, $isGuestEditor, $isEditor, $isCopyEditor)) {
            return self::ROLE_EDITORIAL_STAFF;
        }

        // Check report author (reviewer)
        if ($uid !== null && $uid !== 0) {
            if ((int)$report->getUid() === $uid || (int)$report->getOnbehalf_uid() === $uid) {
                return self::ROLE_REPORT_AUTHOR;
            }
        }

        // Check paper author
        if ($uid !== null && $uid !== 0 && (int)$paper->getUid() === $uid) {
            return self::ROLE_PAPER_AUTHOR;
        }

        return null;
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
        if ($criteria === null) {
            return null;
        }

        foreach ($criteria as $criterion) {
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
     * @param bool $isSecretary Whether the user is a secretary
     * @param bool $isGuestEditor Whether the user is a guest editor
     * @param bool $isEditor Whether the user is an editor (global role)
     * @param bool $isCopyEditor Whether the user is a copy editor (global role)
     * @return bool True if the user is editorial staff
     */
    private static function isEditorialStaff(
        Episciences_Paper $paper,
        ?int $uid,
        Episciences_Review $review,
        bool $isSecretary,
        bool $isGuestEditor,
        bool $isEditor,
        bool $isCopyEditor
    ): bool {
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
