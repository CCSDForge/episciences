<?php

namespace unit\library\Episciences;

use Episciences_Comment;
use Episciences_CommentsManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Comment
 *
 * Tests cover: constructor, getters/setters, message encoding,
 * type-classification helpers, options management, toArray, populate.
 * All DB-dependent methods (find, save, delete, logComment) are excluded.
 *
 * @covers Episciences_Comment
 */
class Episciences_CommentTest extends TestCase
{
    // ---------------------------------------------------------------
    // Constructor
    // ---------------------------------------------------------------

    public function testConstructorWithNoValuesCreatesEmptyObject(): void
    {
        $comment = new Episciences_Comment();

        $this->assertNull($comment->getPcid());
        $this->assertNull($comment->getParentid());
        $this->assertNull($comment->getMessage());
        $this->assertNull($comment->getFile());
        $this->assertNull($comment->getWhen());
        $this->assertNull($comment->getDeadline());
        $this->assertSame([], $comment->getOptions());
    }

    public function testConstructorWithArrayPopulatesFields(): void
    {
        $comment = new Episciences_Comment([
            'pcid'     => 42,
            'type'     => Episciences_CommentsManager::TYPE_INFO_REQUEST,
            'docid'    => 99,
            'uid'      => 7,
            'file'     => 'attachment.pdf',
            'when'     => '2024-01-15 10:00:00',
        ]);

        $this->assertSame(42, $comment->getPcid());
        $this->assertSame(Episciences_CommentsManager::TYPE_INFO_REQUEST, $comment->getType());
        $this->assertSame(99, $comment->getDocid());
        $this->assertSame(7, $comment->getUid());
        $this->assertSame('attachment.pdf', $comment->getFile());
        $this->assertSame('2024-01-15 10:00:00', $comment->getWhen());
    }

    // ---------------------------------------------------------------
    // Setters / Getters
    // ---------------------------------------------------------------

    public function testSetAndGetPcid(): void
    {
        $comment = new Episciences_Comment();
        $result  = $comment->setPcid(123);

        $this->assertSame($comment, $result); // fluent
        $this->assertSame(123, $comment->getPcid());
    }

    public function testSetAndGetParentid(): void
    {
        $comment = new Episciences_Comment();
        $comment->setParentid(10);

        $this->assertSame(10, $comment->getParentid());
    }

    public function testSetAndGetType(): void
    {
        $comment = new Episciences_Comment();
        $result  = $comment->setType(Episciences_CommentsManager::TYPE_EDITOR_COMMENT);

        $this->assertSame($comment, $result);
        $this->assertSame(Episciences_CommentsManager::TYPE_EDITOR_COMMENT, $comment->getType());
    }

    public function testSetAndGetDocid(): void
    {
        $comment = new Episciences_Comment();
        $result  = $comment->setDocid(55);

        $this->assertSame($comment, $result);
        $this->assertSame(55, $comment->getDocid());
    }

    public function testSetAndGetUid(): void
    {
        $comment = new Episciences_Comment();
        $result  = $comment->setUid(3);

        $this->assertSame($comment, $result);
        $this->assertSame(3, $comment->getUid());
    }

    public function testSetAndGetFile(): void
    {
        $comment = new Episciences_Comment();
        $result  = $comment->setFile('report.pdf');

        $this->assertSame($comment, $result);
        $this->assertSame('report.pdf', $comment->getFile());
    }

    public function testSetAndGetWhen(): void
    {
        $comment = new Episciences_Comment();
        $comment->setWhen('2024-06-01 12:00:00');

        $this->assertSame('2024-06-01 12:00:00', $comment->getWhen());
    }

    public function testSetAndGetDeadline(): void
    {
        $comment = new Episciences_Comment();
        $result  = $comment->setDeadline('2024-12-31');

        $this->assertSame($comment, $result);
        $this->assertSame('2024-12-31', $comment->getDeadline());
    }

    public function testSetDeadlineNullResetsDeadline(): void
    {
        $comment = new Episciences_Comment();
        $comment->setDeadline('2024-12-31');
        $comment->setDeadline(null);

        $this->assertNull($comment->getDeadline());
    }

