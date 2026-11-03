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
    /**
     * @return array<string, array{string}>
     */
    public static function foldersProvider(): array
    {
        $cases = [];
        foreach (DocumentFilePolicy::FOLDERS as $folder) {
            $cases[$folder] = [$folder];
        }
        return $cases;
    }

    #[DataProvider('foldersProvider')]
    public function testAnyOtherMemberReadsNothingOfAnUnpublishedPaper(string $folder): void
    {
        self::assertFalse(DocumentFilePolicy::isAllowed($folder, false, false, false, false, false));
    }

    public function testAuthorReadsTheCopyEditingFilesAndTheDataDescriptor(): void
    {
        self::assertTrue(DocumentFilePolicy::isAllowed(DocumentFilePolicy::FOLDER_COPY_EDITING, false, true, false, false, false));
        self::assertTrue(DocumentFilePolicy::isAllowed(DocumentFilePolicy::FOLDER_DATA_DESCRIPTOR, false, true, false, false, false));
    }

    /**
     * @return array<string, array{int[], bool}>
     */
    public static function authorCommentsProvider(): array
    {
        return [
            'cover letter' => [[Episciences_CommentsManager::TYPE_AUTHOR_COMMENT], true],
            'clarification request' => [[Episciences_CommentsManager::TYPE_INFO_REQUEST], true],
            'revision request' => [[Episciences_CommentsManager::TYPE_REVISION_REQUEST], true],
            'editor to author' => [[Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR], true],
            'editor comment' => [[Episciences_CommentsManager::TYPE_EDITOR_COMMENT], false],
            'acceptance suggestion' => [[Episciences_CommentsManager::TYPE_SUGGESTION_ACCEPTATION], false],
            'refusal suggestion' => [[Episciences_CommentsManager::TYPE_SUGGESTION_REFUS], false],
            'revision suggestion' => [[Episciences_CommentsManager::TYPE_SUGGESTION_NEW_VERSION], false],
            'monitoring refused' => [[Episciences_CommentsManager::TYPE_EDITOR_MONITORING_REFUSED], false],
            'file of no comment' => [[], false],
            'same file name on a shown and an internal comment' => [
                [Episciences_CommentsManager::TYPE_AUTHOR_COMMENT, Episciences_CommentsManager::TYPE_EDITOR_COMMENT],
                false,
            ],
        ];
    }

    /**
     * @param int[] $commentTypes
     */
    #[DataProvider('authorCommentsProvider')]
    public function testAuthorReadsTheFilesOfTheCommentsTheyAreShown(array $commentTypes, bool $expected): void
    {
        self::assertSame(
            $expected,
            DocumentFilePolicy::isAllowed(DocumentFilePolicy::FOLDER_COMMENTS, false, true, false, false, false, $commentTypes)
        );
    }

    public function testAuthorInConflictStillReadsTheirFiles(): void
    {
        self::assertTrue(DocumentFilePolicy::isAllowed(DocumentFilePolicy::FOLDER_COPY_EDITING, false, true, true, false, false));
    }

    public function testEditorialStaffAuthorReadsTheInternalComments(): void
    {
        self::assertTrue(DocumentFilePolicy::isAllowed(
            DocumentFilePolicy::FOLDER_COMMENTS, false, true, false, true, false, [Episciences_CommentsManager::TYPE_EDITOR_COMMENT]
        ));
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
            $folder, false, false, true, true, false, [Episciences_CommentsManager::TYPE_EDITOR_COMMENT]
        ));
    }

    public function testConflictOfInterestLeavesAReviewerWhatTheReviewingPageShows(): void
    {
        $shownComment = [Episciences_CommentsManager::TYPE_INFO_REQUEST];

        self::assertTrue(DocumentFilePolicy::isAllowed(DocumentFilePolicy::FOLDER_COMMENTS, false, false, true, true, true, $shownComment));
        self::assertTrue(DocumentFilePolicy::isAllowed(DocumentFilePolicy::FOLDER_DATA_DESCRIPTOR, false, false, true, true, true));
        self::assertFalse(DocumentFilePolicy::isAllowed(DocumentFilePolicy::FOLDER_COPY_EDITING, false, false, true, true, true));
        self::assertFalse(DocumentFilePolicy::isAllowed(
            DocumentFilePolicy::FOLDER_COMMENTS, false, false, true, true, true, [Episciences_CommentsManager::TYPE_EDITOR_COMMENT]
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
            'same file name on two shown comments' => [
                [Episciences_CommentsManager::TYPE_AUTHOR_COMMENT, Episciences_CommentsManager::TYPE_INFO_ANSWER],
                true,
            ],
            'same file name on a shown and a hidden comment' => [
                [Episciences_CommentsManager::TYPE_INFO_ANSWER, Episciences_CommentsManager::TYPE_EDITOR_TO_AUTHOR],
                false,
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

    public function testUnknownFolderIsRefusedToEveryRole(): void
    {
        self::assertFalse(DocumentFilePolicy::isAllowed('reports', false, true, false, true, true));
    }

    public function testOnlyThePublishedDataDescriptorIsPublic(): void
    {
        self::assertTrue(DocumentFilePolicy::isPublic(DocumentFilePolicy::FOLDER_DATA_DESCRIPTOR, true));
        self::assertFalse(DocumentFilePolicy::isPublic(DocumentFilePolicy::FOLDER_DATA_DESCRIPTOR, false));
        self::assertFalse(DocumentFilePolicy::isPublic(DocumentFilePolicy::FOLDER_COMMENTS, true));
        self::assertFalse(DocumentFilePolicy::isPublic(DocumentFilePolicy::FOLDER_COPY_EDITING, true));
    }

    public function testAnyOtherMemberDoesNotReadTheTemporaryVersionFiles(): void
    {
        self::assertFalse(DocumentFilePolicy::isTemporaryVersionFileAllowed(false, false, false, false));
    }

    public function testAuthorReadsTheTemporaryVersionFiles(): void
    {
        self::assertTrue(DocumentFilePolicy::isTemporaryVersionFileAllowed(true, false, false, false));
    }

    public function testReviewerReadsTheTemporaryVersionFiles(): void
    {
        self::assertTrue(DocumentFilePolicy::isTemporaryVersionFileAllowed(false, false, false, true));
    }

    public function testEditorialStaffReadsTheTemporaryVersionFilesUnlessInConflictOfInterest(): void
    {
        self::assertTrue(DocumentFilePolicy::isTemporaryVersionFileAllowed(false, false, true, false));
        self::assertFalse(DocumentFilePolicy::isTemporaryVersionFileAllowed(false, true, true, false));
    }

    public function testAuthorWritesTheTemporaryVersionFiles(): void
    {
        self::assertTrue(DocumentFilePolicy::isTemporaryVersionFileWritable(true, false, false));
    }

    public function testEditorialStaffWritesTheTemporaryVersionFilesUnlessInConflictOfInterest(): void
    {
        self::assertTrue(DocumentFilePolicy::isTemporaryVersionFileWritable(false, false, true));
        self::assertFalse(DocumentFilePolicy::isTemporaryVersionFileWritable(false, true, true));
    }

    public function testAnyOtherMemberDoesNotWriteTheTemporaryVersionFiles(): void
    {
        // Reviewers among them: they read the author's answer but must not alter it
        self::assertFalse(DocumentFilePolicy::isTemporaryVersionFileWritable(false, false, false));
    }

    public function testAuthorWritesTheCopyEditingFiles(): void
    {
        self::assertTrue(DocumentFilePolicy::isCopyEditingFileWritable(true, false, false));
    }

    public function testEditorialStaffWritesTheCopyEditingFilesUnlessInConflictOfInterest(): void
    {
        self::assertTrue(DocumentFilePolicy::isCopyEditingFileWritable(false, false, true));
        self::assertFalse(DocumentFilePolicy::isCopyEditingFileWritable(false, true, true));
    }

    public function testAnyOtherMemberDoesNotWriteTheCopyEditingFiles(): void
    {
        // Reviewers among them: copy-editing files are not for them
        self::assertFalse(DocumentFilePolicy::isCopyEditingFileWritable(false, false, false));
    }
}
