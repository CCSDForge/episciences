<?php

declare(strict_types=1);

namespace Episciences\Paper\Files;

use Episciences\Files\FileManager;
use Episciences_CommentsManager;

/**
 * Who may read a file stored in a sub-directory of a paper version (comments, copy-editing sources,
 * data descriptor), mirroring what the paper pages show to each user:
 *
 * - the data descriptor of a published paper is shown on its public page;
 * - the author and co-authors read every file of their paper;
 * - a user in conflict of interest with the paper reads nothing else;
 * - the editorial staff of the paper (secretaries, assigned editors and copy editors, editors and copy
 *   editors of a journal that does not restrict them to their assigned papers) read every file;
 * - reviewers read the data descriptor and the files of the comments they are shown: their exchanges
 *   with the author and the author's cover letter. Copy-editing files are not for them.
 */
final class DocumentFilePolicy
{
    public const FOLDER_COMMENTS = 'comments';
    public const FOLDER_COPY_EDITING = Episciences_CommentsManager::COPY_EDITING_SOURCES;
    public const FOLDER_DATA_DESCRIPTOR = FileManager::DD_SOURCE;

    /** Types of the comments shown to the reviewers of a paper */
    public const REVIEWER_COMMENT_TYPES = [
        Episciences_CommentsManager::TYPE_INFO_REQUEST,
        Episciences_CommentsManager::TYPE_INFO_ANSWER,
        Episciences_CommentsManager::TYPE_CONTRIBUTOR_TO_REVIEWER,
        Episciences_CommentsManager::TYPE_AUTHOR_COMMENT,
    ];

    /**
     * @param string $folder sub-directory of the paper version (one of the FOLDER_* constants)
     * @param bool $isPublished the paper version is published
     * @param bool $isOwnerOrCoAuthor the user is the owner or a co-author of the paper
     * @param bool $hasConflict the user is in conflict of interest with the paper (declared or not answered yet)
     * @param bool $isEditorialStaff the user handles the paper for the journal (see class description)
     * @param bool $isReviewer the user is an active reviewer of the paper version
     * @param int[] $commentTypes types of the comments the requested file is attached to (comments folder only)
     */
    public static function isAllowed(
        string $folder,
        bool   $isPublished,
        bool   $isOwnerOrCoAuthor,
        bool   $hasConflict,
        bool   $isEditorialStaff,
        bool   $isReviewer,
        array  $commentTypes = []
    ): bool
    {
        if ($folder === self::FOLDER_DATA_DESCRIPTOR && $isPublished) {
            return true;
        }

        if ($isOwnerOrCoAuthor) {
            return true;
        }

        if ($hasConflict) {
            return false;
        }

        if ($isEditorialStaff) {
            return true;
        }

        if (!$isReviewer) {
            return false;
        }

        return match ($folder) {
            self::FOLDER_DATA_DESCRIPTOR => true,
            self::FOLDER_COMMENTS => array_intersect($commentTypes, self::REVIEWER_COMMENT_TYPES) !== [],
            default => false,
        };
    }
}
