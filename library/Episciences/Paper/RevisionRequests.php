<?php

declare(strict_types=1);

namespace Episciences\Paper;

use Episciences_CommentsManager;
use Episciences_Paper;

/**
 * Revision requests of a paper and the answers to them, as shown on the paper pages
 * (PaperController::viewAction() and AdministratepaperController::viewAction()).
 */
final class RevisionRequests
{
    /** Comments of a revision request and of the answers to it */
    public const COMMENT_TYPES = [
        Episciences_CommentsManager::TYPE_REVISION_REQUEST,
        Episciences_CommentsManager::TYPE_REVISION_ANSWER_COMMENT,
        Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION,
        Episciences_CommentsManager::TYPE_REVISION_ANSWER_NEW_VERSION,
        Episciences_CommentsManager::TYPE_REVISION_CONTACT_COMMENT,
    ];

    /**
     * Settings of Episciences_CommentsManager::getList() selecting these comments.
     *
     * @return array{types: list<int>}
     */
    public static function commentSettings(): array
    {
        return ['types' => self::COMMENT_TYPES];
    }

    /**
     * Revision requests and answers of the previous versions, keyed by version DOCID.
     * The versions without any are left out.
     *
     * @param Episciences_Paper[] $previousVersions
     * @return array<int, array<mixed>>
     */
    public static function ofPreviousVersions(array $previousVersions): array
    {
        $requests = [];

        foreach ($previousVersions as $version) {
            $comments = $version->getComments(self::commentSettings());
            if ($comments) {
                $requests[(int)$version->getDocid()] = $comments;
            }
        }

        return $requests;
    }

    /**
     * Deadline of the latest revision request while the author has not answered it.
     * A question to the editor (contact comment) does not answer the request.
     *
     * @param array<string, mixed> $latestRequest
     */
    public static function pendingDeadline(array $latestRequest): ?string
    {
        if (array_key_exists('replies', $latestRequest)) {
            $replies = $latestRequest['replies'];
            if (!is_array($replies) || $replies === []) {
                return null;
            }

            $firstReply = reset($replies);
            if (!is_array($firstReply) || (int)($firstReply['TYPE'] ?? 0) !== Episciences_CommentsManager::TYPE_REVISION_CONTACT_COMMENT) {
                return null;
            }
        }

        $deadline = $latestRequest['DEADLINE'] ?? null;

        return $deadline === null ? null : (string)$deadline;
    }
}
