<?php

declare(strict_types=1);

namespace Episciences\Paper\GraphicalAbstract;

use Episciences_Auth;
use Episciences_Paper;

/**
 * Who may add, replace or delete the illustration of a paper version.
 *
 * Only the version in progress (the latest one, not closed by a final status) can be changed,
 * by the secretaries, by the editors and guest editors assigned to the paper, and by its author
 * and co-authors. A published version is
 * the exception: only the chief editor (or an equivalent role) may correct it after publication.
 * Nobody changes a refused version, an obsolete version, or a version that is no longer active.
 */
final class GraphicalAbstractPolicy
{
    /** Final statuses: the version is closed and its illustration is frozen */
    private const CLOSED_STATUSES = [
        Episciences_Paper::STATUS_REFUSED,
        Episciences_Paper::STATUS_OBSOLETE,
        Episciences_Paper::STATUS_REMOVED,
        Episciences_Paper::STATUS_DELETED,
        Episciences_Paper::STATUS_ABANDONED,
    ];

    public static function canEdit(Episciences_Paper $paper): bool
    {
        return self::isAllowed(
            $paper->getStatus(),
            $paper->isLatestVersion(),
            Episciences_Auth::isSecretary(),
            self::isAssignedEditor($paper),
            $paper->isOwnerOrCoAuthor(),
            Episciences_Auth::isAdministrator()
        );
    }

    /**
     * @param int $status status of the paper version
     * @param bool $isLatestVersion the version is the latest one of its paper
     * @param bool $isSecretary the user is a secretary (or an administrator) of the journal: not limited to assigned papers
     * @param bool $isAssignedEditor the user is an editor or guest editor assigned to the paper
     * @param bool $isOwnerOrCoAuthor the user is the owner or a co-author of the paper
     * @param bool $isChiefEditor the user is a chief editor, an administrator or has a similar role
     */
    public static function isAllowed(
        int  $status,
        bool $isLatestVersion,
        bool $isSecretary,
        bool $isAssignedEditor,
        bool $isOwnerOrCoAuthor,
        bool $isChiefEditor
    ): bool
    {
        if (!$isLatestVersion || in_array($status, self::CLOSED_STATUSES, true)) {
            return false;
        }

        if ($status === Episciences_Paper::STATUS_PUBLISHED) {
            return $isChiefEditor;
        }

        return $isSecretary || $isAssignedEditor || $isOwnerOrCoAuthor;
    }

    /**
     * Guest editors are assigned to a paper like editors.
     */
    private static function isAssignedEditor(Episciences_Paper $paper): bool
    {
        return Episciences_Auth::isLogged() && $paper->isEditor((int)Episciences_Auth::getUid());
    }
}
