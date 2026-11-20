<?php

declare(strict_types=1);

namespace Episciences\Reviewer;

/**
 * What the invitation page shows of a reviewer's workload (git #320), the same in the known reviewers table and above
 * the invitation message: the counts, and the papers the logged user may manage. The other papers are only counted,
 * so that nothing is disclosed about them.
 */
final class WorkloadPresenter
{
    /** Papers listed in the known reviewers table; the alert of the invitation step lists them all */
    public const TABLE_PAPERS = 3;

    /** @var array<int, true> */
    private readonly array $visible;

    /**
     * @param list<int> $visibleDocIds the papers the logged user may manage
     * @param callable(string): string $formatDate date as written on the page
     */
    public function __construct(array $visibleDocIds, private $formatDate)
    {
        $this->visible = array_fill_keys($visibleDocIds, true);
    }

    /**
     * @return array{
     *     reviews_in_progress: int, pending_invitations: int, last_invitation_date: string|null,
     *     last_invitation_answer: string|null, last_invitation_date_label: string|null,
     *     last_invitation_paper: array{docid: int, url: string}|null,
     *     papers: list<array{docid: int, url: string, kind: string, date_label: string|null}>,
     *     other_papers: int
     * }
     */
    public function present(Workload $workload): array
    {
        $papers = [];
        $otherPapers = 0;

        foreach ($workload->getPapers() as $paper) {
            $visible = $this->visiblePaper($paper['docid']);
            if ($visible === null) {
                $otherPapers++;
                continue;
            }

            $papers[] = $visible + [
                'kind' => $paper['kind'],
                'date_label' => $paper['date'] !== null ? $this->formatDate($paper['date']) : null,
            ];
        }

        $lastDocId = $workload->getLastInvitationDocId();
        $lastDate = $workload->getLastInvitationDate();

        return $workload->toArray() + [
            'last_invitation_date_label' => $lastDate !== null ? $this->formatDate($lastDate) : null,
            'last_invitation_paper' => $lastDocId !== null ? $this->visiblePaper($lastDocId) : null,
            'papers' => $papers,
            'other_papers' => $otherPapers,
        ];
    }

    /**
     * @return array{docid: int, url: string}|null
     */
    private function visiblePaper(int $docId): ?array
    {
        if (!isset($this->visible[$docId])) {
            return null;
        }

        return ['docid' => $docId, 'url' => '/administratepaper/view/id/' . $docId];
    }

    private function formatDate(string $date): string
    {
        return ($this->formatDate)($date);
    }
}
