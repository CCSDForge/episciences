<?php

declare(strict_types=1);

namespace unit\library\Episciences\Upload;

use Episciences\Upload\SafeFileName;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\Upload\SafeFileName
 */
final class SafeFileNameTest extends TestCase
{
    /**
     * @return array<string, array{mixed}>
     */
    public static function refusedProvider(): array
    {
        return [
            'parent directory' => ['../other/file.pdf'],
            'sub directory' => ['sub/file.pdf'],
            'absolute path' => ['/etc/passwd'],
            'backslash' => ['..\\file.pdf'],
            'null byte' => ["file.pdf\0.png"],
            'dot' => ['.'],
            'double dot' => ['..'],
            'empty' => [''],
            'not a string' => [['a.pdf']],
            'null' => [null],
        ];
    }

    /**
     * @dataProvider refusedProvider
     */
    public function testIsBareRefusesPathsAndNonStrings(mixed $name): void
    {
        self::assertFalse(SafeFileName::isBare($name));
    }

    public function testIsBareAcceptsPlainNames(): void
    {
        self::assertTrue(SafeFileName::isBare('cover.jpg'));
        self::assertTrue(SafeFileName::isBare('my file (2).pdf'));
        self::assertTrue(SafeFileName::isBare('.hidden'));
    }
}
