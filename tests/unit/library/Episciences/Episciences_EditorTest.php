<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Editor.
 *
 * Focuses on pure-logic: constants, tag/status/when setters-getters,
 * assignments collection, toArray shape.
 * DB-dependent methods (loadAssignedPapers, loadAssignments, etc.)
 * are excluded.
 *
 * @covers Episciences_Editor
 */
class Episciences_EditorTest extends TestCase
{
    private Episciences_Editor $editor;

    protected function setUp(): void
    {
        $this->editor = new Episciences_Editor();
    }

    // =========================================================================
    // Tag constants
    // =========================================================================

    public function testTagConstants(): void
    {
        self::assertSame('Section editor', Episciences_Editor::TAG_SECTION_EDITOR);
        self::assertSame('Volume editor', Episciences_Editor::TAG_VOLUME_EDITOR);
        self::assertSame('suggested editor', Episciences_Editor::TAG_SUGGESTED_EDITOR);
        self::assertSame('Chief editor', Episciences_Editor::TAG_CHIEF_EDITOR);
    }

    // =========================================================================
    // tag setter/getter
    // =========================================================================

    public function testDefaultTagIsEmptyString(): void
    {
        self::assertSame('', $this->editor->getTag());
    }

    public function testSetAndGetTag(): void
    {
        $this->editor->setTag(Episciences_Editor::TAG_SECTION_EDITOR);
        self::assertSame(Episciences_Editor::TAG_SECTION_EDITOR, $this->editor->getTag());
    }

    public function testSetTagReturnsFluent(): void
    {
        $result = $this->editor->setTag('Chief editor');
        self::assertInstanceOf(Episciences_Editor::class, $result);
    }

    // =========================================================================
    // status setter/getter
    // =========================================================================

    public function testSetAndGetStatus(): void
    {
        $this->editor->setStatus('active');
        self::assertSame('active', $this->editor->getStatus());
    }

    // =========================================================================
    // when setter/getter
    // =========================================================================

    public function testSetAndGetWhen(): void
    {
        $this->editor->setWhen('2024-01-01 00:00:00');
        self::assertSame('2024-01-01 00:00:00', $this->editor->getWhen());
    }

    // =========================================================================
    // setAssignedPapers / toArray
    // =========================================================================

    public function testSetAssignedPapersAndToArray(): void
    {
        $this->editor->setAssignedPapers([]);
        $array = $this->editor->toArray();

        self::assertIsArray($array);
        self::assertArrayHasKey('status', $array);
        self::assertArrayHasKey('when', $array);
        self::assertArrayHasKey('tag', $array);
    }

    public function testToArrayContainsTagValue(): void
    {
        $this->editor->setTag(Episciences_Editor::TAG_CHIEF_EDITOR);
        $array = $this->editor->toArray();

        self::assertSame(Episciences_Editor::TAG_CHIEF_EDITOR, $array['tag']);
    }

    // =========================================================================
    // assignments collection
    // =========================================================================

    public function testGetAssignmentsReturnsEmptyArrayByDefault(): void
    {
        $result = $this->editor->getAssignments();
        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    public function testGetAssignmentsByTypeReturnsEmptyArrayForUnknownType(): void
    {
        $result = $this->editor->getAssignments(Episciences_User_Assignment::ITEM_PAPER);
        self::assertSame([], $result);
    }

    public function testSetAssignmentsAndGet(): void
    {
        $this->editor->setAssignments(['mock'], Episciences_User_Assignment::ITEM_PAPER);
        $result = $this->editor->getAssignments(Episciences_User_Assignment::ITEM_PAPER);

        self::assertSame(['mock'], $result);
    }

    public function testHasAssignmentsReturnsFalseWhenEmpty(): void
    {
        self::assertFalse($this->editor->hasAssignments(Episciences_User_Assignment::ITEM_PAPER));
    }

    public function testHasAssignmentsReturnsTrueWhenSet(): void
    {
        $this->editor->setAssignments(['mock'], Episciences_User_Assignment::ITEM_PAPER);
        self::assertTrue($this->editor->hasAssignments(Episciences_User_Assignment::ITEM_PAPER));
    }

    // =========================================================================
    // setAssignedSections
    // =========================================================================

    public function testSetAndGetAssignedSections(): void
    {
        $sections = ['sec1', 'sec2'];
        $this->editor->setAssignedSections($sections);
        self::assertSame($sections, $this->editor->getAssignedSections());
    }

    // =========================================================================
    // Constructor with options
    // =========================================================================

    public function testConstructorWithOptions(): void
    {
        $editor = new Episciences_Editor([
            'SCREEN_NAME' => 'Alice',
            'STATUS'      => 'active',
        ]);

        self::assertSame('Alice', $editor->getScreenName());
        self::assertSame('active', $editor->getStatus());
    }
}