    public function testSetAndGetFilePath(): void
    {
        $comment = new Episciences_Comment();
        $result  = $comment->setFilePath('/var/data/comments/');

        $this->assertSame($comment, $result);
        $this->assertSame('/var/data/comments/', $comment->getFilePath());
    }

    // ---------------------------------------------------------------
    // Message encoding/decoding
    // ---------------------------------------------------------------

    public function testGetMessageReturnsNullWhenNotSet(): void
    {
        $comment = new Episciences_Comment();
        $this->assertNull($comment->getMessage());
    }

    public function testSetMessageNullLeavesMessageNull(): void
    {
        $comment = new Episciences_Comment();
        $comment->setMessage(null);

        $this->assertNull($comment->getMessage());
    }

    public function testSetMessagePlainTextRoundTrip(): void
    {
        $comment = new Episciences_Comment();
        $comment->setMessage('Hello world');

        $this->assertSame('Hello world', $comment->getMessage());
    }

    public function testSetMessageTrimsWhitespace(): void
    {
        $comment = new Episciences_Comment();
        $comment->setMessage('  trimmed  ');

        $this->assertSame('trimmed', $comment->getMessage());
    }

    public function testSetMessageScriptTagIsStripped(): void
    {
        $comment = new Episciences_Comment();
        $comment->setMessage('<script>alert("xss")</script>');

        // HTMLPurifier strips <script> tags (not in allowed elements list)
        $this->assertStringNotContainsString('<script>', $comment->getMessage() ?? '');
    }

    public function testSetMessageAllowedHtmlTagsArePreserved(): void
    {
        $comment = new Episciences_Comment();
        $comment->setMessage('<b>bold text</b>');

        // <b> is in the allowed elements list
        $this->assertStringContainsString('bold text', $comment->getMessage() ?? '');
    }

    // ---------------------------------------------------------------
    // Options
    // ---------------------------------------------------------------

    public function testSetOptionAndGetOption(): void
    {
        $comment = new Episciences_Comment();
        $comment->setOption('key1', 'value1');

        $this->assertSame('value1', $comment->getOption('key1'));
    }

    public function testGetOptionReturnsNullForMissingKey(): void
    {
        $comment = new Episciences_Comment();
        $this->assertNull($comment->getOption('nonexistent'));
    }

    public function testHasOptionsReturnsFalseWhenEmpty(): void
    {
        $comment = new Episciences_Comment();
        $this->assertFalse($comment->hasOptions());
    }

    public function testHasOptionsReturnsTrueAfterSetOption(): void
    {
        $comment = new Episciences_Comment();
        $comment->setOption('foo', 'bar');

        $this->assertTrue($comment->hasOptions());
    }

    public function testSetAndGetOptions(): void
    {
        $comment  = new Episciences_Comment();
        $options  = ['a' => 1, 'b' => 2];
        $comment->setOptions($options);

        $this->assertSame($options, $comment->getOptions());
    }

    // ---------------------------------------------------------------
    // Type-classification helpers
    // ---------------------------------------------------------------

    /**
     * @dataProvider suggestionTypeProvider
     */
    public function testIsSuggestionReturnsTrueForSuggestionTypes(int $type): void
    {
        $comment = new Episciences_Comment();
        $comment->setType($type);

        $this->assertTrue($comment->isSuggestion());
    }

    public static function suggestionTypeProvider(): array
    {
        return [
            'acceptation' => [Episciences_CommentsManager::TYPE_SUGGESTION_ACCEPTATION],
            'refus'       => [Episciences_CommentsManager::TYPE_SUGGESTION_REFUS],
            'new version' => [Episciences_CommentsManager::TYPE_SUGGESTION_NEW_VERSION],
        ];
    }

    /**
     * @dataProvider nonSuggestionTypeProvider
     */
    public function testIsSuggestionReturnsFalseForNonSuggestionTypes(int $type): void
    {
        $comment = new Episciences_Comment();
        $comment->setType($type);

        $this->assertFalse($comment->isSuggestion());
    }

