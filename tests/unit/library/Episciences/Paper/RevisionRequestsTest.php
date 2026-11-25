<?php

declare(strict_types=1);

namespace unit\library\Episciences\Paper;

use Episciences\Paper\RevisionRequests;
use Episciences_CommentsManager;
use Episciences_Paper;
use PHPUnit\Framework\TestCase;

final class RevisionRequestsTest extends TestCase
{
    public function testCommentSettingsSelectTheRequestsAndTheirAnswers(): void
    {
        self::assertSame(
            ['types' => [
                Episciences_CommentsManager::TYPE_REVISION_REQUEST,
                Episciences_CommentsManager::TYPE_REVISION_ANSWER_COMMENT,
                Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION,
                Episciences_CommentsManager::TYPE_REVISION_ANSWER_NEW_VERSION,
                Episciences_CommentsManager::TYPE_REVISION_CONTACT_COMMENT,
            ]],
            RevisionRequests::commentSettings()
        );
    }

    public function testPreviousVersionsRequestsAreKeyedByVersionAndSkipVersionsWithout(): void
    {
        $requests = RevisionRequests::ofPreviousVersions([
            $this->version(10, [1 => ['PCID' => 1]]),
            $this->version(11, []),
            $this->version(12, [2 => ['PCID' => 2]]),
        ]);

        self::assertSame([10 => [1 => ['PCID' => 1]], 12 => [2 => ['PCID' => 2]]], $requests);
    }

    public function testEachPreviousVersionIsQueriedOnceWithTheRevisionSettings(): void
    {
        $version = $this->createMock(Episciences_Paper::class);
        $version->method('getDocid')->willReturn(10);
        $version->expects(self::once())
            ->method('getComments')
            ->with(RevisionRequests::commentSettings())
            ->willReturn([1 => ['PCID' => 1]]);

        RevisionRequests::ofPreviousVersions([$version]);
    }

    public function testUnansweredRequestHasAPendingDeadline(): void
    {
        self::assertSame('2026-11-30', RevisionRequests::pendingDeadline(['DEADLINE' => '2026-11-30']));
    }

    public function testRequestWithoutDeadlineHasNoPendingDeadline(): void
    {
        self::assertNull(RevisionRequests::pendingDeadline(['DEADLINE' => null]));
        self::assertNull(RevisionRequests::pendingDeadline([]));
    }

    public function testQuestionToTheEditorDoesNotAnswerTheRequest(): void
    {
        $request = [
            'DEADLINE' => '2026-11-30',
            'replies' => [7 => ['TYPE' => (string)Episciences_CommentsManager::TYPE_REVISION_CONTACT_COMMENT]],
        ];

        self::assertSame('2026-11-30', RevisionRequests::pendingDeadline($request));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function answers(): iterable
    {
        yield 'comment' => [Episciences_CommentsManager::TYPE_REVISION_ANSWER_COMMENT];
        yield 'temporary version' => [Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION];
        yield 'new version' => [Episciences_CommentsManager::TYPE_REVISION_ANSWER_NEW_VERSION];
    }

    /**
     * @dataProvider answers
     */
    public function testAnsweredRequestHasNoPendingDeadline(int $answerType): void
    {
        $request = ['DEADLINE' => '2026-11-30', 'replies' => [7 => ['TYPE' => (string)$answerType]]];

        self::assertNull(RevisionRequests::pendingDeadline($request));
    }

    /**
     * Only the first reply counts.
     */
    public function testLaterQuestionDoesNotReopenAnAnsweredRequest(): void
    {
        $request = [
            'DEADLINE' => '2026-11-30',
            'replies' => [
                8 => ['TYPE' => Episciences_CommentsManager::TYPE_REVISION_ANSWER_NEW_VERSION],
                9 => ['TYPE' => Episciences_CommentsManager::TYPE_REVISION_CONTACT_COMMENT],
            ],
        ];

        self::assertNull(RevisionRequests::pendingDeadline($request));
    }

    public function testEmptyRepliesGiveNoPendingDeadline(): void
    {
        self::assertNull(RevisionRequests::pendingDeadline(['DEADLINE' => '2026-11-30', 'replies' => []]));
        self::assertNull(RevisionRequests::pendingDeadline(['DEADLINE' => '2026-11-30', 'replies' => null]));
    }

    /**
     * @param array<int, array<string, mixed>> $comments
     */
    private function version(int $docId, array $comments): Episciences_Paper
    {
        $version = $this->createMock(Episciences_Paper::class);
        $version->method('getDocid')->willReturn($docId);
        $version->method('getComments')->willReturn($comments);

        return $version;
    }
}
