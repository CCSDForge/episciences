<?php

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Paper::getPublicationYear() and
 * Episciences_Paper::getPublicationMonth().
 *
 * These tests document bugs B1 and B2: DateTime::createFromFormat() can return
 * false on malformed dates, and the return value must be checked before calling
 * ->format() on it.
 *
 * @covers Episciences_Paper
 */
final class Episciences_Paper_PublicationDateTest extends TestCase
{
    // -----------------------------------------------------------------------
    // getPublicationYear()
    // -----------------------------------------------------------------------

    public function testGetPublicationYearReturnsCurrentYearWhenNotPublished(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['isPublished']);
        $paper->method('isPublished')->willReturn(false);

        self::assertSame(date('Y'), $paper->getPublicationYear());
    }

    public function testGetPublicationYearReturnsCorrectYearWhenPublished(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['isPublished', 'getPublication_date']);
        $paper->method('isPublished')->willReturn(true);
        $paper->method('getPublication_date')->willReturn('2021-03-15 10:00:00');

        self::assertSame('2021', $paper->getPublicationYear());
    }

    public function testGetPublicationYearAcceptsCustomFormat(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['isPublished', 'getPublication_date']);
        $paper->method('isPublished')->willReturn(true);
        $paper->method('getPublication_date')->willReturn('2019-07-04 00:00:00');

        self::assertSame('19', $paper->getPublicationYear('y'));
    }

    /**
     * Bug B1: before the fix, calling ->format() on false caused a fatal TypeError.
     * After the fix the method falls back to the current year.
     */
    public function testGetPublicationYearDoesNotCrashOnInvalidDate(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['isPublished', 'getPublication_date']);
        $paper->method('isPublished')->willReturn(true);
        $paper->method('getPublication_date')->willReturn('not-a-date');

        // Must not throw; falls back to current year
        $result = $paper->getPublicationYear();
        self::assertSame(date('Y'), $result);
    }

    public function testGetPublicationYearFallsBackOnNullDate(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['isPublished', 'getPublication_date']);
        $paper->method('isPublished')->willReturn(true);
        $paper->method('getPublication_date')->willReturn(null);

        $result = $paper->getPublicationYear();
        self::assertSame(date('Y'), $result);
    }

    // -----------------------------------------------------------------------
    // getPublicationMonth()
    // -----------------------------------------------------------------------

    public function testGetPublicationMonthReturnsCurrentMonthWhenNotPublished(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['isPublished']);
        $paper->method('isPublished')->willReturn(false);

        self::assertSame(date('m'), $paper->getPublicationMonth());
    }

    public function testGetPublicationMonthReturnsCorrectMonthWhenPublished(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['isPublished', 'getPublication_date']);
        $paper->method('isPublished')->willReturn(true);
        $paper->method('getPublication_date')->willReturn('2021-03-15 10:00:00');

        self::assertSame('03', $paper->getPublicationMonth());
    }

    public function testGetPublicationMonthReturnsZeroPaddedMonth(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['isPublished', 'getPublication_date']);
        $paper->method('isPublished')->willReturn(true);
        $paper->method('getPublication_date')->willReturn('2020-01-01 00:00:00');

        self::assertSame('01', $paper->getPublicationMonth());
    }

    /**
     * Bug B2: before the fix, calling ->format() on false caused a fatal TypeError.
     * After the fix the method falls back to the current month.
     */
    public function testGetPublicationMonthDoesNotCrashOnInvalidDate(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['isPublished', 'getPublication_date']);
        $paper->method('isPublished')->willReturn(true);
        $paper->method('getPublication_date')->willReturn('bad-date-string');

        $result = $paper->getPublicationMonth();
        self::assertSame(date('m'), $result);
    }

    public function testGetPublicationMonthFallsBackOnNullDate(): void
    {
        $paper = $this->createPartialMock(Episciences_Paper::class, ['isPublished', 'getPublication_date']);
        $paper->method('isPublished')->willReturn(true);
        $paper->method('getPublication_date')->willReturn(null);

        $result = $paper->getPublicationMonth();
        self::assertSame(date('m'), $result);
    }
}
