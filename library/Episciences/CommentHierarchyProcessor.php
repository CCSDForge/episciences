<?php

/**
 * Class Episciences_CommentHierarchyProcessor
 *
 * Utility class for processing author-editor comment hierarchies.
 * Handles the complex logic of building, sorting, and flattening comment threads
 * for timeline display in both PaperController and AdministratepaperController.
 */
class Episciences_CommentHierarchyProcessor
{
    /**
     * Valid comment types for author-editor communication hierarchy
     */
    private const VALID_TYPES = [
        Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR,
        Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR_RESPONSE
    ];

    /**
     * Check if a comment type is valid for author-editor communication
     *
     * @param mixed $type Comment type to validate
     * @return bool True if valid type
     */
    private static function isValidType($type): bool
    {
        return in_array((int)$type, self::VALID_TYPES, true);
    }

    /**
     * Process comments for timeline display
     *
     * Takes a potentially nested comment structure from the database and:
     * 1. Flattens it to collect all comments
     * 2. Rebuilds the hierarchy based on PARENTID relationships
     * 3. Sorts comments recursively by date
     * 4. Flattens all replies under their root message (2-level structure)
     * 5. Adds REPLY_TO_INFO to each reply for display purposes
     *
     * @param array $comments Raw comment array from database
     * @return array Processed comments ready for timeline display
     */
    public static function processCommentsForTimeline(array $comments): array
    {
        if (empty($comments)) {
            return [];
        }

        // Step 1: Collect all comments recursively into a flat array
        $allCommentsFlat = [];
        self::collectAllComments($comments, $allCommentsFlat);

        // Step 2: Build hierarchy recursively based on PARENTID
        $rootMessages = self::buildHierarchy($allCommentsFlat);

        // Step 3: Sort recursively by date
        self::sortCommentsRecursive($rootMessages);

        // Step 4: Flatten ALL replies (both editor and author) to same level UNDER their root message
        $flattenedMessages = self::flattenAllReplies($rootMessages);

        // Step 5: Sort root messages by date (replies are already sorted under each root)
        uasort($flattenedMessages, function($a, $b) {
            $timeA = isset($a['WHEN']) ? strtotime($a['WHEN']) : 0;
            $timeB = isset($b['WHEN']) ? strtotime($b['WHEN']) : 0;
            return $timeA <=> $timeB;
        });

        return $flattenedMessages;
    }

    /**
     * Recursively collect all comments into a flat array
     *
     * @param array $comments Nested comment structure
     * @param array $flatArray Output array (passed by reference)
     */
    private static function collectAllComments(array $comments, array &$flatArray): void
    {
        foreach ($comments as $id => $comment) {
            // Only include comments with valid author-editor communication types
            if (isset($comment['TYPE']) && isset($comment['PCID']) && self::isValidType($comment['TYPE'])) {
                $flatArray[$id] = $comment;
            }
            if (!empty($comment['replies'])) {
                self::collectAllComments($comment['replies'], $flatArray);
            }
        }
    }

