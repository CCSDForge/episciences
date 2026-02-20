<?php

namespace unit\library\Episciences;

use Episciences_CommentsManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_CommentsManager
 *
 * Tests cover: constants, static typed arrays, and the two methods
 * that short-circuit before touching the database.
 * All DB-dependent methods (getList, getParents, save, getComment…) are excluded.
 *
 * @covers Episciences_CommentsManager
 */
class Episciences_CommentsManagerTest extends TestCase
{
    // ---------------------------------------------------------------
    // Integer TYPE_* constants
    // ---------------------------------------------------------------

    public function testTypeConstants(): void
    {
        $this->assertSame(0,  Episciences_CommentsManager::TYPE_INFO_REQUEST);
        $this->assertSame(1,  Episciences_CommentsManager::TYPE_INFO_ANSWER);
        $this->assertSame(2,  Episciences_CommentsManager::TYPE_REVISION_REQUEST);
        $this->assertSame(3,  Episciences_CommentsManager::TYPE_REVISION_ANSWER_COMMENT);
        $this->assertSame(4,  Episciences_CommentsManager::TYPE_AUTHOR_COMMENT);
        $this->assertSame(5,  Episciences_CommentsManager::TYPE_REVISION_CONTACT_COMMENT);
        $this->assertSame(6,  Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION);
        $this->assertSame(7,  Episciences_CommentsManager::TYPE_REVISION_ANSWER_NEW_VERSION);
        $this->assertSame(8,  Episciences_CommentsManager::TYPE_SUGGESTION_ACCEPTATION);
        $this->assertSame(9,  Episciences_CommentsManager::TYPE_SUGGESTION_REFUS);
        $this->assertSame(10, Episciences_CommentsManager::TYPE_SUGGESTION_NEW_VERSION);
        $this->assertSame(11, Episciences_CommentsManager::TYPE_CONTRIBUTOR_TO_REVIEWER);
        $this->assertSame(12, Episciences_CommentsManager::TYPE_EDITOR_COMMENT);
        $this->assertSame(13, Episciences_CommentsManager::TYPE_EDITOR_MONITORING_REFUSED);
        $this->assertSame(14, Episciences_CommentsManager::TYPE_AUTHOR_SOURCES_DEPOSED_ANSWER);
        $this->assertSame(15, Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_FORMATTING_REQUEST);
        $this->assertSame(16, Episciences_CommentsManager::TYPE_AUTHOR_FORMATTING_ANSWER);
        $this->assertSame(17, Episciences_CommentsManager::TYPE_AUTHOR_FORMATTING_VALIDATED_REQUEST);
        $this->assertSame(18, Episciences_CommentsManager::TYPE_REVIEW_FORMATTING_DEPOSED_REQUEST);
        $this->assertSame(19, Episciences_CommentsManager::TYPE_CE_AUTHOR_FINAL_VERSION_SUBMITTED);
        $this->assertSame(20, Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST);
        $this->assertSame(21, Episciences_CommentsManager::TYPE_ACCEPTED_ASK_AUTHOR_VALIDATION);
    }

    public function testStringConstants(): void
    {
        $this->assertSame('copy_editing_sources', Episciences_CommentsManager::COPY_EDITING_SOURCES);
        $this->assertSame('answerRequest',        Episciences_CommentsManager::TYPE_ANSWER_REQUEST);
    }

    // ---------------------------------------------------------------
    // Static typed arrays
    // ---------------------------------------------------------------

    public function testSuggestionTypesContainsExactlyThreeTypes(): void
    {
        $this->assertCount(3, Episciences_CommentsManager::$suggestionTypes);
    }

    public function testSuggestionTypesContents(): void
    {
        $this->assertContains(Episciences_CommentsManager::TYPE_SUGGESTION_ACCEPTATION, Episciences_CommentsManager::$suggestionTypes);
        $this->assertContains(Episciences_CommentsManager::TYPE_SUGGESTION_REFUS,       Episciences_CommentsManager::$suggestionTypes);
        $this->assertContains(Episciences_CommentsManager::TYPE_SUGGESTION_NEW_VERSION, Episciences_CommentsManager::$suggestionTypes);
    }

    public function testCopyEditingRequestTypesContents(): void
    {
        $types = Episciences_CommentsManager::$_copyEditingRequestTypes;

        $this->assertContains(Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST,    $types);
        $this->assertContains(Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_FORMATTING_REQUEST, $types);
        $this->assertContains(Episciences_CommentsManager::TYPE_AUTHOR_FORMATTING_VALIDATED_REQUEST,   $types);
        $this->assertContains(Episciences_CommentsManager::TYPE_REVIEW_FORMATTING_DEPOSED_REQUEST,     $types);
        $this->assertCount(4, $types);
    }

    public function testCopyEditingAnswerTypesContents(): void
    {
        $types = Episciences_CommentsManager::$_copyEditingAnswerTypes;

        $this->assertContains(Episciences_CommentsManager::TYPE_AUTHOR_SOURCES_DEPOSED_ANSWER,    $types);
        $this->assertContains(Episciences_CommentsManager::TYPE_AUTHOR_FORMATTING_ANSWER,          $types);
        $this->assertContains(Episciences_CommentsManager::TYPE_CE_AUTHOR_FINAL_VERSION_SUBMITTED, $types);
        $this->assertCount(3, $types);
    }

