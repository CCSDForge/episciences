<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

/**
 * Conflict-of-interest (COI) rule applied to the papers behind the reviewer statistics.
 *
 * Mirrors the rest of the application when the journal has COI enabled:
 * - editorial staff (who can declare a conflict) only see the reviewers of papers for which
 *   they confirmed having no conflict (same rule as the "reviewer" filter of the paper lists,
 *   Episciences_PapersManager::fetchPapersWithNoConflictsConfirmation());
 * - administrators without an editorial role cannot declare a conflict: only papers they are
 *   recorded as having a conflict with are hidden (same rule as the paper page access check).
 */
enum StatsCoiFilter
{
    /** COI disabled for the journal, or root: no paper hidden. */
    case None;

    /** Only papers the viewer answered "no conflict" for. */
    case ConfirmedNoConflictOnly;

    /** Every paper except those the viewer declared a conflict with. */
    case ExcludeDeclaredConflicts;

    public static function resolve(bool $isCoiEnabled, bool $isRoot, bool $canDeclareConflict): self
    {
        if (!$isCoiEnabled || $isRoot) {
            return self::None;
        }

        return $canDeclareConflict ? self::ConfirmedNoConflictOnly : self::ExcludeDeclaredConflicts;
    }
}
