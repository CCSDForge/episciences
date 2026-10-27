<?php
declare(strict_types=1);

namespace Episciences\Paper\Delete;

use Closure;
use Episciences_Paper;
use Episciences_PapersManager;

/**
 * Deletes paper versions of one journal, refusing those of any other journal.
 */
final class PaperDeleter
{
    /** @var Closure(int): bool */
    private readonly Closure $delete;

    /**
     * @param Closure(int): bool|null $delete deletes a paper version, returns false on failure;
     *                                        Episciences_PapersManager::delete() by default
     */
    public function __construct(private readonly int $rvid, ?Closure $delete = null)
    {
        $this->delete = $delete ?? static fn(int $docId): bool => Episciences_PapersManager::delete($docId);
    }

    public function delete(int $docId, bool $dryRun = false): DeletionOutcome
    {
        $paper = Episciences_PapersManager::get($docId, false);

        if (!$paper instanceof Episciences_Paper) {
            return DeletionOutcome::NotFound;
        }

        if ($paper->getRvid() !== $this->rvid) {
            return DeletionOutcome::OtherJournal;
        }

        if ($dryRun) {
            return DeletionOutcome::WouldBeDeleted;
        }

        return ($this->delete)($docId) ? DeletionOutcome::Deleted : DeletionOutcome::Failed;
    }
}
