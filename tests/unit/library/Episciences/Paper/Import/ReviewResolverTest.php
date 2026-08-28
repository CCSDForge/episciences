<?php

namespace unit\library\Episciences\Paper\Import;

use Episciences\Paper\Import\ReviewResolver;
use PHPUnit\Framework\TestCase;

/**
 * resolve() itself needs a DB (Episciences_ReviewsManager); only the pure
 * isNumericId() branch logic is unit-tested here.
 */
class ReviewResolverTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function numericIdProvider(): array
    {
        return [
            'plain integer' => ['123', true],
            'zero' => ['0', true],
            'empty string' => ['', false],
            'alphanumeric' => ['12a', false],
            'rvcode' => ['ABC', false],
            'negative number' => ['-1', false],
            'decimal' => ['1.5', false],
            'leading whitespace' => [' 123', false],
        ];
    }

    /** @dataProvider numericIdProvider */
    public function testIsNumericId(string $value, bool $expected): void
    {
        $this->assertSame($expected, ReviewResolver::isNumericId($value));
    }
}
