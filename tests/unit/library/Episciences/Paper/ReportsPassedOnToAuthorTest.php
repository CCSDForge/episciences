<?php

declare(strict_types=1);

namespace unit\library\Episciences\Paper;

use Episciences\Reviewer\DefaultRequestType;
use Episciences\Reviewer\RequestType;
use Episciences\Reviewer\RequestTypeRepository;
use Episciences_Paper;
use Episciences_Rating_Criterion;
use Episciences_Rating_Report;
use Episciences_User;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Reports shown outside the editorial team (author's page, decision e-mails, public page): only those of the request
 * types passed on to the author, according to the visibility of their criteria; the reviewer always sees their own
 * report; a report left without criteria is removed by its key, a reviewer holding one report per type (#319, lot D).
 */
final class ReportsPassedOnToAuthorTest extends TestCase
{
    private const REVIEW_REPORT = 1;
    private const QUICK_OPINION = 2;
    private const PHASE_TWO = 3;
    private const REVIEWER_UID = 7;
    private const AUTHOR_UID = 99;

    protected function setUp(): void
    {
        RequestTypeRepository::clearCache();
        $types = [
            self::REVIEW_REPORT => new RequestType(self::REVIEW_REPORT, RVID, DefaultRequestType::FullReport, ['en' => 'Review report']),
            self::QUICK_OPINION => new RequestType(self::QUICK_OPINION, RVID, DefaultRequestType::QuickOpinion, ['en' => 'Quick opinion']),
            // a type of the journal, passed on to the author
            self::PHASE_TWO => new RequestType(self::PHASE_TWO, RVID, null, ['en' => 'Phase two'], false, null, 3, true, [], false, true),
        ];
        $repository = $this->createMock(RequestTypeRepository::class);
        $repository->method('findById')->willReturnCallback(static fn(int $id): ?RequestType => $types[$id] ?? null);

        // the request types are read once per request: primed with these ones
        foreach (array_keys($types) as $id) {
            RequestTypeRepository::isFullReportId($id, $repository);
            RequestTypeRepository::visibleToAuthorId($id, $repository);
        }
    }

    protected function tearDown(): void
    {
        RequestTypeRepository::clearCache();
    }

    private static function criterion(int $id, string $visibility): Episciences_Rating_Criterion
    {
        $criterion = new Episciences_Rating_Criterion();
        $criterion->setId($id);
        $criterion->setVisibility($visibility);
        $criterion->setComment('Comment ' . $id);

        return $criterion;
    }

    /**
     * @param list<Episciences_Rating_Criterion> $criteria
     */
    private static function report(int $requestTypeId, array $criteria, int $uid = self::REVIEWER_UID): Episciences_Rating_Report
    {
        $report = new Episciences_Rating_Report();
        $report->setUid($uid);
        $report->setRequest_type_id($requestTypeId);
        foreach ($criteria as $criterion) {
            $report->setCriterion($criterion->getId(), $criterion);
        }

        return $report;
    }

    /**
     * @param array<int|string, Episciences_Rating_Report> $reports
     * @param list<string> $visibility
     * @return array<int|string, Episciences_Rating_Report>
     */
    private function filter(array $reports, array $visibility, ?Episciences_User $user = null, bool $editorialTeam = false): array
    {
        $paper = new Episciences_Paper();
        $paper->setUid(self::AUTHOR_UID);

        /** @var array<int|string, Episciences_Rating_Report> */
        return (new ReflectionMethod(Episciences_Paper::class, 'filterRatingReports'))->invoke($paper, $reports, $visibility, $user, $editorialTeam);
    }

    /**
     * @return array<int|string, Episciences_Rating_Report> a review report and a quick opinion of the same reviewer,
     *     and a report of a type of the journal passed on to the author
     */
    private static function reports(): array
    {
        $reports = [
            self::report(self::REVIEW_REPORT, [self::criterion(1, Episciences_Rating_Criterion::VISIBILITY_CONTRIBUTOR)]),
            self::report(self::QUICK_OPINION, [self::criterion(2, Episciences_Rating_Criterion::VISIBILITY_PUBLIC)]),
            self::report(self::PHASE_TWO, [self::criterion(3, Episciences_Rating_Criterion::VISIBILITY_PUBLIC)], 8),
        ];

        $keyed = [];
        foreach ($reports as $report) {
            $keyed[Episciences_Paper::reportKey($report)] = $report;
        }

        return $keyed;
    }

    public function testTheAuthorGetsOnlyTheTypesPassedOnToThem(): void
    {
        $filtered = $this->filter(self::reports(), [Episciences_Rating_Criterion::VISIBILITY_PUBLIC, Episciences_Rating_Criterion::VISIBILITY_CONTRIBUTOR]);

        // the quick opinion stays with the editorial team, although its criterion is public
        self::assertSame([self::REVIEWER_UID, '8_' . self::PHASE_TWO], array_keys($filtered));
    }

    public function testTheEditorialTeamGetsEveryType(): void
    {
        $filtered = $this->filter(
            self::reports(),
            [Episciences_Rating_Criterion::VISIBILITY_PUBLIC, Episciences_Rating_Criterion::VISIBILITY_CONTRIBUTOR, Episciences_Rating_Criterion::VISIBILITY_EDITORS],
            null,
            true
        );

        self::assertCount(3, $filtered);
    }

    public function testTheReviewerAlwaysSeesTheirOwnReports(): void
    {
        $reviewer = new Episciences_User();
        $reviewer->setUid(self::REVIEWER_UID);

        $filtered = $this->filter(self::reports(), [Episciences_Rating_Criterion::VISIBILITY_PUBLIC], $reviewer);

        self::assertArrayHasKey(self::REVIEWER_UID . '_' . self::QUICK_OPINION, $filtered);
        self::assertArrayHasKey(self::REVIEWER_UID, $filtered);
    }

    public function testTheVisitorsGetThePublicCriteriaOfTheTypesPassedOn(): void
    {
        $filtered = (new Episciences_Paper())->filterReportsByUserRole(self::reports(), null);

        // the review report has only a contributor criterion: nothing public is left of it
        self::assertSame(['8_' . self::PHASE_TWO], array_keys($filtered));
    }

    public function testAReportLeftWithoutCriteriaIsRemovedByItsKeyNotThatOfTheReviewer(): void
    {
        $reports = [
            self::REVIEWER_UID => self::report(self::REVIEW_REPORT, [self::criterion(1, Episciences_Rating_Criterion::VISIBILITY_PUBLIC)]),
            // a type passed on to the author, with editors criteria only: nothing left for the author
            self::REVIEWER_UID . '_' . self::PHASE_TWO => self::report(self::PHASE_TWO, [self::criterion(3, Episciences_Rating_Criterion::VISIBILITY_EDITORS)]),
        ];

        $filtered = $this->filter($reports, [Episciences_Rating_Criterion::VISIBILITY_PUBLIC, Episciences_Rating_Criterion::VISIBILITY_CONTRIBUTOR]);

        self::assertSame([self::REVIEWER_UID], array_keys($filtered));
    }
}
