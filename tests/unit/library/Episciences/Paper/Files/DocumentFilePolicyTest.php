<?php

declare(strict_types=1);

namespace unit\library\Episciences\Paper\Files;

use Episciences\Paper\Files\DocumentFilePolicy;
use Episciences_CommentsManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\Paper\Files\DocumentFilePolicy
 */
final class DocumentFilePolicyTest extends TestCase
{
    private const FOLDERS = [
        DocumentFilePolicy::FOLDER_COMMENTS,
        DocumentFilePolicy::FOLDER_COPY_EDITING,
        DocumentFilePolicy::FOLDER_DATA_DESCRIPTOR,
    ];

    /**
     * @return array<string, array{string}>
     */
    public static function foldersProvider(): array
    {
        $cases = [];
        foreach (self::FOLDERS as $folder) {
            $cases[$folder] = [$folder];
        }
        return $cases;
    }

    #[DataProvider('foldersProvider')]
    public function testAnyOtherMemberReadsNothingOfAnUnpublishedPaper(string $folder): void
    {
        self::assertFalse(DocumentFilePolicy::isAllowed($folder, false, false, false, false, false));
    }

    #[DataProvider('foldersProvider')]
    public function testAuthorReadsEveryFileOfTheirPaper(string $folder): void
    {
        self::assertTrue(DocumentFilePolicy::isAllowed($folder, false, true, false, false, false));
    }

    #[DataProvider('foldersProvider')]
    public function testEditorialStaffReadsEveryFile(string $folder): void
    {
        self::assertTrue(DocumentFilePolicy::isAllowed($folder, false, false, false, true, false));
    }

    #[DataProvider('foldersProvider')]
    public function testConflictOfInterestPrevailsOverEditorialRole(string $folder): void
    {
        self::assertFalse(DocumentFilePolicy::isAllowed(
            $folder, false, false, true, true, true, [Episciences_CommentsManager::TYPE_INFO_REQUEST]
        ));
    }

    public function testConflictOfInterestDoesNotHideThePublicDataDescriptor(): void
    {
        self::assertTrue(DocumentFilePolicy::isAllowed(DocumentFilePolicy::FOLDER_DATA_DESCRIPTOR, true, false, true, false, false));
    }

    public function testDataDescriptorOfAPublishedPaperIsPublic(): void
    {
        self::assertTrue(DocumentFilePolicy::isAllowed(DocumentFilePolicy::FOLDER_DATA_DESCRIPTOR, true, false, false, false, false));
    }

    public function testOtherFilesOfAPublishedPaperStayPrivate(): void
    {
        self::assertFalse(DocumentFilePolicy::isAllowed(DocumentFilePolicy::FOLDER_COMMENTS, true, false, false, false, false));
        self::assertFalse(DocumentFilePolicy::isAllowed(DocumentFilePolicy::FOLDER_COPY_EDITING, true, false, false, false, false));
    }

    public function testReviewerReadsTheDataDescriptor(): void
    {
        self::assertTrue(DocumentFilePolicy::isAllowed(DocumentFilePolicy::FOLDER_DATA_DESCRIPTOR, false, false, false, false, true));
    }

    public function testReviewerDoesNotReadCopyEditingFiles(): void
    {
        self::assertFalse(DocumentFilePolicy::isAllowed(DocumentFilePolicy::FOLDER_COPY_EDITING, false, false, false, false, true));
    }

    /**
     * @return array<string, array{int[], bool}>
     */
    public static function reviewerCommentsProvider(): array
    {
        return [
            'clarification request' => [[Episciences_CommentsManager::TYPE_INFO_REQUEST], true],
            'clarification answer' => [[Episciences_CommentsManager::TYPE_INFO_ANSWER], true],
            'author to reviewer' => [[Episciences_CommentsManager::TYPE_CONTRIBUTOR_TO_REVIEWER], true],
            'cover letter' => [[Episciences_CommentsManager::TYPE_AUTHOR_COMMENT], true],
            'revision request' => [[Episciences_CommentsManager::TYPE_REVISION_REQUEST], false],
            'editor comment' => [[Episciences_CommentsManager::TYPE_EDITOR_COMMENT], false],
            'author to editor' => [[Episciences_CommentsManager::TYPE_AUTHOR_TO_EDITOR], false],
            'editor to author' => [[Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR], false],
            'file of no comment' => [[], false],
            'same file name on a shown and a hidden comment' => [
                [Episciences_CommentsManager::TYPE_EDITOR_COMMENT, Episciences_CommentsManager::TYPE_INFO_ANSWER],
                true,
            ],
        ];
    }

    /**
     * @param int[] $commentTypes
     */
    #[DataProvider('reviewerCommentsProvider')]
    public function testReviewerReadsTheFilesOfTheCommentsTheyAreShown(array $commentTypes, bool $expected): void
    {
        self::assertSame(
            $expected,
            DocumentFilePolicy::isAllowed(DocumentFilePolicy::FOLDER_COMMENTS, false, false, false, false, true, $commentTypes)
        );
    }

    public function testCommentTypesDoNotOpenAccessToANonReviewer(): void
    {
        self::assertFalse(DocumentFilePolicy::isAllowed(
            DocumentFilePolicy::FOLDER_COMMENTS, false, false, false, false, false, [Episciences_CommentsManager::TYPE_INFO_REQUEST]
        ));
    }

    public function testUnknownFolderIsRefusedToReviewers(): void
    {
        self::assertFalse(DocumentFilePolicy::isAllowed('reports', false, false, false, false, true));
    }
}
