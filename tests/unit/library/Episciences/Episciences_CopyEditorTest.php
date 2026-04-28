<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_CopyEditor.
 *
 * Pure-logic tests: when/status setters-getters, assignedSections,
 * assignedPapers. DB-dependent methods excluded.
 *
 * @covers Episciences_CopyEditor
 */
class Episciences_CopyEditorTest extends TestCase
{
    private Episciences_CopyEditor $copyEditor;

    protected function setUp(): void
    {
        $this->copyEditor = new Episciences_CopyEditor();
    }

    // =========================================================================
    // when setter/getter
    // =========================================================================

    public function testSetAndGetWhen(): void
    {
        $this->copyEditor->setWhen('2024-06-15 10:30:00');
        self::assertSame('2024-06-15 10:30:00', $this->copyEditor->getWhen());
    }

    public function testSetWhenReturnsFluent(): void
    {
        $result = $this->copyEditor->setWhen('2024-01-01');
        self::assertInstanceOf(Episciences_CopyEditor::class, $result);
    }

    public function testDefaultWhenIsNull(): void
    {
        self::assertNull($this->copyEditor->getWhen());
    }

    // =========================================================================
    // status setter/getter
    // =========================================================================

    public function testSetAndGetStatus(): void
    {
        $this->copyEditor->setStatus('active');
        self::assertSame('active', $this->copyEditor->getStatus());
    }

    public function testSetStatusReturnsFluent(): void
    {
        $result = $this->copyEditor->setStatus('inactive');
        self::assertInstanceOf(Episciences_CopyEditor::class, $result);
    }

    public function testDefaultStatusIsNull(): void
    {
        self::assertNull($this->copyEditor->getStatus());
    }

    // =========================================================================
    // assignedSections setter/getter
    // =========================================================================

    public function testSetAndGetAssignedSections(): void
    {
        $sections = ['s1', 's2'];
        $this->copyEditor->setAssignedSections($sections);
        self::assertSame($sections, $this->copyEditor->getAssignedSections());
    }

    public function testDefaultAssignedSectionsPropertyIsEmptyArray(): void
    {
        // getAssignedSections() triggers loadAssignedSections() (DB) when the internal
        // property is empty — bypass via reflection to verify the initial state.
        $prop = new ReflectionProperty(Episciences_CopyEditor::class, '_assignedSections');
        $prop->setAccessible(true);
        self::assertSame([], $prop->getValue($this->copyEditor));
    }

    // =========================================================================
    // assignedPapers setter
    // =========================================================================

    public function testSetAssignedPapersAcceptsArray(): void
    {
        $papers = ['p1', 'p2'];
        $this->copyEditor->setAssignedPapers($papers);
        // getAssignedPapers() triggers loadAssignedPapers() if empty — bypass via reflection
        $prop = new ReflectionProperty(Episciences_CopyEditor::class, '_assignedPapers');
        $prop->setAccessible(true);
        self::assertSame($papers, $prop->getValue($this->copyEditor));
    }

    // =========================================================================
    // Constructor with options
    // =========================================================================

    public function testConstructorWithOptions(): void
    {
        $ce = new Episciences_CopyEditor([
            'SCREEN_NAME' => 'Bob',
            'STATUS'      => 'pending',
        ]);

        self::assertSame('Bob', $ce->getScreenName());
        self::assertSame('pending', $ce->getStatus());
    }

    public function testConstructorWithNoOptionsDoesNotThrow(): void
    {
        $ce = new Episciences_CopyEditor();
        self::assertInstanceOf(Episciences_CopyEditor::class, $ce);
    }
}
