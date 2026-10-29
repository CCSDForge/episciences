<?php
declare(strict_types=1);

namespace Episciences\Paper\Delete;

/**
 * Outcome of deleting one paper version.
 */
enum DeletionOutcome: string
{
    case Deleted = 'deleted';
    case WouldBeDeleted = 'would be deleted (dry run)';
    case NotFound = 'not found';
    case OtherJournal = 'belongs to another journal';
    case Failed = 'deletion failed, nothing was deleted';

    public function isSuccess(): bool
    {
        return $this === self::Deleted || $this === self::WouldBeDeleted;
    }
}