    /**
     * Recursively find a comment by PCID in the structure
     *
     * Searches in two ways:
     * 1. First by array key (fast path when key matches PCID)
     * 2. Then by PCID property value (handles cases where key != PCID)
     *
     * @param array $comments Comment array to search
     * @param int|string $pcid PCID to find
     * @return array|null Found comment or null
     */
    private static function findCommentById(array $comments, $pcid): ?array
    {
        // Fast path: check if array key matches PCID
        if (isset($comments[$pcid])) {
            return $comments[$pcid];
        }

        // Search by PCID property value
        foreach ($comments as $comment) {
            // Check if this comment matches by PCID property
            if (isset($comment['PCID']) && (int)$comment['PCID'] === (int)$pcid) {
                return $comment;
            }
            // Recursively search in replies
            if (!empty($comment['replies'])) {
                $found = self::findCommentById($comment['replies'], $pcid);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
    }

    /**
     * Recursively build hierarchy based on PARENTID
     *
     * This function handles multi-level reply chains by making multiple passes
     * to ensure all replies are correctly placed under their parents, even when
     * the parent is itself a reply.
     *
     * @param array $allComments Flat array of all comments
     * @return array Hierarchical structure with root messages and nested replies
     */
    private static function buildHierarchy(array $allComments): array
    {
        $rootMessages = [];
        $allReplies = [];

        // Separate root messages from replies
        foreach ($allComments as $id => $comment) {
            if (empty($comment['PARENTID'])) {
                $rootMessages[$id] = $comment;
                $rootMessages[$id]['replies'] = [];
            } else {
                $allReplies[$id] = $comment;
            }
        }

        // Place all replies (multiple passes if needed)
        self::placeReplies($rootMessages, $allReplies);

        return $rootMessages;
    }

    /**
     * Recursively place replies under their parents
     *
     * Uses multiple passes to ensure all replies are placed, since some replies
     * may have parents that are themselves replies (multi-level threads).
     *
     * @param array $structure Root structure to place replies into (passed by reference)
     * @param array $replies Array of replies to place
     */
    private static function placeReplies(array &$structure, array $replies): void
    {
        $remaining = $replies;

        // Keep trying until no more replies can be placed
        while (!empty($remaining)) {
            $beforeCount = count($remaining);

            foreach ($remaining as $replyId => $reply) {
                $parentId = $reply['PARENTID'];

                // Try to find parent in structure
                $found = false;
                self::findAndPlace($structure, $parentId, $replyId, $reply, $found);

                if ($found) {
                    unset($remaining[$replyId]);
                }
            }

            // If no replies were placed in this pass, break to avoid infinite loop
            if (count($remaining) === $beforeCount) {
                // Log warning for orphaned replies that couldn't be placed
                foreach ($remaining as $replyId => $reply) {
                    trigger_error(
                        "Orphaned reply in placeReplies: PCID " . ($reply['PCID'] ?? 'unknown') .
                        " with PARENTID " . ($reply['PARENTID'] ?? 'unknown'),
                        E_USER_WARNING
                    );
                }
                break;
            }
        }
    }

    /**
     * Find parent and place reply under it
     *
     * @param array $items Items to search (passed by reference)
     * @param int|string $parentId PCID of parent to find
     * @param int|string $replyId ID of reply to place
     * @param array $reply Reply data
     * @param bool $found Flag indicating if parent was found (passed by reference)
     */
    private static function findAndPlace(array &$items, $parentId, $replyId, array $reply, bool &$found): void
    {
        foreach ($items as $key => &$item) {
            if (isset($item['PCID']) && (int)$item['PCID'] === (int)$parentId) {
                if (!isset($item['replies'])) {
                    $item['replies'] = [];
                }
                $item['replies'][$replyId] = $reply;
                $found = true;
                return;
            }
            if (!empty($item['replies'])) {
                self::findAndPlace($item['replies'], $parentId, $replyId, $reply, $found);
                if ($found) return;
            }
        }
        unset($item);
    }

    /**
     * Recursively sort comments by date
     *
     * @param array $comments Comments to sort (passed by reference)
     */
    private static function sortCommentsRecursive(array &$comments): void
    {
        uasort($comments, function($a, $b) {
            $timeA = isset($a['WHEN']) ? strtotime($a['WHEN']) : 0;
            $timeB = isset($b['WHEN']) ? strtotime($b['WHEN']) : 0;
            return $timeA <=> $timeB;
        });
        foreach ($comments as &$comment) {
            if (!empty($comment['replies'])) {
                self::sortCommentsRecursive($comment['replies']);
            }
        }
        unset($comment);
    }

    /**
     * Flatten ALL replies (both editor and author) to same level UNDER their root message
     *
     * This creates a 2-level structure:
     * - Root messages (PARENTID = null)
     * - All replies directly under their root (flattened, regardless of original nesting)
     *
     * Each reply gets a REPLY_TO_INFO field indicating which message it's replying to.
     *
     * @param array $rootMessages Hierarchical root messages
     * @return array Flattened structure with all replies at the same level
     */
    private static function flattenAllReplies(array $rootMessages): array
    {
        // Collect ALL replies (any type with PARENTID) and group them by root message
        $repliesByRoot = [];

        // Collect all replies (recursively from all root messages)
        foreach ($rootMessages as $rootMessage) {
            if (!empty($rootMessage['replies'])) {
                self::collectAllReplies($rootMessage['replies'], $rootMessages, $repliesByRoot);
            }
        }

        // Build flattened structure: keep root messages, add all replies under their root
        $flattenedMessages = [];
        foreach ($rootMessages as $rootId => $rootMessage) {
            $flattenedRoot = $rootMessage;
            $flattenedRoot['replies'] = [];

            // Add all replies that belong to this root (flattened to same level)
            if (isset($repliesByRoot[$rootId])) {
                foreach ($repliesByRoot[$rootId] as $replyId => $data) {
                    $reply = $data['comment'];
                    $parent = $data['parent'];

                    // Add REPLY_TO_INFO to indicate which message this is replying to
                    if ($parent !== null && isset($parent['PCID'])) {
                        $reply['REPLY_TO_INFO'] = [
                            'PCID' => $parent['PCID'],
                            'SCREEN_NAME' => $parent['SCREEN_NAME'] ?? 'Unknown',
                            'WHEN' => $parent['WHEN'] ?? '',
                            'MESSAGE_PREVIEW' => isset($parent['MESSAGE'])
                                ? mb_substr(strip_tags($parent['MESSAGE']), 0, 100)
                                : ''
                        ];
                    } else {
                        // Orphaned reply: parent not found or missing PCID, set empty REPLY_TO_INFO
                        $reply['REPLY_TO_INFO'] = null;
                    }

                    // Clear nested replies (they will be collected separately if they exist)
                    $reply['replies'] = [];

                    $flattenedRoot['replies'][$replyId] = $reply;
                }

                // Sort replies by date under this root
                uasort($flattenedRoot['replies'], function($a, $b) {
                    $timeA = isset($a['WHEN']) ? strtotime($a['WHEN']) : 0;
                    $timeB = isset($b['WHEN']) ? strtotime($b['WHEN']) : 0;
                    return $timeA <=> $timeB;
                });
            }

            $flattenedMessages[$rootId] = $flattenedRoot;
        }

        return $flattenedMessages;
    }

    /**
     * Collect all replies recursively and group them by root message
     *
     * @param array $comments Comments to process
     * @param array $rootMessages Root messages structure for lookups
     * @param array $repliesByRoot Output array grouped by root (passed by reference)
     */
    private static function collectAllReplies(array $comments, array $rootMessages, array &$repliesByRoot): void
    {
        foreach ($comments as $id => $comment) {
            // Check if this is a valid reply (valid type with PARENTID)
            if (isset($comment['TYPE']) &&
                self::isValidType($comment['TYPE']) &&
                isset($comment['PCID']) &&
                !empty($comment['PARENTID'])) {

                // Get the full comment with all its replies from the structure
                $fullComment = self::findCommentById($rootMessages, $comment['PCID']);

                // Use full comment if found and has required fields, otherwise fall back to original
                if ($fullComment !== null && isset($fullComment['TYPE']) && isset($fullComment['PCID'])) {
                    $commentToFlatten = $fullComment;
                } elseif (isset($comment['TYPE']) && isset($comment['PCID'])) {
                    $commentToFlatten = $comment;
                } else {
                    // Skip corrupted comment without required fields
                    trigger_error("Skipping comment without required TYPE or PCID fields", E_USER_WARNING);
                    continue;
                }

                // Find the root message for this reply
                $rootMessage = self::findRootMessage($commentToFlatten, $rootMessages);
                if ($rootMessage !== null && isset($rootMessage['PCID'])) {
                    $rootId = $rootMessage['PCID'];

                    // Find parent for REPLY_TO_INFO
                    $parent = self::findCommentById($rootMessages, $commentToFlatten['PARENTID']);

                    // Group by root message
                    if (!isset($repliesByRoot[$rootId])) {
                        $repliesByRoot[$rootId] = [];
                    }

                    if ($parent !== null) {
                        $repliesByRoot[$rootId][$id] = [
                            'comment' => $commentToFlatten,
                            'parent' => $parent
                        ];
                    } else {
                        // Orphaned reply: parent not found, still display but log warning
                        trigger_error(
                            "Orphaned comment: PCID " . $commentToFlatten['PCID'] .
                            " has PARENTID " . $commentToFlatten['PARENTID'] . " which was not found",
                            E_USER_WARNING
                        );
                        // Still include the reply but without parent info
                        $repliesByRoot[$rootId][$id] = [
                            'comment' => $commentToFlatten,
                            'parent' => null  // No parent info available
                        ];
                    }
                }
            }

            // Continue recursively to collect all nested replies
            if (!empty($comment['replies'])) {
                self::collectAllReplies($comment['replies'], $rootMessages, $repliesByRoot);
            }
        }
    }

    /**
     * Find the root message for a given comment by traversing PARENTID chain
     *
     * Includes cycle detection to prevent infinite loops from corrupted data.
     *
     * @param array $comment Comment to find root for
     * @param array $rootMessages Root messages structure
     * @param array $visited Already visited PCIDs (for cycle detection)
     * @return array|null Root message or null
     */
    private static function findRootMessage(array $comment, array $rootMessages, array $visited = []): ?array
    {
        // If no PARENTID, this is a root message
        if (empty($comment['PARENTID'])) {
            return $comment;
        }

        // Cycle detection: check if we've already visited this comment
        $pcid = $comment['PCID'] ?? null;
        if ($pcid !== null && in_array((int)$pcid, $visited, true)) {
            trigger_error(
                "Circular reference detected in comment hierarchy at PCID: " . $pcid,
                E_USER_WARNING
            );
            return null;
        }

        // Add current PCID to visited set
        if ($pcid !== null) {
            $visited[] = (int)$pcid;
        }

        // Find parent
        $parent = self::findCommentById($rootMessages, $comment['PARENTID']);
        if ($parent === null) {
            return null;
        }

        // If parent has no PARENTID, it's the root
        if (empty($parent['PARENTID'])) {
            return $parent;
        }

        // Recursively find root with visited set
        return self::findRootMessage($parent, $rootMessages, $visited);
    }
}