<?php

namespace unit\library\Episciences;

use Episciences_CommentHierarchyProcessor;
use Episciences_CommentsManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_CommentHierarchyProcessor
 *
 * This class is responsible for processing author-editor communication comments into a hierarchical structure suitable for timeline display.
 * It handles:
 * - Collecting comments from nested structures into flat arrays
 * - Building parent-child relationships based on PARENTID
 * - Sorting comments chronologically
 * - Flattening deep hierarchies into 2-level structures (root + replies)
 * - Adding REPLY_TO_INFO metadata for UI display
 *
 * The processor only handles two comment types:
 * - TYPE_AUTHOR_TO_EDITOR (22): Messages from authors to editors
 * - TYPE_EDITOR_TO_AUTHOR_RESPONSE (23): Editor replies to authors
 *
 * @covers Episciences_CommentHierarchyProcessor
 * @see Episciences_CommentsManager For comment type constants
 * @see Episciences_Paper_AuthorEditorCommunicationService For service that uses this processor
 */
class Episciences_CommentHierarchyProcessorTest extends TestCase
{
    // =========================================================================
    // Empty Input Tests
    // =========================================================================

    /**
     * Test that processing an empty array returns an empty array.
     * This is the base case - no comments means no output.
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
     * Test processing a single root message (no replies).
     *
     * A root message is identified by having PARENTID = null.
     * The result should contain:
     * - The original message data (PCID, TYPE, MESSAGE, etc.)
     * - An empty 'replies' array (initialized for consistency)
     */
    public function testProcessCommentsForTimelineWithSingleRootMessage(): void
    {
        $comments = [
            1 => [
                'PCID' => 1,
                'TYPE' => Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
                'PARENTID' => null,
                'MESSAGE' => 'Test message',
                'WHEN' => '2024-01-01 10:00:00',
                'SCREEN_NAME' => 'Author'
            ]
        ];

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
     * Test processing a root message with one reply.
     *
     * This tests the basic parent-child relationship:
     * - Root message (PARENTID = null) becomes the main entry
     * - Reply (PARENTID = root's PCID) is placed in the 'replies' array
     * - Reply gets REPLY_TO_INFO populated with parent's metadata
     *
     * REPLY_TO_INFO contains:
     * - PCID: Parent comment ID (for linking)
     * - SCREEN_NAME: Who wrote the parent (for display)
     * - WHEN: Parent timestamp (for context)
     * - MESSAGE_PREVIEW: Truncated parent message (for UI preview)
     */
    public function testProcessCommentsForTimelineWithRootAndOneReply(): void
    {
        $comments = [
            1 => [
                'PCID' => 1,
                'TYPE' => Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
                'PARENTID' => null,
                'MESSAGE' => 'Author message',
                'WHEN' => '2024-01-01 10:00:00',
                'SCREEN_NAME' => 'Author',
                'replies' => [
                    2 => [
                        'PCID' => 2,
                        'TYPE' => Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR_RESPONSE,
                        'PARENTID' => 1,
                        'MESSAGE' => 'Editor reply',
                        'WHEN' => '2024-01-01 11:00:00',
                        'SCREEN_NAME' => 'Editor'
                    ]
                ]
            ]
        ];

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline($comments);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertCount(1, $result[1]['replies']);

        // Verify REPLY_TO_INFO is populated correctly
        $reply = reset($result[1]['replies']);
        $this->assertArrayHasKey('REPLY_TO_INFO', $reply);
        $this->assertSame(1, $reply['REPLY_TO_INFO']['PCID']);
        $this->assertSame('Author', $reply['REPLY_TO_INFO']['SCREEN_NAME']);
    }

    // =========================================================================
    // Sorting Tests
    // =========================================================================

    /**
     * Test that root messages are sorted chronologically (oldest first).
     *
     * This ensures the timeline displays messages in the order they were sent, regardless of the order they appear in the input array.
     * Uses uasort() to preserve array keys while sorting by WHEN timestamp.
     */
    public function testProcessCommentsForTimelineSortsRootMessagesByDate(): void
    {
        $comments = [
            2 => [
                'PCID' => 2,
                'TYPE' => Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
                'PARENTID' => null,
                'MESSAGE' => 'Second message',
                'WHEN' => '2024-01-02 10:00:00',
                'SCREEN_NAME' => 'Author'
            ],
            1 => [
                'PCID' => 1,
                'TYPE' => Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
                'PARENTID' => null,
                'MESSAGE' => 'First message',
                'WHEN' => '2024-01-01 10:00:00',
                'SCREEN_NAME' => 'Author'
            ]
        ];

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline($comments);

        // Verify chronological order (oldest first)
        $keys = array_keys($result);
        $this->assertSame(1, $keys[0], 'First message should be first in timeline');
        $this->assertSame(2, $keys[1], 'Second message should be second in timeline');
    }

    /**
     * Test that replies within a thread are sorted chronologically.
     *
     * When a root message has multiple replies, they should be displayed in the order they were posted, creating a readable conversation flow.
     */
    public function testProcessCommentsForTimelineSortsRepliesByDate(): void
    {
        $comments = [
            1 => [
                'PCID' => 1,
                'TYPE' => Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
                'PARENTID' => null,
                'MESSAGE' => 'Root message',
                'WHEN' => '2024-01-01 10:00:00',
                'SCREEN_NAME' => 'Author',
                'replies' => [
                    // Intentionally out of order in input
                    3 => [
                        'PCID' => 3,
                        'TYPE' => Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
                        'PARENTID' => 1,
                        'MESSAGE' => 'Second reply',
                        'WHEN' => '2024-01-01 12:00:00',
                        'SCREEN_NAME' => 'Author'
                    ],
                    2 => [
                        'PCID' => 2,
                        'TYPE' => Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR_RESPONSE,
                        'PARENTID' => 1,
                        'MESSAGE' => 'First reply',
                        'WHEN' => '2024-01-01 11:00:00',
                        'SCREEN_NAME' => 'Editor'
                    ]
                ]
            ]
        ];

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline($comments);

        $replies = $result[1]['replies'];
        $replyKeys = array_keys($replies);

        // Verify replies are sorted by date (oldest first)
        $this->assertSame(2, $replyKeys[0], 'First reply (11:00) should come first');
        $this->assertSame(3, $replyKeys[1], 'Second reply (12:00) should come second');
    }

    // =========================================================================
    // Type Filtering Tests
    // =========================================================================

    /**
     * Test that only valid author-editor comment types are included.
     *
     * The processor filters out any comment types that are not part of the author-editor communication system.
     * This prevents other comment types (revision requests, editor notes, etc.) from appearing in the author-editor timeline.
     *
     * Valid types: TYPE_AUTHOR_TO_EDITOR (22), TYPE_EDITOR_TO_AUTHOR_RESPONSE (23)
     */
    public function testProcessCommentsForTimelineFiltersInvalidTypes(): void
    {
        $comments = [
            1 => [
                'PCID' => 1,
                'TYPE' => Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
                'PARENTID' => null,
                'MESSAGE' => 'Valid message',
                'WHEN' => '2024-01-01 10:00:00',
                'SCREEN_NAME' => 'Author'
            ],
            2 => [
                'PCID' => 2,
                'TYPE' => Episciences_CommentsManager::TYPE_EDITOR_COMMENT, // Wrong type
                'PARENTID' => null,
                'MESSAGE' => 'Invalid type message',
                'WHEN' => '2024-01-01 11:00:00',
                'SCREEN_NAME' => 'Editor'
            ]
        ];

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline($comments);

        // Only the valid type should remain
        $this->assertCount(1, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayNotHasKey(2, $result);
    }

    // =========================================================================
    // Hierarchy Flattening Tests
    // =========================================================================

    /**
     * Test that deeply nested replies are flattened to a 2-level structure.
     *
     * The UI displays comments in a 2-level structure:
     * - Level 1: Root messages (original questions/statements)
     * - Level 2: All replies (regardless of how deep they were nested)
     *
     * This flattening makes the conversation easier to follow in the UI while preserving the REPLY_TO_INFO to show what each reply responded to.
     *
     * Input structure:  root -> reply1 -> reply2 (3 levels)
     * Output structure: root -> [reply1, reply2] (2 levels, both replies at same level)
     */
    public function testProcessCommentsForTimelineFlattensNestedReplies(): void
    {
        // Multi-level structure: root -> reply1 -> reply2
        $comments = [
            1 => [
                'PCID' => 1,
                'TYPE' => Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
                'PARENTID' => null,
                'MESSAGE' => 'Root message',
                'WHEN' => '2024-01-01 10:00:00',
                'SCREEN_NAME' => 'Author',
                'replies' => [
                    2 => [
                        'PCID' => 2,
                        'TYPE' => Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR_RESPONSE,
                        'PARENTID' => 1,
                        'MESSAGE' => 'Editor reply',
                        'WHEN' => '2024-01-01 11:00:00',
                        'SCREEN_NAME' => 'Editor',
                        'replies' => [
                            3 => [
                                'PCID' => 3,
                                'TYPE' => Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
                                'PARENTID' => 2, // Reply to reply
                                'MESSAGE' => 'Author reply to editor',
                                'WHEN' => '2024-01-01 12:00:00',
                                'SCREEN_NAME' => 'Author'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline($comments);

        // Verify 2-level structure: 1 root with 2 replies at the same level
        $this->assertCount(1, $result, 'Should have 1 root message');
        $this->assertCount(2, $result[1]['replies'], 'Both replies should be flattened under root');

        // Verify both replies are present
        $replyPcids = array_column($result[1]['replies'], 'PCID');
        $this->assertContains(2, $replyPcids);
        $this->assertContains(3, $replyPcids);
    }

    // =========================================================================
    // REPLY_TO_INFO Tests
    // =========================================================================

    /**
     * Test that REPLY_TO_INFO contains correct parent metadata.
     *
     * REPLY_TO_INFO is used by the UI to display context about what message a reply is responding to. It contains:
     * - PCID: For creating links/anchors
     * - SCREEN_NAME: For "In reply to [name]" display
     * - WHEN: For timestamp context
     * - MESSAGE_PREVIEW: First 100 chars of parent message (truncated)
     */
    public function testProcessCommentsForTimelineAddsReplyToInfo(): void
    {
        $comments = [
            1 => [
                'PCID' => 1,
                'TYPE' => Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
                'PARENTID' => null,
                'MESSAGE' => 'Original author message with some content',
                'WHEN' => '2024-01-01 10:00:00',
                'SCREEN_NAME' => 'AuthorName',
                'replies' => [
                    2 => [
                        'PCID' => 2,
                        'TYPE' => Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR_RESPONSE,
                        'PARENTID' => 1,
                        'MESSAGE' => 'Editor reply',
                        'WHEN' => '2024-01-01 11:00:00',
                        'SCREEN_NAME' => 'EditorName'
                    ]
                ]
            ]
        ];

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline($comments);

        $reply = reset($result[1]['replies']);

        // Verify all REPLY_TO_INFO fields
        $this->assertArrayHasKey('REPLY_TO_INFO', $reply);
        $this->assertSame(1, $reply['REPLY_TO_INFO']['PCID']);
        $this->assertSame('AuthorName', $reply['REPLY_TO_INFO']['SCREEN_NAME']);
        $this->assertSame('2024-01-01 10:00:00', $reply['REPLY_TO_INFO']['WHEN']);
        $this->assertStringStartsWith('Original author message', $reply['REPLY_TO_INFO']['MESSAGE_PREVIEW']);
    }

    // =========================================================================
    // Edge Case Tests
    // =========================================================================

    /**
     * Test that comments with missing optional fields are handled gracefully.
     *
     * The processor should not crash if some fields are missing.
     * Only PCID and TYPE are strictly required for processing.
     */
    public function testProcessCommentsForTimelineHandlesMissingFields(): void
    {
        $comments = [
            1 => [
                'PCID' => 1,
                'TYPE' => Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
                // Missing: PARENTID, MESSAGE, WHEN, SCREEN_NAME
            ]
        ];

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline($comments);

        // Should process without errors
        $this->assertCount(1, $result);
        $this->assertArrayHasKey(1, $result);
    }

    // =========================================================================
    // Multiple Threads Tests
    // =========================================================================

    /**
     * Test processing multiple independent conversation threads.
     *
     * Each root message represents a separate conversation thread.
     * Replies should stay under their respective root messages and
     * not get mixed between threads.
     */
    public function testProcessCommentsForTimelineWithMultipleRootsAndReplies(): void
    {
        $comments = [
            1 => [
                'PCID' => 1,
                'TYPE' => Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
                'PARENTID' => null,
                'MESSAGE' => 'First thread',
                'WHEN' => '2024-01-01 10:00:00',
                'SCREEN_NAME' => 'Author',
                'replies' => [
                    2 => [
                        'PCID' => 2,
                        'TYPE' => Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR_RESPONSE,
                        'PARENTID' => 1,
                        'MESSAGE' => 'Reply to first thread',
                        'WHEN' => '2024-01-01 11:00:00',
                        'SCREEN_NAME' => 'Editor'
                    ]
                ]
            ],
            3 => [
                'PCID' => 3,
                'TYPE' => Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
                'PARENTID' => null,
                'MESSAGE' => 'Second thread',
                'WHEN' => '2024-01-02 10:00:00',
                'SCREEN_NAME' => 'Author',
                'replies' => [
                    4 => [
                        'PCID' => 4,
                        'TYPE' => Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR_RESPONSE,
                        'PARENTID' => 3,
                        'MESSAGE' => 'Reply to second thread',
                        'WHEN' => '2024-01-02 11:00:00',
                        'SCREEN_NAME' => 'Editor'
                    ]
                ]
            ]
        ];

        $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline($comments);

        // Verify two separate threads
        $this->assertCount(2, $result);
        $this->assertCount(1, $result[1]['replies']);
        $this->assertCount(1, $result[3]['replies']);

        // Verify replies reference correct parents (not mixed between threads)
        $reply1 = reset($result[1]['replies']);
        $this->assertSame(1, $reply1['REPLY_TO_INFO']['PCID'], 'Reply should reference thread 1');

        $reply3 = reset($result[3]['replies']);
        $this->assertSame(3, $reply3['REPLY_TO_INFO']['PCID'], 'Reply should reference thread 3');
    }

    // =========================================================================
    // Valid Type Verification Tests
    // =========================================================================

    /**
     * Test that both valid author-editor communication types are accepted.
     *
     * Verifies that:
     * - TYPE_AUTHOR_TO_EDITOR (22): Author messages to editors
     * - TYPE_EDITOR_TO_AUTHOR_RESPONSE (23): Editor replies to authors
     *
     * Both types should be processed and appear in the output.
     */
    public function testOnlyAuthorToEditorAndEditorToAuthorTypesAreValid(): void
    {
        $validTypes = [
            Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
            Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR_RESPONSE
        ];

        foreach ($validTypes as $type) {
            $comments = [
                1 => [
                    'PCID' => 1,
                    'TYPE' => $type,
                    'PARENTID' => null,
                    'MESSAGE' => 'Test',
                    'WHEN' => '2024-01-01 10:00:00',
                    'SCREEN_NAME' => 'User'
                ]
            ];

            $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline($comments);
            $this->assertCount(1, $result, "Type $type should be valid and included");
        }
    }

    /**
     * Test that other comment types are filtered out.
     *
     * The following types should NOT appear in author-editor communication:
     * - TYPE_INFO_REQUEST: Review information requests
     * - TYPE_REVISION_REQUEST: Revision requests from editors
     * - TYPE_EDITOR_COMMENT: Internal editor comments
     * - TYPE_AUTHOR_COMMENT: Author comments on reviews
     *
     * These have their own display locations and should not pollute the author-editor communication timeline.
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
                1 => [
                    'PCID' => 1,
                    'TYPE' => $type,
                    'PARENTID' => null,
                    'MESSAGE' => 'Test',
                    'WHEN' => '2024-01-01 10:00:00',
                    'SCREEN_NAME' => 'User'
                ]
            ];

            $result = Episciences_CommentHierarchyProcessor::processCommentsForTimeline($comments);
            $this->assertCount(0, $result, "Type $type should be filtered out");
        }
    }
}