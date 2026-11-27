<?php

declare(strict_types=1);

namespace Episciences\Submit\Checklist;

/**
 * Decides which checkboxes the submission forms show for a journal:
 * 1. its own list (REVIEW_SETTING row of the journal) when it has at least one checkbox;
 * 2. otherwise the default list (row of the portal, RVID = 0);
 * 3. otherwise [] : the caller uses the code fallback (DefaultChecklist).
 *
 * The rule lives only here: Submit.php, ChecklistFormBuilder and the admin page never read
 * the stored lists themselves. No cache: at most two indexed queries on REVIEW_SETTING.
 */
final class ChecklistProvider
{
    /** RVID of the portal, whose row holds the default list of all journals */
    public const DEFAULT_RVID = 0;

    public function __construct(
        private readonly ChecklistRepository $repository,
        private readonly ?DefaultChecklist $defaultChecklist = null,
    ) {
    }

    /**
     * Provider reading the application database.
     */
    public static function create(): self
    {
        return new self(new ChecklistRepository());
    }

    /**
     * Checkboxes shown to the authors of the journal, in order: its own list, else the default list,
     * else [] (code fallback: disclaimer1 / disclaimer2).
     *
     * @return list<ChecklistItem>
     */
    public function items(int $rvid): array
    {
        $journalItems = $this->storedItems($rvid);

        return $journalItems !== [] ? $journalItems : $this->storedItems(self::DEFAULT_RVID);
    }

    /**
     * Whether the journal has its own list (the default list does not count).
     */
    public function isCustomised(int $rvid): bool
    {
        return $rvid !== self::DEFAULT_RVID && $this->storedItems($rvid) !== [];
    }

    /**
     * The default list: copied into a journal when it clicks "Customise", shown read-only before.
     * Keys of the stored default list are kept, so a fresh copy keeps the same element IDs.
     * Without a stored default list: the code texts, without key.
     *
     * @return list<ChecklistItem>
     */
    public function defaultItems(): array
    {
        $items = $this->storedItems(self::DEFAULT_RVID);
        if ($items !== []) {
            return $items;
        }

        return ($this->defaultChecklist ?? new DefaultChecklist())->toPrefill();
    }

    /**
     * Checkboxes stored for $rvid; [] when nothing is stored or the JSON is invalid.
     *
     * @return list<ChecklistItem>
     */
    private function storedItems(int $rvid): array
    {
        return $this->repository->find($rvid)->items ?? [];
    }
}
