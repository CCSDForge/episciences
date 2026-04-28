<?php

namespace unit\library\Episciences\Reviewer;

use Episciences_Rating_Report;
use Episciences_Reviewer_Reviewing;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Reviewer_Reviewing.
 *
 * Focuses on pure-logic: constants, status labels/colors, setters-getters,
 * invitation/assignment/rating holders, static helpers.
 * Methods that call the DB or filesystem are excluded.
 *
 * @covers Episciences_Reviewer_Reviewing
 */
class Episciences_Reviewer_ReviewingTest extends TestCase
{
    private Episciences_Reviewer_Reviewing $reviewing;

    protected function setUp(): void
    {
        $this->reviewing = new Episciences_Reviewer_Reviewing();
    }

    // =========================================================================
    // Status constants
    // =========================================================================

    public function testStatusConstants(): void
    {
        self::assertSame(0, Episciences_Reviewer_Reviewing::STATUS_PENDING);
        self::assertSame(1, Episciences_Reviewer_Reviewing::STATUS_WIP);
        self::assertSame(2, Episciences_Reviewer_Reviewing::STATUS_COMPLETE);
        self::assertSame(3, Episciences_Reviewer_Reviewing::STATUS_UNANSWERED);
        self::assertSame(4, Episciences_Reviewer_Reviewing::STATUS_OBSOLETE);
        self::assertSame(5, Episciences_Reviewer_Reviewing::STATUS_DECLINED);
        self::assertSame(6, Episciences_Reviewer_Reviewing::STATUS_NOT_NEED_REVIEWING);
    }

    // =========================================================================
    // getStatusList()
    // =========================================================================

    public function testGetStatusListReturnsAllStatuses(): void
    {
        $list = Episciences_Reviewer_Reviewing::getStatusList();

        self::assertIsArray($list);
        self::assertCount(7, $list);
        self::assertArrayHasKey(Episciences_Reviewer_Reviewing::STATUS_PENDING, $list);
        self::assertArrayHasKey(Episciences_Reviewer_Reviewing::STATUS_WIP, $list);
        self::assertArrayHasKey(Episciences_Reviewer_Reviewing::STATUS_COMPLETE, $list);
        self::assertArrayHasKey(Episciences_Reviewer_Reviewing::STATUS_UNANSWERED, $list);
        self::assertArrayHasKey(Episciences_Reviewer_Reviewing::STATUS_OBSOLETE, $list);
        self::assertArrayHasKey(Episciences_Reviewer_Reviewing::STATUS_DECLINED, $list);
        self::assertArrayHasKey(Episciences_Reviewer_Reviewing::STATUS_NOT_NEED_REVIEWING, $list);
    }

    public function testGetStatusListLabelsAreStrings(): void
    {
        foreach (Episciences_Reviewer_Reviewing::getStatusList() as $label) {
            self::assertIsString($label);
            self::assertNotEmpty($label);
        }
    }

    // =========================================================================
    // getStatusLabel()
    // =========================================================================

    public function testGetStatusLabelForKnownStatus(): void
    {
        $label = Episciences_Reviewer_Reviewing::getStatusLabel(Episciences_Reviewer_Reviewing::STATUS_COMPLETE);
        self::assertIsString($label);
        self::assertStringContainsString('relecture', $label);
    }

    public function testGetStatusLabelForNullReturnsUnexpectedString(): void
    {
        $label = @Episciences_Reviewer_Reviewing::getStatusLabel(null);
        self::assertIsString($label);
        self::assertStringContainsString('Unexpected', $label);
    }

    public function testGetStatusLabelForUnknownStatusReturnsStatusAsIs(): void
    {
        // Unknown status: getStatusLabel() returns the original value unchanged
        $label = Episciences_Reviewer_Reviewing::getStatusLabel(999);
        self::assertSame(999, $label);
    }

    // =========================================================================
    // setStatus / getStatus (with explicit value, no loadStatus needed)
    // =========================================================================

    public function testSetStatusAndGetStatus(): void
    {
        $this->reviewing->setStatus(Episciences_Reviewer_Reviewing::STATUS_WIP);
        self::assertSame(Episciences_Reviewer_Reviewing::STATUS_WIP, $this->reviewing->getStatus());
    }

    public function testSetStatusCastsToInt(): void
    {
        $this->reviewing->setStatus('2');
        self::assertSame(2, $this->reviewing->getStatus());
    }

    // =========================================================================
    // invitation holder
    // =========================================================================

    public function testSetAndGetInvitation(): void
    {
        $inv = new \stdClass();
        $this->reviewing->setInvitation($inv);
        self::assertSame($inv, $this->reviewing->getInvitation());
    }

    public function testDefaultInvitationIsNull(): void
    {
        self::assertNull($this->reviewing->getInvitation());
    }

    // =========================================================================
    // rating holder
    // =========================================================================

    public function testSetAndGetRating(): void
    {
        $report = new Episciences_Rating_Report();
        $this->reviewing->setRating($report);
        self::assertSame($report, $this->reviewing->getRating());
    }

    public function testHasRatingReturnsFalsyByDefault(): void
    {
        // hasRating() returns getRating() directly — null when unset
        self::assertEmpty($this->reviewing->hasRating());
    }

    public function testHasRatingReturnsTruthyWhenSet(): void
    {
        $this->reviewing->setRating(new Episciences_Rating_Report());
        self::assertNotEmpty($this->reviewing->hasRating());
    }

    // =========================================================================
    // assignment holder
    // =========================================================================

    public function testDefaultAssignmentIsNull(): void
    {
        self::assertNull($this->reviewing->getAssignment());
    }

    public function testHasAssignmentReturnsFalsyByDefault(): void
    {
        // hasAssignment() returns getAssignment() directly — null when unset
        self::assertEmpty($this->reviewing->hasAssignment());
    }

    // =========================================================================
    // paper holder
    // =========================================================================

    public function testSetPaperWithNull(): void
    {
        $this->reviewing->setPaper(null);
        // getPaper() calls loadPaper if no assignment and paper is null — safe to check _paper via reflection
        $prop = new \ReflectionProperty(Episciences_Reviewer_Reviewing::class, '_paper');
        $prop->setAccessible(true);
        self::assertNull($prop->getValue($this->reviewing));
    }

    // =========================================================================
    // Color codes (static array)
    // =========================================================================

    public function testStatusColorsContainsExpectedKeys(): void
    {
        $colors = Episciences_Reviewer_Reviewing::$_statusColors;
        self::assertArrayHasKey(Episciences_Reviewer_Reviewing::STATUS_PENDING, $colors);
        self::assertArrayHasKey(Episciences_Reviewer_Reviewing::STATUS_WIP, $colors);
        self::assertArrayHasKey(Episciences_Reviewer_Reviewing::STATUS_COMPLETE, $colors);
    }

    public function testStatusColorsAreStrings(): void
    {
        foreach (Episciences_Reviewer_Reviewing::$_statusColors as $color) {
            self::assertIsString($color);
        }
    }
}
