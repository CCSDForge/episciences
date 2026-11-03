<?php
declare(strict_types=1);

namespace Episciences\Paper\Delete;

use Closure;
use Episciences_Paper;
use Episciences_PapersManager;
use Throwable;

/**
 * Deletes paper versions of one journal, refusing those of any other journal.
 */
final class PaperDeleter
{
    /** @var Closure(Episciences_Paper): void */
    private readonly Closure $delete;

    /**
     * @param Closure(Episciences_Paper): void|null $delete deletes a paper version, throws on failure;
     *                                                     Episciences_PapersManager::deletePaper() by default
     */
    public function __construct(private readonly int $rvid, ?Closure $delete = null)
    {
        $this->delete = $delete ?? Episciences_PapersManager::deletePaper(...);
    }

    /**
     * @throws Throwable when the deletion fails: nothing was deleted
     */
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

        ($this->delete)($paper);

        return DeletionOutcome::Deleted;
    }
}
