<?php

declare(strict_types=1);

namespace unit\library\Episciences\Paper;

use Episciences\Paper\RequiredReviews;
use Episciences_Acl;
use Episciences_Paper;
use Episciences_Rating_Report;
use Episciences_Review;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use unit\modules\ControllerTestCase;

/**
 * Minimum number of reviews of a version (git #239): minimum of the first N rounds, then the later minimum.
 *
 * Extends ControllerTestCase for its database stub (rounds are counted in the database) and its logged user.
 */
final class RequiredReviewsTest extends ControllerTestCase
{
    private const PAPER_ID = 10;

    /**
     * @return array<string, array{int, mixed, mixed, mixed, int}>
     */
    public static function roundProvider(): array
    {
        return [
            'no minimum' => [1, '0', null, null, 0],
            'minimum without rounds, first round' => [1, '2', null, null, 2],
            'minimum without rounds, later round (current behaviour)' => [4, '2', null, null, 2],
            'empty number of rounds means every round' => [3, '2', '', '1', 2],
            'zero rounds means every round' => [3, '2', '0', '1', 2],
            'CM, first round' => [1, '2', '1', '1', 2],
            'CM, second round' => [2, '2', '1', '1', 1],
            'CM, fifth round' => [5, '2', '1', '1', 1],
            'TheoretiCS, second round' => [2, '2', '2', '0', 2],
            'TheoretiCS, third round' => [3, '2', '2', '0', 0],
            'later minimum not set' => [2, '2', '1', null, 0],
            'integers' => [2, 3, 1, 1, 1],
            'invalid minimum' => [1, 'two', null, null, 0],
            'negative minimum' => [1, -2, null, null, 0],
            'invalid number of rounds means every round' => [3, '2', 'x', '1', 2],
        ];
    }

    #[DataProvider('roundProvider')]
    public function testMinimumOfARound(int $round, mixed $minimum, mixed $rounds, mixed $laterMinimum, int $expected): void
    {
        self::assertSame($expected, RequiredReviews::forRound($round, $minimum, $rounds, $laterMinimum));
    }

    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function enabledProvider(): array
    {
        return [
            'nothing set' => [[], false],
            'minimum for every round' => [[Episciences_Review::SETTING_REQUIRED_REVIEWERS => '2'], true],
            'minimum for the first round only' => [self::settings('2', '1', '0'), true],
            'minimum for the later rounds only' => [self::settings('0', '1', '2'), true],
            'later minimum without number of rounds is never used' => [self::settings('0', '', '3'), false],
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    #[DataProvider('enabledProvider')]
    public function testWhetherTheJournalHasAMinimum(array $settings, bool $expected): void
    {
        self::assertSame($expected, RequiredReviews::isEnabled($this->journal($settings)));
    }

    public function testFirstVersionIsTheFirstRoundWithoutQuery(): void
    {
        self::assertSame(1, RequiredReviews::roundOf($this->version(self::PAPER_ID)));
        self::assertSame([], $this->db->queries());
    }

    public function testRoundCountsTheEarlierVersionsTemporaryOnesIncluded(): void
    {
        $this->db->respondTo('/COUNT\(\*\).*FROM `PAPERS`/', [['NB' => 2]]);

        self::assertSame(3, RequiredReviews::roundOf($this->version(30, '2026-03-01 10:00:00')));

        $sql = $this->db->selects()[0] ?? '';
        self::assertStringContainsString('PAPERID = ' . self::PAPER_ID, $sql);
        self::assertStringContainsString("`WHEN` < '2026-03-01 10:00:00'", $sql);
        self::assertStringNotContainsString('REPOID', $sql);
    }

    public function testMinimumOfAVersionFollowsItsRound(): void
    {
        $this->db->respondTo('/COUNT\(\*\).*FROM `PAPERS`/', [['NB' => 1]]);
        $journal = $this->journal(self::settings('2', '1', '1'));

        self::assertSame(2, RequiredReviews::requiredFor($this->version(self::PAPER_ID), $journal));
        self::assertSame(1, RequiredReviews::requiredFor($this->version(20), $journal));
    }

    /**
     * @return array<string, array{list<string>, int, bool}>
     */
    public static function editorProvider(): array
    {
        return [
            'editor, minimum not reached' => [[Episciences_Acl::ROLE_EDITOR], 1, true],
            'guest editor, minimum not reached' => [[Episciences_Acl::ROLE_GUEST_EDITOR], 0, true],
            'editor, minimum reached' => [[Episciences_Acl::ROLE_EDITOR], 2, false],
            'secretary, minimum not reached' => [[Episciences_Acl::ROLE_SECRETARY], 0, false],
            'chief editor, minimum not reached' => [[Episciences_Acl::ROLE_CHIEF_EDITOR], 0, false],
            'administrator, minimum not reached' => [[Episciences_Acl::ROLE_ADMIN], 0, false],
        ];
    }

    /**
     * Same rule as the status menu: only editors and guest editors are held back.
     *
     * @param list<string> $roles
     */
    #[DataProvider('editorProvider')]
    public function testOnlyEditorsAreHeldBackBeforeTheMinimum(array $roles, int $completedReviews, bool $expected): void
    {
        $this->logInAs($roles);
        $journal = $this->journal([Episciences_Review::SETTING_REQUIRED_REVIEWERS => '2']);

        self::assertSame($expected, RequiredReviews::blocksLoggedEditor($this->version(self::PAPER_ID, null, $completedReviews), $journal));
    }

    public function testNoMinimumNeverHoldsBack(): void
    {
        $this->logInAs([Episciences_Acl::ROLE_EDITOR]);

        self::assertTrue(RequiredReviews::isReached($this->version(self::PAPER_ID), $this->journal([])));
        self::assertFalse(RequiredReviews::blocksLoggedEditor($this->version(self::PAPER_ID), $this->journal([])));
    }

    /**
     * @return array<string, string>
     */
    private static function settings(string $minimum, string $rounds, string $laterMinimum): array
    {
        return [
            Episciences_Review::SETTING_REQUIRED_REVIEWERS => $minimum,
            Episciences_Review::SETTING_REQUIRED_REVIEWERS_ROUNDS => $rounds,
            Episciences_Review::SETTING_REQUIRED_REVIEWERS_LATER_ROUNDS => $laterMinimum,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @return MockObject&Episciences_Review
     */
    private function journal(array $settings): Episciences_Review
    {
        $journal = $this->createMock(Episciences_Review::class);
        $journal->method('getSetting')->willReturnCallback(static fn(string $name) => $settings[$name] ?? null);

        return $journal;
    }

    /**
     * Version $docId of paper PAPER_ID (the first version has DOCID = PAPERID), with $completedReviews completed reviews.
     *
     * @return MockObject&Episciences_Paper
     */
    private function version(int $docId, ?string $when = null, int $completedReviews = 0): Episciences_Paper
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getPaperid')->willReturn(self::PAPER_ID);
        $paper->method('getDocid')->willReturn($docId);
        $paper->method('getWhen')->willReturn($when ?? '2026-01-01 10:00:00');
        $paper->method('loadRatings')->willReturnSelf();
        $paper->method('getRatings')->willReturnCallback(
            fn($uid = null, $status = null): array => $status === Episciences_Rating_Report::STATUS_COMPLETED
                ? array_fill(0, $completedReviews, $this->createMock(Episciences_Rating_Report::class))
                : []
        );

        return $paper;
    }
}