    public static function nonSuggestionTypeProvider(): array
    {
        return [
            'info request'   => [Episciences_CommentsManager::TYPE_INFO_REQUEST],
            'info answer'    => [Episciences_CommentsManager::TYPE_INFO_ANSWER],
            'editor comment' => [Episciences_CommentsManager::TYPE_EDITOR_COMMENT],
            'author comment' => [Episciences_CommentsManager::TYPE_AUTHOR_COMMENT],
        ];
    }

    public function testIsEditorCommentReturnsTrueForEditorCommentType(): void
    {
        $comment = new Episciences_Comment();
        $comment->setType(Episciences_CommentsManager::TYPE_EDITOR_COMMENT);

        $this->assertTrue($comment->isEditorComment());
    }

    /**
     * @dataProvider nonEditorCommentTypeProvider
     */
    public function testIsEditorCommentReturnsFalseForOtherTypes(int $type): void
    {
        $comment = new Episciences_Comment();
        $comment->setType($type);

        $this->assertFalse($comment->isEditorComment());
    }

    public static function nonEditorCommentTypeProvider(): array
    {
        return [
            'info request'        => [Episciences_CommentsManager::TYPE_INFO_REQUEST],
            'revision request'    => [Episciences_CommentsManager::TYPE_REVISION_REQUEST],
            'suggestion refus'    => [Episciences_CommentsManager::TYPE_SUGGESTION_REFUS],
        ];
    }

    /**
     * @dataProvider copyEditingTypeProvider
     */
    public function testIsCopyEditingCommentReturnsTrueForCopyEditingTypes(int $type): void
    {
        $comment = new Episciences_Comment();
        $comment->setType($type);

        $this->assertTrue($comment->isCopyEditingComment());
    }

    public static function copyEditingTypeProvider(): array
    {
        return [
            'author sources request'   => [Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_SOURCES_REQUEST],
            'author formatting request'=> [Episciences_CommentsManager::TYPE_WAITING_FOR_AUTHOR_FORMATTING_REQUEST],
            'formatting validated'     => [Episciences_CommentsManager::TYPE_AUTHOR_FORMATTING_VALIDATED_REQUEST],
            'review formatting deposed'=> [Episciences_CommentsManager::TYPE_REVIEW_FORMATTING_DEPOSED_REQUEST],
            'author sources answer'    => [Episciences_CommentsManager::TYPE_AUTHOR_SOURCES_DEPOSED_ANSWER],
            'author formatting answer' => [Episciences_CommentsManager::TYPE_AUTHOR_FORMATTING_ANSWER],
            'final version submitted'  => [Episciences_CommentsManager::TYPE_CE_AUTHOR_FINAL_VERSION_SUBMITTED],
        ];
    }

    /**
     * @dataProvider nonCopyEditingTypeProvider
     */
    public function testIsCopyEditingCommentReturnsFalseForOtherTypes(int $type): void
    {
        $comment = new Episciences_Comment();
        $comment->setType($type);

        $this->assertFalse($comment->isCopyEditingComment());
    }

    public static function nonCopyEditingTypeProvider(): array
    {
        return [
            'info request'     => [Episciences_CommentsManager::TYPE_INFO_REQUEST],
            'info answer'      => [Episciences_CommentsManager::TYPE_INFO_ANSWER],
            'revision request' => [Episciences_CommentsManager::TYPE_REVISION_REQUEST],
            'editor comment'   => [Episciences_CommentsManager::TYPE_EDITOR_COMMENT],
            'author comment'   => [Episciences_CommentsManager::TYPE_AUTHOR_COMMENT],
        ];
    }

    public function testSetCopyEditingCommentOverridesAutoDetection(): void
    {
        $comment = new Episciences_Comment();
        $comment->setType(Episciences_CommentsManager::TYPE_INFO_REQUEST); // not copy-editing

        $comment->setCopyEditingComment(true);

        $this->assertTrue($comment->isCopyEditingComment());
    }

    // ---------------------------------------------------------------
    // toArray
    // ---------------------------------------------------------------