    public function testCopyEditingFinalVersionRequestContents(): void
    {
        $types = Episciences_CommentsManager::$_copyEditingFinalVersionRequest;

        $this->assertContains(Episciences_CommentsManager::TYPE_AUTHOR_FORMATTING_VALIDATED_REQUEST, $types);
        $this->assertContains(Episciences_CommentsManager::TYPE_REVIEW_FORMATTING_DEPOSED_REQUEST,   $types);
        $this->assertContains(Episciences_CommentsManager::TYPE_ACCEPTED_ASK_AUTHOR_VALIDATION,      $types);
        $this->assertCount(3, $types);
    }

    public function testUploadFilesRequestContents(): void
    {
        $types = Episciences_CommentsManager::$_UploadFilesRequest;

        $this->assertContains(Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_FORMATTING_REQUEST, $types);
        $this->assertContains(Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST,    $types);
        $this->assertCount(2, $types);
    }

    // ---------------------------------------------------------------
    // $_typeLabel completeness
    // ---------------------------------------------------------------

    public function testTypeLabelCoversAllDefinedTypes(): void
    {
        $allTypes = [
            Episciences_CommentsManager::TYPE_INFO_REQUEST,
            Episciences_CommentsManager::TYPE_INFO_ANSWER,
            Episciences_CommentsManager::TYPE_REVISION_REQUEST,
            Episciences_CommentsManager::TYPE_REVISION_ANSWER_COMMENT,
            Episciences_CommentsManager::TYPE_REVISION_ANSWER_TMP_VERSION,
            Episciences_CommentsManager::TYPE_REVISION_ANSWER_NEW_VERSION,
            Episciences_CommentsManager::TYPE_SUGGESTION_ACCEPTATION,
            Episciences_CommentsManager::TYPE_SUGGESTION_REFUS,
            Episciences_CommentsManager::TYPE_SUGGESTION_NEW_VERSION,
            Episciences_CommentsManager::TYPE_EDITOR_MONITORING_REFUSED,
            Episciences_CommentsManager::TYPE_EDITOR_COMMENT,
            Episciences_CommentsManager::TYPE_AUTHOR_COMMENT,
            Episciences_CommentsManager::TYPE_CONTRIBUTOR_TO_REVIEWER,
            Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST,
            Episciences_CommentsManager::TYPE_AUTHOR_SOURCES_DEPOSED_ANSWER,
            Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_FORMATTING_REQUEST,
            Episciences_CommentsManager::TYPE_AUTHOR_FORMATTING_ANSWER,
            Episciences_CommentsManager::TYPE_AUTHOR_FORMATTING_VALIDATED_REQUEST,
            Episciences_CommentsManager::TYPE_REVIEW_FORMATTING_DEPOSED_REQUEST,
            Episciences_CommentsManager::TYPE_CE_AUTHOR_FINAL_VERSION_SUBMITTED,
            Episciences_CommentsManager::TYPE_ACCEPTED_ASK_AUTHOR_VALIDATION,
            Episciences_CommentsManager::TYPE_REVISION_CONTACT_COMMENT,
        ];

        foreach ($allTypes as $type) {
            $this->assertArrayHasKey(
                $type,
                Episciences_CommentsManager::$_typeLabel,
                "Type $type has no entry in \$_typeLabel"
            );
        }
    }

    public function testTypeLabelValuesAreNonEmptyStrings(): void
    {
        foreach (Episciences_CommentsManager::$_typeLabel as $type => $label) {
            $this->assertIsString($label, "Label for type $type should be a string");
            $this->assertNotEmpty($label, "Label for type $type should not be empty");
        }
    }

    // ---------------------------------------------------------------
    // updateUid – short-circuit branch (no DB needed)
    // ---------------------------------------------------------------

    public function testUpdateUidWithZeroOldUidReturnsZero(): void
    {
        $result = Episciences_CommentsManager::updateUid(0, 99);
        $this->assertSame(0, $result);
    }

    public function testUpdateUidWithZeroNewUidReturnsZero(): void
    {
        $result = Episciences_CommentsManager::updateUid(42, 0);
        $this->assertSame(0, $result);
    }

    public function testUpdateUidWithBothZeroReturnsZero(): void
    {
        $result = Episciences_CommentsManager::updateUid(0, 0);
        $this->assertSame(0, $result);
    }

    public function testUpdateUidWithNegativeOldUidReturnsZero(): void
    {
        $result = Episciences_CommentsManager::updateUid(-1, 99);
        $this->assertSame(0, $result);
    }

    public function testUpdateUidWithNegativeNewUidReturnsZero(): void
    {
        $result = Episciences_CommentsManager::updateUid(42, -1);
        $this->assertSame(0, $result);
    }

    // ---------------------------------------------------------------
    // deleteByDocid – short-circuit branch (no DB needed)
    // ---------------------------------------------------------------

    public function testDeleteByDocidWithZeroReturnsFalse(): void
    {
        $this->assertFalse(Episciences_CommentsManager::deleteByDocid(0));
    }

    public function testDeleteByDocidWithNegativeIdReturnsFalse(): void
    {
        $this->assertFalse(Episciences_CommentsManager::deleteByDocid(-1));
    }
}
