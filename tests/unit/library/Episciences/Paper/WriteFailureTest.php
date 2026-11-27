<?php

declare(strict_types=1);

namespace unit\library\Episciences\Paper;

use Episciences\Paper\WriteFailure;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zend_Db_Exception;
use Zend_Db_Statement_Exception;

/**
 * @covers \Episciences\Paper\WriteFailure
 */
final class WriteFailureTest extends TestCase
{
    public function testResultOfTheWriteIsReturned(): void
    {
        $errors = $this->collectErrors(static fn() => WriteFailure::reportAsWarning('Foo::insert', static fn(): int => 3), $result);

        self::assertSame(3, $result);
        self::assertSame([], $errors);
    }

    public function testFailureIsAWarningWithTheMethod(): void
    {
        $errors = $this->collectErrors(
            static fn() => WriteFailure::reportAsWarning('Foo::insert', static fn(): int => throw new RuntimeException('query failed')),
            $result
        );

        self::assertSame(0, $result);
        self::assertSame([[E_USER_WARNING, 'Foo::insert: query failed']], $errors);
    }

    public function testValueReturnedOnFailure(): void
    {
        $this->collectErrors(
            static fn() => WriteFailure::reportAsWarning('Foo::insert', static fn(): int => throw new RuntimeException('x'), null),
            $result
        );

        self::assertNull($result);
    }

    public function testFailureOfTheCaughtClassIsAWarning(): void
    {
        $errors = $this->collectErrors(
            static fn() => WriteFailure::reportAsWarning(
                'Foo::insert',
                static fn(): int => throw new Zend_Db_Statement_Exception('query failed'),
                0,
                Zend_Db_Exception::class
            ),
            $result
        );

        self::assertSame(0, $result);
        self::assertCount(1, $errors);
    }

    public function testFailureOfAnotherClassIsThrown(): void
    {
        $this->expectException(LogicException::class);

        WriteFailure::reportAsWarning(
            'Foo::insert',
            static fn(): int => throw new LogicException('programming error'),
            0,
            Zend_Db_Exception::class
        );
    }

    /**
     * @return list<array{int, string}>
     */
    private function collectErrors(callable $run, mixed &$result): array
    {
        $errors = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$errors): bool {
            $errors[] = [$errno, $errstr];
            return true;
        });

        try {
            $result = $run();
        } finally {
            restore_error_handler();
        }

        return $errors;
    }
}
