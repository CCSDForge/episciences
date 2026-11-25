<?php

namespace unit\scripts\Messenger;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

require_once __DIR__ . '/../../../../scripts/Messenger/ParsesMemoryLimit.php';

/**
 * Unit tests for the ParsesMemoryLimit trait, extracted from the former
 * SolrWorkerCommand so it can be shared by episciences:worker regardless of
 * transport.
 */
class ParsesMemoryLimitTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        $this->subject = new class {
            use \ParsesMemoryLimit;
        };
    }

    /** @dataProvider memoryLimitProvider */
    public function testParseMemoryLimit(string $input, int $expectedBytes): void
    {
        $method = new ReflectionMethod($this->subject, 'parseMemoryLimit');
        $method->setAccessible(true);

        self::assertSame($expectedBytes, $method->invoke($this->subject, $input));
    }

    /** @return array<string, array{0: string, 1: int}> */
    public static function memoryLimitProvider(): array
    {
        return [
            'gigabytes' => ['1G', 1024 * 1024 * 1024],
            'megabytes' => ['512M', 512 * 1024 * 1024],
            'kilobytes' => ['256K', 256 * 1024],
            'plain bytes' => ['1000', 1000],
            'megabytes with trailing b' => ['512MB', 512 * 1024 * 1024],
            'gigabytes with trailing b' => ['1GB', 1024 * 1024 * 1024],
            'kilobytes with trailing b' => ['256KB', 256 * 1024],
            'lowercase unit with trailing b' => ['512mb', 512 * 1024 * 1024],
        ];
    }

    public function testParseMemoryLimitRejectsGarbageInput(): void
    {
        $method = new ReflectionMethod($this->subject, 'parseMemoryLimit');
        $method->setAccessible(true);

        $this->expectException(InvalidArgumentException::class);
        $method->invoke($this->subject, 'not-a-size');
    }
}
