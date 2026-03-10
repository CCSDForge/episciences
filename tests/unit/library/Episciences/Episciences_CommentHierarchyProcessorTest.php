<?php

namespace unit\library\Episciences;

use Episciences_CommentHierarchyProcessor;
use Episciences_CommentsManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_CommentHierarchyProcessor
 *
 * This class processes author-editor communication comments into a hierarchical
 * structure suitable for timeline display. It handles:
 * - Collecting comments from nested structures into flat arrays
 * - Building parent-child relationships based on PARENTID
 * - Sorting comments chronologically
 * - Flattening deep hierarchies into 2-level structures (root + replies)
 * - Adding REPLY_TO_INFO metadata for UI display
 *
 * The processor only handles two comment types:
 * - TYPE_AUTHOR_TO_EDITOR (22): Messages from authors to editors
 * - TYPE_EDITOR_TO_AUTHOR (23): Editor replies to authors
 *
 * @covers Episciences_CommentHierarchyProcessor
 * @see Episciences_CommentsManager For comment type constants
 * @see Episciences_Paper_AuthorEditorCommunicationService For service that uses this processor
 */
class Episciences_CommentHierarchyProcessorTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /** Build a minimal root comment. */
    private function makeRoot(int $pcid, string $when = '2024-01-01 10:00:00', int $type = 22): array
    {
        return [
            'PCID'        => $pcid,
            'TYPE'        => $type,
            'PARENTID'    => null,
            'MESSAGE'     => "Root message $pcid",
            'WHEN'        => $when,
            'SCREEN_NAME' => "User $pcid",
        ];
    }

    /** Build a minimal reply comment. */
    private function makeReply(int $pcid, int $parentId, string $when = '2024-01-01 11:00:00', int $type = 23): array
    {
        return [
            'PCID'        => $pcid,
            'TYPE'        => $type,
            'PARENTID'    => $parentId,
            'MESSAGE'     => "Reply $pcid",
            'WHEN'        => $when,
            'SCREEN_NAME' => "User $pcid",
        ];
    }

    // =========================================================================
    // Empty Input Tests
    // =========================================================================

    /**
     * Test that processing an empty array returns an empty array.
     */
    public function testProcessCommentsForTimelineWithEmptyArrayReturnsEmptyArray(): void
    {
        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline([]);
        $this->assertSame([], $result);
    }

    // =========================================================================
    // Single Root Message Tests
    // =========================================================================

    /**
     * A single root message is returned with an empty 'replies' array.
     */
    public function testProcessCommentsForTimelineWithSingleRootMessage(): void
    {
        $comments = [1 => $this->makeRoot(1)];

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline($comments);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertSame(1, $result[1]['PCID']);
        $this->assertSame(Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR, $result[1]['TYPE']);
        $this->assertArrayHasKey('replies', $result[1]);
        $this->assertEmpty($result[1]['replies']);
    }

    // =========================================================================
    // Root With Reply Tests
    // =========================================================================

    /**
     * A root message with one reply: reply is nested and has REPLY_TO_INFO populated.
     */
    public function testProcessCommentsForTimelineWithRootAndOneReply(): void
    {
        $root  = $this->makeRoot(1);
        $reply = $this->makeReply(2, 1, '2024-01-01 11:00:00');
        $root['replies'] = [2 => $reply];

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline([1 => $root]);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertCount(1, $result[1]['replies']);

        $replyResult = reset($result[1]['replies']);
        $this->assertArrayHasKey('REPLY_TO_INFO', $replyResult);
        $this->assertSame(1, $replyResult['REPLY_TO_INFO']['PCID']);
        $this->assertSame('User 1', $replyResult['REPLY_TO_INFO']['SCREEN_NAME']);
    }

    /**
     * Flat input (no pre-nested 'replies' key) is also processed correctly.
     *
     * collectAllComments() handles both flat and nested input formats.
     */
    public function testProcessCommentsForTimelineWithFlatInput(): void
    {
        $comments = [
            1 => $this->makeRoot(1, '2024-01-01 10:00:00'),
            2 => $this->makeReply(2, 1, '2024-01-01 11:00:00'),
        ];

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline($comments);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertCount(1, $result[1]['replies']);
    }

    // =========================================================================
    // Sorting Tests
    // =========================================================================

    /**
     * Root messages are sorted chronologically (oldest first).
     */
    public function testProcessCommentsForTimelineSortsRootMessagesByDate(): void
    {
        $comments = [
            2 => $this->makeRoot(2, '2024-01-02 10:00:00'),
            1 => $this->makeRoot(1, '2024-01-01 10:00:00'),
        ];

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline($comments);

        $keys = array_keys($result);
        $this->assertSame(1, $keys[0], 'First message should be first in timeline');
        $this->assertSame(2, $keys[1], 'Second message should be second in timeline');
    }

    /**
     * Replies within a thread are sorted chronologically (oldest first).
     */
    public function testProcessCommentsForTimelineSortsRepliesByDate(): void
    {
        $root = $this->makeRoot(1, '2024-01-01 10:00:00');
        $root['replies'] = [
            3 => $this->makeReply(3, 1, '2024-01-01 12:00:00'),
            2 => $this->makeReply(2, 1, '2024-01-01 11:00:00'),
        ];

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline([1 => $root]);

        $replyKeys = array_keys($result[1]['replies']);
        $this->assertSame(2, $replyKeys[0], 'Earlier reply (11:00) should come first');
        $this->assertSame(3, $replyKeys[1], 'Later reply (12:00) should come second');
    }

    // =========================================================================
    // Type Filtering Tests
    // =========================================================================

    /**
     * Comments with invalid types are filtered out.
     */
    public function testProcessCommentsForTimelineFiltersInvalidTypes(): void
    {
        $comments = [
            1 => $this->makeRoot(1),
            2 => array_merge($this->makeRoot(2, '2024-01-01 11:00:00'), ['TYPE' => Episciences_CommentsManager::TYPE_EDITOR_COMMENT]),
        ];

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline($comments);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayNotHasKey(2, $result);
    }

    /**
     * Both valid types (22 and 23) are accepted as root messages.
     */
    public function testOnlyAuthorToEditorAndEditorToAuthorTypesAreValid(): void
    {
        $validTypes = [
            Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
            Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR,
        ];

        foreach ($validTypes as $type) {
            $comments = [
                1 => array_merge($this->makeRoot(1), ['TYPE' => $type]),
            ];

            $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline($comments);
            $this->assertCount(1, $result, "Type $type should be valid and included");
        }
    }

    /**
     * Common non-author-editor types are all filtered out.
     */
    public function testInvalidTypesAreFiltered(): void
    {
        $invalidTypes = [
            Episciences_CommentsManager::TYPE_INFO_REQUEST,
            Episciences_CommentsManager::TYPE_REVISION_REQUEST,
            Episciences_CommentsManager::TYPE_EDITOR_COMMENT,
            Episciences_CommentsManager::TYPE_AUTHOR_COMMENT,
        ];

        foreach ($invalidTypes as $type) {
            $comments = [
                1 => array_merge($this->makeRoot(1), ['TYPE' => $type]),
            ];

            $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline($comments);
            $this->assertCount(0, $result, "Type $type should be filtered out");
        }
    }

    /**
     * A comment without a TYPE key is filtered out.
     *
     * collectAllComments requires isset($comment['TYPE']) to pass.
     */
    public function testProcessCommentsFiltersMissingType(): void
    {
        $noType = ['PCID' => 1, 'PARENTID' => null, 'MESSAGE' => 'no type', 'WHEN' => '2024-01-01'];

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline([1 => $noType]);

        $this->assertSame([], $result);
    }

    /**
     * A comment without a PCID key is filtered out.
     *
     * collectAllComments requires isset($comment['PCID']) to pass.
     */
    public function testProcessCommentsFiltersMissingPcid(): void
    {
        $noPcid = ['TYPE' => 22, 'PARENTID' => null, 'MESSAGE' => 'no pcid', 'WHEN' => '2024-01-01'];

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline([1 => $noPcid]);

        $this->assertSame([], $result);
    }

    /**
     * All-invalid-type input yields an empty result (not a crash).
     */
    public function testProcessCommentsAllInvalidTypesYieldEmptyResult(): void
    {
        $comments = [
            1 => array_merge($this->makeRoot(1), ['TYPE' => Episciences_CommentsManager::TYPE_REVISION_REQUEST]),
            2 => array_merge($this->makeRoot(2), ['TYPE' => Episciences_CommentsManager::TYPE_INFO_REQUEST]),
        ];

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline($comments);

        $this->assertSame([], $result);
    }

    // =========================================================================
    // Hierarchy Flattening Tests
    // =========================================================================

    /**
     * Deeply nested replies (root → reply1 → reply2) are flattened to 2 levels.
     *
     * Both reply1 and reply2 appear directly under root in the output,
     * regardless of their original nesting depth.
     */
    public function testProcessCommentsForTimelineFlattensNestedReplies(): void
    {
        $reply2 = $this->makeReply(3, 2, '2024-01-01 12:00:00', 22);
        $reply1 = array_merge($this->makeReply(2, 1, '2024-01-01 11:00:00'), ['replies' => [3 => $reply2]]);
        $root   = array_merge($this->makeRoot(1, '2024-01-01 10:00:00'), ['replies' => [2 => $reply1]]);

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline([1 => $root]);

        $this->assertCount(1, $result, 'Should have 1 root message');
        $this->assertCount(2, $result[1]['replies'], 'Both replies should be flattened under root');

        $replyPcids = array_column($result[1]['replies'], 'PCID');
        $this->assertContains(2, $replyPcids);
        $this->assertContains(3, $replyPcids);
    }

    /**
     * For a grandchild reply, REPLY_TO_INFO points to its direct parent (PCID=2),
     * not to the root (PCID=1).
     *
     * This lets the UI show the correct "In reply to" context.
     */
    public function testProcessCommentsGrandchildReplyToInfoPointsToDirectParent(): void
    {
        $reply2 = $this->makeReply(3, 2, '2024-01-01 12:00:00', 22);
        $reply1 = array_merge($this->makeReply(2, 1, '2024-01-01 11:00:00'), ['replies' => [3 => $reply2]]);
        $root   = array_merge($this->makeRoot(1, '2024-01-01 10:00:00'), ['replies' => [2 => $reply1]]);

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline([1 => $root]);

        $replies     = $result[1]['replies'];
        $grandchild  = array_filter($replies, static fn($r) => $r['PCID'] === 3);
        $grandchild  = reset($grandchild);

        $this->assertSame(2, $grandchild['REPLY_TO_INFO']['PCID'],
            'REPLY_TO_INFO should point to direct parent (PCID=2), not root (PCID=1)');
    }

    // =========================================================================
    // REPLY_TO_INFO Tests
    // =========================================================================

    /**
     * REPLY_TO_INFO contains all required fields with correct values.
     */
    public function testProcessCommentsForTimelineAddsReplyToInfo(): void
    {
        $root  = array_merge($this->makeRoot(1), [
            'MESSAGE'     => 'Original author message with some content',
            'WHEN'        => '2024-01-01 10:00:00',
            'SCREEN_NAME' => 'AuthorName',
        ]);
        $root['replies'] = [2 => $this->makeReply(2, 1, '2024-01-01 11:00:00')];

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline([1 => $root]);

        $reply = reset($result[1]['replies']);

        $this->assertArrayHasKey('REPLY_TO_INFO', $reply);
        $this->assertSame(1,                    $reply['REPLY_TO_INFO']['PCID']);
        $this->assertSame('AuthorName',         $reply['REPLY_TO_INFO']['SCREEN_NAME']);
        $this->assertSame('2024-01-01 10:00:00', $reply['REPLY_TO_INFO']['WHEN']);
        $this->assertStringStartsWith('Original author message', $reply['REPLY_TO_INFO']['MESSAGE_PREVIEW']);
    }

    /**
     * MESSAGE_PREVIEW is truncated to 100 characters via mb_substr.
     *
     * Long parent messages are previewed at a fixed length to keep the UI compact.
     */
    public function testProcessCommentsMessagePreviewTruncatedTo100Chars(): void
    {
        $longMessage = str_repeat('A', 150);
        $root = array_merge($this->makeRoot(1), ['MESSAGE' => $longMessage]);
        $root['replies'] = [2 => $this->makeReply(2, 1, '2024-01-02 00:00:00')];

        $result  = Episciences_CommentHierarchyProcessor::processCommentsForTimeline([1 => $root]);
        $preview = reset($result[1]['replies'])['REPLY_TO_INFO']['MESSAGE_PREVIEW'];

        $this->assertSame(100, mb_strlen($preview));
        $this->assertSame(str_repeat('A', 100), $preview);
    }

    /**
     * MESSAGE_PREVIEW strips HTML tags via strip_tags().
     *
     * Prevents raw HTML from leaking into the UI preview text.
     */
    public function testProcessCommentsMessagePreviewStripsHtmlTags(): void
    {
        $root = array_merge($this->makeRoot(1), ['MESSAGE' => '<p>Hello <strong>world</strong></p>']);
        $root['replies'] = [2 => $this->makeReply(2, 1, '2024-01-02 00:00:00')];

        $result  = Episciences_CommentHierarchyProcessor::processCommentsForTimeline([1 => $root]);
        $preview = reset($result[1]['replies'])['REPLY_TO_INFO']['MESSAGE_PREVIEW'];

        $this->assertSame('Hello world', $preview);
    }

    // =========================================================================
    // Edge Case Tests
    // =========================================================================

    /**
     * Missing optional fields (WHEN, SCREEN_NAME) are handled gracefully.
     *
     * PCID and TYPE are the only strictly required fields for processing.
     * Missing WHEN defaults to timestamp 0 (Unix epoch) for sort purposes.
     */
    public function testProcessCommentsForTimelineHandlesMissingFields(): void
    {
        $comments = [
            1 => [
                'PCID'     => 1,
                'TYPE'     => Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
                // Missing: PARENTID, MESSAGE, WHEN, SCREEN_NAME
            ],
        ];

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline($comments);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey(1, $result);
    }

    /**
     * Missing WHEN field defaults to timestamp 0 (the guard in sortCommentsRecursive
     * and the final sort uses `isset($a['WHEN']) ? strtotime($a['WHEN']) : 0`).
     *
     * A comment without WHEN is treated as epoch and sorts before any dated comment.
     */
    public function testProcessCommentsWithMissingWhenSortedAsEarliestEntry(): void
    {
        $noWhen  = ['PCID' => 1, 'TYPE' => 22, 'PARENTID' => null, 'MESSAGE' => 'no when'];
        $withDate = $this->makeRoot(2, '2024-01-01 10:00:00');

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline([
            1 => $noWhen,
            2 => $withDate,
        ]);

        $keys = array_keys($result);
        $this->assertSame(1, $keys[0], 'Missing WHEN (epoch 0) should sort before any real date');
        $this->assertSame(2, $keys[1]);
    }

    /**
     * PARENTID = 0 is falsy (empty(0) === true) so the comment is treated as a root message.
     */
    public function testProcessCommentsWithZeroParentIdTreatedAsRoot(): void
    {
        $comment = ['PCID' => 1, 'TYPE' => 22, 'PARENTID' => 0, 'WHEN' => '2024-01-01'];

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline([1 => $comment]);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey(1, $result);
    }

    /**
     * Circular PARENTID references do not cause an infinite loop.
     *
     * PCID=1 has PARENTID=2 and PCID=2 has PARENTID=1 — both are placed in $allReplies.
     * placeReplies() detects no progress and breaks with warnings.
     * Result: no root messages → empty output.
     */
    public function testProcessCommentsCircularReferenceDoesNotLoop(): void
    {
        $comments = [
            1 => ['PCID' => 1, 'TYPE' => 22, 'PARENTID' => 2, 'WHEN' => '2024-01-01', 'MESSAGE' => 'A'],
            2 => ['PCID' => 2, 'TYPE' => 23, 'PARENTID' => 1, 'WHEN' => '2024-01-02', 'MESSAGE' => 'B'],
        ];

        set_error_handler(static function (): bool { return true; });
        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline($comments);
        restore_error_handler();

        // Both comments are replies of each other — no root can be established
        $this->assertSame([], $result);
    }

    // =========================================================================
    // Multiple Threads Tests
    // =========================================================================

    /**
     * Multiple independent threads each contain only their own replies.
     */
    public function testProcessCommentsForTimelineWithMultipleRootsAndReplies(): void
    {
        $thread1 = $this->makeRoot(1, '2024-01-01 10:00:00');
        $thread1['replies'] = [2 => $this->makeReply(2, 1, '2024-01-01 11:00:00')];

        $thread2 = $this->makeRoot(3, '2024-01-02 10:00:00');
        $thread2['replies'] = [4 => $this->makeReply(4, 3, '2024-01-02 11:00:00')];

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline([
            1 => $thread1,
            3 => $thread2,
        ]);

        $this->assertCount(2, $result);
        $this->assertCount(1, $result[1]['replies']);
        $this->assertCount(1, $result[3]['replies']);

        $reply1 = reset($result[1]['replies']);
        $this->assertSame(1, $reply1['REPLY_TO_INFO']['PCID'], 'Reply should reference thread 1');

        $reply3 = reset($result[3]['replies']);
        $this->assertSame(3, $reply3['REPLY_TO_INFO']['PCID'], 'Reply should reference thread 3');
    }

    /**
     * Multiple replies under the same root all appear in the output.
     */
    public function testProcessCommentsWithMultipleRepliesUnderOneRoot(): void
    {
        $root = $this->makeRoot(1, '2024-01-01 10:00:00');
        $root['replies'] = [
            2 => $this->makeReply(2, 1, '2024-01-01 11:00:00'),
            3 => $this->makeReply(3, 1, '2024-01-01 12:00:00'),
            4 => $this->makeReply(4, 1, '2024-01-01 13:00:00'),
        ];

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline([1 => $root]);

        $this->assertCount(3, $result[1]['replies']);
        $this->assertArrayHasKey(2, $result[1]['replies']);
        $this->assertArrayHasKey(3, $result[1]['replies']);
        $this->assertArrayHasKey(4, $result[1]['replies']);
    }
}
