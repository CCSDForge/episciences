<?php

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Paper::findExistingDocId() and alreadyExists().
 *
 * Both methods require a live database for their full flow.
 * Here we verify the delegation contract between alreadyExists() and
 * findExistingDocId() using a partial mock, and document the expected
 * return types.
 *
 * @covers Episciences_Paper
 */
final class Episciences_Paper_AlreadyExistsTest extends TestCase
{
    // -----------------------------------------------------------------------
    // alreadyExists() — return type contract
    // -----------------------------------------------------------------------

    public function testAlreadyExistsReturnsBoolWhenDocIdFound(): void
    {
        $paper = $this->getMockBuilder(Episciences_Paper::class)
            ->onlyMethods(['findExistingDocId'])
            ->getMock();
        $paper->method('findExistingDocId')->willReturn(42);

        self::assertTrue($paper->alreadyExists());
    }

    public function testAlreadyExistsReturnsFalseWhenNoDocIdFound(): void
    {
        $paper = $this->getMockBuilder(Episciences_Paper::class)
            ->onlyMethods(['findExistingDocId'])
            ->getMock();
        $paper->method('findExistingDocId')->willReturn(0);

        self::assertFalse($paper->alreadyExists());
    }

    public function testAlreadyExistsReturnsBool(): void
    {
        $paper = $this->getMockBuilder(Episciences_Paper::class)
            ->onlyMethods(['findExistingDocId'])
            ->getMock();
        $paper->method('findExistingDocId')->willReturn(0);

        self::assertIsBool($paper->alreadyExists());
    }

    // -----------------------------------------------------------------------
    // alreadyExists() — delegates $strict to findExistingDocId()
    // -----------------------------------------------------------------------

    public function testAlreadyExistsPassesTrueStrictByDefault(): void
    {
        $paper = $this->getMockBuilder(Episciences_Paper::class)
            ->onlyMethods(['findExistingDocId'])
            ->getMock();
        $paper->expects(self::once())
            ->method('findExistingDocId')
            ->with(true)
            ->willReturn(0);

        $paper->alreadyExists();
    }

    public function testAlreadyExistsPassesFalseStrictWhenGiven(): void
    {
        $paper = $this->getMockBuilder(Episciences_Paper::class)
            ->onlyMethods(['findExistingDocId'])
            ->getMock();
        $paper->expects(self::once())
            ->method('findExistingDocId')
            ->with(false)
            ->willReturn(5);

        self::assertTrue($paper->alreadyExists(false));
    }

    // -----------------------------------------------------------------------
    // findExistingDocId() — return type contract (no DB)
    // -----------------------------------------------------------------------

    /**
     * findExistingDocId() must return an int.
     * Verified via a mock to avoid requiring a database in unit tests.
     */
    public function testFindExistingDocIdReturnsInt(): void
    {
        $paper = $this->getMockBuilder(Episciences_Paper::class)
            ->onlyMethods(['findExistingDocId'])
            ->getMock();
        $paper->method('findExistingDocId')->willReturn(7);

        self::assertIsInt($paper->findExistingDocId());
    }

    public function testFindExistingDocIdReturnsZeroWhenNotFound(): void
    {
        $paper = $this->getMockBuilder(Episciences_Paper::class)
            ->onlyMethods(['findExistingDocId'])
            ->getMock();
        $paper->method('findExistingDocId')->willReturn(0);

        self::assertSame(0, $paper->findExistingDocId());
    }

    public function testFindExistingDocIdReturnsPositiveIntWhenFound(): void
    {
        $paper = $this->getMockBuilder(Episciences_Paper::class)
            ->onlyMethods(['findExistingDocId'])
            ->getMock();
        $paper->method('findExistingDocId')->willReturn(123);

        self::assertGreaterThan(0, $paper->findExistingDocId());
    }
}