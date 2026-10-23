<?php

namespace unit\library\Episciences;

use Episciences_Paper;
use Episciences_PapersManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_PapersManager::sortBy().
 *
 * @covers Episciences_PapersManager
 */
final class Episciences_PapersManager_SortByTest extends TestCase
{
    private function paperWithStatus(int $status): Episciences_Paper
    {
        $paper = $this->getMockBuilder(Episciences_Paper::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStatus'])
            ->getMock();
        $paper->method('getStatus')->willReturn($status);

        return $paper;
    }

    public function testEmptyListReturnsFalse(): void
    {
        self::assertFalse(Episciences_PapersManager::sortBy([], 'STATUS'));
    }

    public function testSortByStatusFollowsTheWorkflowOrderWithoutDeprecation(): void
    {
        $list = [
            10 => $this->paperWithStatus(Episciences_Paper::STATUS_REVIEWED),
            11 => $this->paperWithStatus(Episciences_Paper::STATUS_SUBMITTED),
            12 => $this->paperWithStatus(Episciences_Paper::STATUS_BEING_REVIEWED),
            13 => $this->paperWithStatus(Episciences_Paper::STATUS_SUBMITTED),
        ];

        $deprecations = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$deprecations): bool {
            $deprecations[] = $errstr;
            return true;
        }, E_DEPRECATED);

        try {
            $sorted = Episciences_PapersManager::sortBy($list, 'STATUS');
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $deprecations);
        self::assertSame(
            [Episciences_Paper::STATUS_SUBMITTED, Episciences_Paper::STATUS_BEING_REVIEWED, Episciences_Paper::STATUS_REVIEWED],
            array_keys($sorted)
        );
        self::assertSame([11, 13], array_keys($sorted[Episciences_Paper::STATUS_SUBMITTED]));
    }
}
