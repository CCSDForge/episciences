<?php

namespace unit\library\Episciences\paper;

use Episciences_Paper;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Paper::findExistingDocId() and alreadyExists().
 *
 * Both methods require a live database for their full flow.
 * Here we verify the delegation contract between alreadyExists() and
 * findExistingDocId() using a partial mock.
 *
 * @covers Episciences_Paper
 */
final class Episciences_Paper_AlreadyExistsTest extends TestCase
{
    // -----------------------------------------------------------------------
    // alreadyExists() — return type contract
    // -----------------------------------------------------------------------

    public function testAlreadyExistsReturnsTrueWhenDocIdFound(): void
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
    // findExistingDocId() — integration tests require a live DB.
    // The return type contract (int, 0 when not found, positive when found)
    // is enforced by the PHP type declaration and covered by integration tests.
    // -----------------------------------------------------------------------
}