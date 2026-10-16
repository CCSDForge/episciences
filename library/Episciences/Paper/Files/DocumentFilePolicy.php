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
 * - the author and co-authors read the copy-editing files, the data descriptor and the files of the
 *   comments they are shown (every comment but the exchanges internal to the editorial staff);
 * - the editorial staff of the paper (secretaries, assigned editors and copy editors, editors and copy
 *   editors of a journal that does not restrict them to their assigned papers) read every file, unless
 *   they are in conflict of interest with the paper;
 * - reviewers read the data descriptor and the files of the comments they are shown: their exchanges
 *   with the author and the author's cover letter. Copy-editing files are not for them. A conflict of
 *   interest, which concerns the editorial role, does not withdraw what the reviewing page shows them.
 *
 * The files of the temporary versions are stored by paper, not by version (see isTemporaryVersionFileAllowed()).
 */
final class DocumentFilePolicy
{
    public const FOLDER_COMMENTS = 'comments';
    public const FOLDER_COPY_EDITING = Episciences_CommentsManager::COPY_EDITING_SOURCES;
    public const FOLDER_DATA_DESCRIPTOR = FileManager::DD_SOURCE;

    /** Sub-directories of a paper version governed by this policy */
    public const FOLDERS = [self::FOLDER_COMMENTS, self::FOLDER_COPY_EDITING, self::FOLDER_DATA_DESCRIPTOR];

    /** Types of the comments shown to the reviewers of a paper */
    public const REVIEWER_COMMENT_TYPES = [
        Episciences_CommentsManager::TYPE_INFO_REQUEST,
        Episciences_CommentsManager::TYPE_INFO_ANSWER,
        Episciences_CommentsManager::TYPE_CONTRIBUTOR_TO_REVIEWER,
        Episciences_CommentsManager::TYPE_AUTHOR_COMMENT,
    ];

    /** Types of the comments exchanged within the editorial staff, never shown to the author */
    public const EDITORIAL_COMMENT_TYPES = [
        Episciences_CommentsManager::TYPE_SUGGESTION_ACCEPTATION,
        Episciences_CommentsManager::TYPE_SUGGESTION_REFUS,
        Episciences_CommentsManager::TYPE_SUGGESTION_NEW_VERSION,
        Episciences_CommentsManager::TYPE_EDITOR_COMMENT,
        Episciences_CommentsManager::TYPE_EDITOR_MONITORING_REFUSED,
    ];

    /**
     * Files anyone may read, whatever their roles on the paper
     */
    public static function isPublic(string $folder, bool $isPublished): bool
    {
        return $folder === self::FOLDER_DATA_DESCRIPTOR && $isPublished;
    }

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
        if (self::isPublic($folder, $isPublished)) {
            return true;
        }

        if ($isEditorialStaff && !$hasConflict && in_array($folder, self::FOLDERS, true)) {
            return true;
        }

        if ($isOwnerOrCoAuthor && self::isShownToAuthor($folder, $commentTypes)) {
            return true;
        }

        return $isReviewer && self::isShownToReviewer($folder, $commentTypes);
    }

    /**
     * Files of the temporary versions, kept in the directory of the paper and shown with the revision answer
     * and the temporary version: the author and co-authors, the editorial staff of the paper unless they are
     * in conflict of interest, and the reviewers, who are given the temporary version to review.
     *
     * @param bool $isOwnerOrCoAuthor the user is the owner or a co-author of the paper
     * @param bool $hasConflict the user is in conflict of interest with the paper (declared or not answered yet)
     * @param bool $isEditorialStaff the user handles the paper for the journal (see class description)
     * @param bool $isReviewer the user is a reviewer of a version of the paper
     */
    public static function isTemporaryVersionFileAllowed(
        bool $isOwnerOrCoAuthor,
        bool $hasConflict,
        bool $isEditorialStaff,
        bool $isReviewer
    ): bool
    {
        return $isOwnerOrCoAuthor || ($isEditorialStaff && !$hasConflict) || $isReviewer;
    }

    /**
     * @param int[] $commentTypes
     */
    private static function isShownToAuthor(string $folder, array $commentTypes): bool
    {
        return match ($folder) {
            self::FOLDER_COPY_EDITING, self::FOLDER_DATA_DESCRIPTOR => true,
            // The folder is shared by all the comments of the version: a name also carried by an internal
            // comment of the editorial staff may be that comment's file
            self::FOLDER_COMMENTS => $commentTypes !== [] && array_intersect($commentTypes, self::EDITORIAL_COMMENT_TYPES) === [],
            default => false,
        };
    }

    /**
     * @param int[] $commentTypes
     */
    private static function isShownToReviewer(string $folder, array $commentTypes): bool
    {
        return match ($folder) {
            self::FOLDER_DATA_DESCRIPTOR => true,
            // Same as for the author: a name also carried by a comment hidden from the reviewers may be its file
            self::FOLDER_COMMENTS => $commentTypes !== [] && array_diff($commentTypes, self::REVIEWER_COMMENT_TYPES) === [],
            default => false,
        };
    }
}
