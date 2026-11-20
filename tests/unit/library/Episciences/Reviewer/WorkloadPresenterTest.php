<?php

declare(strict_types=1);

namespace unit\library\Episciences\Reviewer;

use Episciences\Reviewer\Workload;
use Episciences\Reviewer\WorkloadPresenter;
use PHPUnit\Framework\TestCase;

/**
 * What the invitation page shows of a reviewer's workload (git #320): the papers the logged user may manage, the
 * others only counted.
 */
final class WorkloadPresenterTest extends TestCase
{
    private static function workload(): Workload
    {
        return new Workload(2, 1, '2026-11-18 09:00:00', Workload::ANSWER_DECLINED, [
            ['docid' => 10, 'kind' => Workload::PAPER_REVIEW, 'date' => '2026-12-15 00:00:00'],
            ['docid' => 11, 'kind' => Workload::PAPER_REVIEW, 'date' => null],
            ['docid' => 12, 'kind' => Workload::PAPER_INVITATION, 'date' => '2026-11-15 09:00:00'],
        ], 13);
    }

    private static function presenter(array $visibleDocIds): WorkloadPresenter
    {
        return new WorkloadPresenter($visibleDocIds, static fn(string $date): string => 'on ' . substr($date, 0, 10));
    }

    public function testOnlyThePapersTheUserMayManageAreShownTheOthersAreCounted(): void
    {
        $presented = self::presenter([10, 12])->present(self::workload());

        self::assertSame([
            ['docid' => 10, 'url' => '/administratepaper/view/id/10', 'kind' => Workload::PAPER_REVIEW, 'date_label' => 'on 2026-12-15'],
            ['docid' => 12, 'url' => '/administratepaper/view/id/12', 'kind' => Workload::PAPER_INVITATION, 'date_label' => 'on 2026-11-15'],
        ], $presented['papers']);
        self::assertSame(1, $presented['other_papers']);
        // the counts stay complete
        self::assertSame(2, $presented['reviews_in_progress']);
        self::assertSame(1, $presented['pending_invitations']);
    }

    public function testTheLatestInvitationNamesItsPaperOnlyWhenTheUserMayManageIt(): void
    {
        $hidden = self::presenter([])->present(self::workload());
        self::assertNull($hidden['last_invitation_paper']);
        self::assertSame('on 2026-11-18', $hidden['last_invitation_date_label']);
        self::assertSame([], $hidden['papers']);
        self::assertSame(3, $hidden['other_papers']);

        $shown = self::presenter([13])->present(self::workload());
        self::assertSame(['docid' => 13, 'url' => '/administratepaper/view/id/13'], $shown['last_invitation_paper']);
    }

    public function testNeverInvited(): void
    {
        $presented = self::presenter([])->present(new Workload());

        self::assertNull($presented['last_invitation_date_label']);
        self::assertNull($presented['last_invitation_paper']);
        self::assertSame(0, $presented['other_papers']);
    }
}
