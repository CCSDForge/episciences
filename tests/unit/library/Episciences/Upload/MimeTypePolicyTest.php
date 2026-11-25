<?php

declare(strict_types=1);

namespace unit\library\Episciences\Upload;

use Episciences\Upload\MimeTypePolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Episciences\Upload\MimeTypePolicy
 */
final class MimeTypePolicyTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/mime_policy_' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testAcceptsOnlyTheContentTypesOfTheExtension(): void
    {
        $policy = new MimeTypePolicy(['png' => ['image/png'], 'pdf' => ['application/pdf']]);

        self::assertTrue($policy->accepts('png', 'image/png'));
        self::assertFalse($policy->accepts('png', 'application/pdf'), 'a type accepted for another extension is not enough');
        self::assertFalse($policy->accepts('exe', 'image/png'));
    }

    public function testExtensionsAreCaseInsensitive(): void
    {
        $policy = new MimeTypePolicy(['PDF' => ['application/pdf']]);

        self::assertTrue($policy->accepts('pdf', 'application/pdf'));
        self::assertTrue($policy->isExtensionAllowed('PdF'));
        self::assertSame(['pdf'], $policy->extensions());
    }

    public function testAnyExtensionPolicy(): void
    {
        $policy = MimeTypePolicy::forAnyExtension(['application/pdf']);

        self::assertTrue($policy->accepts('', 'application/pdf'));
        self::assertTrue($policy->accepts('whatever', 'application/pdf'));
        self::assertFalse($policy->accepts('pdf', 'image/png'));
        self::assertSame([], $policy->extensions());
        self::assertSame(['application/pdf'], $policy->mimeTypes());
    }

    public function testMimeTypesListsEachTypeOnce(): void
    {
        $policy = new MimeTypePolicy(['doc' => ['application/msword', 'application/CDFV2'], 'xls' => ['application/CDFV2']]);

        self::assertSame(['application/msword', 'application/CDFV2'], $policy->mimeTypes());
    }

    public function testWithoutRemovesExtensionsAndLeavesTheOriginalUntouched(): void
    {
        $policy = new MimeTypePolicy(['png' => ['image/png'], 'html' => ['text/html'], 'pdf' => ['application/pdf']]);

        $reduced = $policy->without('HTML', 'unknown');

        self::assertFalse($reduced->isExtensionAllowed('html'));
        self::assertTrue($reduced->accepts('png', 'image/png'));
        self::assertTrue($policy->isExtensionAllowed('html'));
    }

    /**
     * @dataProvider providerFileNames
     */
    public function testExtensionOf(string $fileName, string $expected): void
    {
        self::assertSame($expected, MimeTypePolicy::extensionOf($fileName));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerFileNames(): array
    {
        return [
            'simple' => ['article.pdf', 'pdf'],
            'uppercase' => ['ARTICLE.PDF', 'pdf'],
            'several dots' => ['archive.tar.gz', 'gz'],
            'no extension' => ['README', ''],
            'hidden file' => ['.htaccess', 'htaccess'],
            'empty' => ['', ''],
        ];
    }

    /**
     * @dataProvider providerInvalidMaps
     * @param array<mixed> $map
     */
    public function testRejectsAnInvalidMap(array $map): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MimeTypePolicy($map);
    }

    /**
     * @return array<string, array{array<mixed>}>
     */
    public static function providerInvalidMaps(): array
    {
        return [
            'no content type' => [['pdf' => []]],
            'not a list' => [['pdf' => 'application/pdf']],
            'empty content type' => [['pdf' => ['']]],
            'content type not a string' => [['pdf' => [1]]],
            'empty extension' => [['' => ['application/pdf']]],
        ];
    }

    public function testFromJsonFile(): void
    {
        $path = $this->directory . '/config.json';
        file_put_contents($path, json_encode(['allowed_mimes_by_extension' => ['pdf' => ['application/pdf']]], JSON_THROW_ON_ERROR));

        self::assertTrue(MimeTypePolicy::fromJsonFile($path)->accepts('pdf', 'application/pdf'));
    }

    /**
     * @dataProvider providerInvalidFiles
     */
    public function testFromJsonFileRejectsAnUnusableFile(?string $contents): void
    {
        $path = $this->directory . '/config.json';

        if ($contents !== null) {
            file_put_contents($path, $contents);
        }

        $this->expectException(InvalidArgumentException::class);

        MimeTypePolicy::fromJsonFile($path);
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function providerInvalidFiles(): array
    {
        return [
            'missing file' => [null],
            'invalid json' => ['{not json'],
            'entry missing' => ['{"allowed_extensions": ["pdf"]}'],
            'entry is not a map' => ['{"allowed_mimes_by_extension": "pdf"}'],
        ];
    }
}
