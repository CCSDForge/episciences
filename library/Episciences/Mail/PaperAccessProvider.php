<?php

declare(strict_types=1);

namespace Episciences\Mail;

/**
 * Facts about the papers of the current journal, from the point of view of the logged user.
 * Kept apart from RecipientAccessPolicy so that the policy has no I/O.
 */
interface PaperAccessProvider
{
    /**
     * @return ?int the PAPERID of the document when it belongs to the current journal, null otherwise
     */
    public function findPaperId(int $docId): ?int;

    /** The logged user is an active editor or copy editor of the document (or of its latest version) */
    public function isAssigned(int $docId): bool;

    /** The logged user is the submitter or a co-author of the document: they may not manage it */
    public function isAuthor(int $docId): bool;

    /** The logged user may not see the document because of a conflict of interest */
    public function hasConflict(int $docId): bool;

    /**
     * @return list<int> PAPERIDs the logged user is assigned to as an editor
     */
    public function assignedPaperIds(): array;

    /**
     * @return list<int> PAPERIDs for which the logged user confirmed the absence of conflict of interest
     */
    public function paperIdsWithoutConflict(): array;
}
