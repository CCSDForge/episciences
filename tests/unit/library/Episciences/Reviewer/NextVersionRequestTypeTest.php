<?php

declare(strict_types=1);

namespace unit\library\Episciences\Reviewer;

use Episciences\Reviewer\NextVersionRequestType;
use Episciences\Reviewer\RequestType;
use Episciences\Reviewer\RequestTypeRepository;
use Episciences_Comment;
use Episciences_CommentsManager;
use Episciences_Paper;
use Episciences_Review;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;
use unit\modules\ControllerTestCase;

/**
 * Request type of the reviewers on the next version of a paper, chosen in the revision request (#319, lot B).
 *
 * Extends ControllerTestCase for its database stub (forVersion() reads the previous version and its request).
 */
final class NextVersionRequestTypeTest extends ControllerTestCase
{
    private const PAPER_ID = 10;
    private const QUICK_OPINION = 12;

    public function testChoicesStartWithTheSameTypeThenListTheEnabledTypes(): void
    {
        $repository = $this->createMock(RequestTypeRepository::class);
        $repository->expects(self::once())->method('findAllForJournal')->with(RVID, true)->willReturn([
            new RequestType(11, RVID, null, ['en' => 'Review report', 'fr' => 'Rapport de relecture']),
            new RequestType(self::QUICK_OPINION, RVID, null, ['en' => 'Quick opinion', 'fr' => 'Avis rapide']),
        ]);

        self::assertSame(
            ['' => NextVersionRequestType::SAME_TYPE_LABEL, 11 => 'Review report', self::QUICK_OPINION => 'Quick opinion'],
            NextVersionRequestType::choices(RVID, 'en', $repository)
        );
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function notAnIdProvider(): array
    {
        return [
            'same type (empty)' => [''],
            'missing' => [null],
            'zero' => ['0'],
            'negative' => ['-3'],
            'not a number' => ['abc'],
            'number with text' => ['12abc'],
            'array' => [['12']],
        ];
    }

    #[DataProvider('notAnIdProvider')]
    public function testAPostedValueThatIsNotAnIdIsNoChoice(mixed $value): void
    {
        $repository = $this->createMock(RequestTypeRepository::class);
        $repository->expects(self::never())->method('findUsable');

        self::assertNull(NextVersionRequestType::fromPost($value, RVID, $repository));
    }

    public function testAPostedTypeIsKeptWhenTheJournalCanUseIt(): void
    {
        $repository = $this->createMock(RequestTypeRepository::class);
        $repository->method('findUsable')->with(self::QUICK_OPINION, RVID)
            ->willReturn(new RequestType(self::QUICK_OPINION, RVID, null, ['en' => 'Quick opinion']));

        self::assertSame(self::QUICK_OPINION, NextVersionRequestType::fromPost((string)self::QUICK_OPINION, RVID, $repository));
    }

    public function testAPostedTypeOfAnotherJournalOrDisabledIsNoChoice(): void
    {
        $repository = $this->createMock(RequestTypeRepository::class);
        $repository->method('findUsable')->willReturn(null);

        self::assertNull(NextVersionRequestType::fromPost('99', RVID, $repository));
    }

    /**
     * @return array<string, array{mixed, int|null}>
     */
    public static function optionProvider(): array
    {
        return [
            'no option (request saved before, or same type)' => [null, null],
            'integer' => [self::QUICK_OPINION, self::QUICK_OPINION],
            'string' => ['12', self::QUICK_OPINION],
            'zero' => [0, null],
            'invalid' => ['x', null],
        ];
    }

    #[DataProvider('optionProvider')]
    public function testTypeChosenInARevisionRequest(mixed $option, ?int $expected): void
    {
        $request = new Episciences_Comment();
        if ($option !== null) {
            $request->setOption(NextVersionRequestType::OPTION, $option);
        }

        self::assertSame($expected, NextVersionRequestType::ofRequest($request));
    }

    public function testWithoutChoiceEveryCurrentRequestIsInvitedAgainWithItsType(): void
    {
        $reviewer = new stdClass();
        $requests = [[$reviewer, 11], [$reviewer, self::QUICK_OPINION], [new stdClass(), null]];

        self::assertSame($requests, NextVersionRequestType::requestsFor($requests, null));
    }

    public function testWithAChosenTypeEachReviewerGetsASingleRequestOfThisType(): void
    {
        $first = new stdClass();
        $second = new stdClass();

        self::assertSame(
            [[$first, 20], [$second, 20]],
            NextVersionRequestType::requestsFor([[$first, 11], [$first, self::QUICK_OPINION], [$second, null]], 20)
        );
    }

    public function testDeadlineOfTheChosenTypeElseOfTheJournal(): void
    {
        $journal = $this->createMock(Episciences_Review::class);
        $journal->method('getSetting')->willReturnMap([[Episciences_Review::SETTING_RATING_DEADLINE, '1 month']]);

        $withDeadline = new RequestType(self::QUICK_OPINION, RVID, null, ['en' => 'Quick opinion'], false, '15 day');
        $withoutDeadline = new RequestType(11, RVID, null, ['en' => 'Review report']);

        self::assertSame('15 day', NextVersionRequestType::deadlineInterval($withDeadline, $journal));
        self::assertSame('1 month', NextVersionRequestType::deadlineInterval($withoutDeadline, $journal));
        self::assertSame('1 month', NextVersionRequestType::deadlineInterval(null, $journal));
    }

    public function testTheFirstVersionHasNoChosenTypeWithoutQuery(): void
    {
        self::assertNull(NextVersionRequestType::forVersion($this->version(self::PAPER_ID)));
        self::assertSame([], $this->db->queries());
    }

    public function testANewVersionTakesTheTypeOfTheLatestRequestOfThePreviousVersion(): void
    {
        $this->db
            ->respondTo('/FROM `PAPERS`/', [['DOCID' => 15]])
            ->respondTo('/FROM `PAPER_COMMENTS`/', [['OPTIONS' => '{"reassign_reviewers":true,"next_request_type_id":12}']]);

        self::assertSame(self::QUICK_OPINION, NextVersionRequestType::forVersion($this->version(20)));

        [$versionQuery, $requestQuery] = $this->db->selects();
        self::assertStringContainsString('PAPERID = ' . self::PAPER_ID, $versionQuery);
        self::assertStringContainsString("`WHEN` < '2026-03-01 10:00:00'", $versionQuery);
        self::assertStringContainsString('DOCID = 15', $requestQuery);
        self::assertStringContainsString('TYPE = ' . Episciences_CommentsManager::TYPE_REVISION_REQUEST, $requestQuery);
        self::assertStringContainsString('ORDER BY `WHEN` DESC', $requestQuery);
    }

    /**
     * @return array<string, array{list<array<string, mixed>>}>
     */
    public static function noChoiceProvider(): array
    {
        return [
            'no revision request' => [[]],
            'request without options' => [[['OPTIONS' => null]]],
            'request without a chosen type' => [[['OPTIONS' => '{"reassign_reviewers":true}']]],
            'unreadable options' => [[['OPTIONS' => '{not json']]],
        ];
    }

    /**
     * @param list<array<string, mixed>> $requestRows
     */
    #[DataProvider('noChoiceProvider')]
    public function testANewVersionWithoutChosenTypeHasNone(array $requestRows): void
    {
        $this->db
            ->respondTo('/FROM `PAPERS`/', [['DOCID' => 15]])
            ->respondTo('/FROM `PAPER_COMMENTS`/', $requestRows);

        self::assertNull(NextVersionRequestType::forVersion($this->version(20)));
    }

    /**
     * Version $docId of paper PAPER_ID (the first version has DOCID = PAPERID).
     *
     * @return MockObject&Episciences_Paper
     */
    private function version(int $docId): Episciences_Paper
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getPaperid')->willReturn(self::PAPER_ID);
        $paper->method('getDocid')->willReturn($docId);
        $paper->method('getWhen')->willReturn('2026-03-01 10:00:00');

        return $paper;
    }
}