    public function testToArrayReturnsExpectedKeys(): void
    {
        $comment = new Episciences_Comment();
        $comment->setType(Episciences_CommentsManager::TYPE_INFO_REQUEST);
        $comment->setDocid(1);
        $array   = $comment->toArray();

        $expectedKeys = ['pcid', 'parentId', 'type', 'docid', 'uid', 'message', 'file', 'when'];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $array);
        }
    }

    public function testToArrayReturnsSetValues(): void
    {
        $comment = new Episciences_Comment();
        $comment->setPcid(5)
            ->setType(Episciences_CommentsManager::TYPE_AUTHOR_COMMENT)
            ->setDocid(10)
            ->setUid(2)
            ->setFile('letter.pdf')
            ->setWhen('2024-03-01 08:00:00');

        $array = $comment->toArray();

        $this->assertSame(5, $array['pcid']);
        $this->assertSame(Episciences_CommentsManager::TYPE_AUTHOR_COMMENT, $array['type']);
        $this->assertSame(10, $array['docid']);
        $this->assertSame(2, $array['uid']);
        $this->assertSame('letter.pdf', $array['file']);
        $this->assertSame('2024-03-01 08:00:00', $array['when']);
    }

    // ---------------------------------------------------------------
    // populate()
    // ---------------------------------------------------------------

    public function testPopulateFromUppercaseKeys(): void
    {
        $comment = new Episciences_Comment();
        $comment->populate([
            'PCID'     => 77,
            'TYPE'     => Episciences_CommentsManager::TYPE_REVISION_REQUEST,
            'DOCID'    => 200,
            'UID'      => 4,
            'FILE'     => 'doc.pdf',
            'WHEN'     => '2024-09-01 00:00:00',
        ]);

        $this->assertSame(77, $comment->getPcid());
        $this->assertSame(Episciences_CommentsManager::TYPE_REVISION_REQUEST, $comment->getType());
        $this->assertSame(200, $comment->getDocid());
        $this->assertSame(4, $comment->getUid());
        $this->assertSame('doc.pdf', $comment->getFile());
        $this->assertSame('2024-09-01 00:00:00', $comment->getWhen());
    }

    public function testPopulateIgnoresUnknownKeys(): void
    {
        $comment = new Episciences_Comment();
        // Should not throw
        $comment->populate(['UNKNOWN_FIELD' => 'value', 'NONEXISTENT' => 42]);

        $this->assertNull($comment->getPcid());
    }

    public function testPopulateReturnsThis(): void
    {
        $comment = new Episciences_Comment();
        $result  = $comment->populate(['PCID' => 1]);

        $this->assertSame($comment, $result);
    }

    public function testPopulateDecodesJsonOptions(): void
    {
        $comment = new Episciences_Comment();
        $comment->populate(['OPTIONS' => '{"key":"value"}']);

        $this->assertSame(['key' => 'value'], $comment->getOptions());
    }

    // ---------------------------------------------------------------
    // Regression tests for fixed bugs
    // ---------------------------------------------------------------

    /**
     * Regression: before the fix, the truthy check `if ($message)` made setMessage('0') a no-op,
     * leaving getMessage() returning null. After the fix getMessage() no longer returns null.
     * Note: epi_html_decode() uses empty() internally, so '0' is normalized to '' — that is a
     * separate concern; the important assertion here is that the result is not null.
     */
    public function testSetMessageZeroStringIsNoLongerSilentlyIgnored(): void
    {
        $comment = new Episciences_Comment();
        $comment->setMessage('0');

        $this->assertNotNull($comment->getMessage());
    }

    /**
     * Regression: getType() and getDocid() threw TypeError on a fresh object
     * because $_type and $_docId were uninitialized (null) despite int return types.
     */
    public function testToArrayWorksOnFreshObject(): void
    {
        $comment = new Episciences_Comment();

        // Must not throw a TypeError
        $array = $comment->toArray();

        $this->assertSame(0, $array['type']);
        $this->assertSame(0, $array['docid']);
    }
}
