<?php

declare(strict_types=1);

namespace unit\library\Episciences\Trait;

use Episciences\Trait\UrlBuilder;
use PHPUnit\Framework\TestCase;

/**
 * UrlBuilder::prefixPath(): a path built from the site root, served under the journal prefix
 */
final class UrlBuilderPrefixPathTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function paths(): iterable
    {
        yield 'journal, path from the root' => ['/15/pdf', '/epijinfo/', '/epijinfo/15/pdf'];
        yield 'journal, relative path' => ['docfiles/ce/12/report.pdf', '/epijinfo/', '/epijinfo/docfiles/ce/12/report.pdf'];
        yield 'journal prefix without trailing slash' => ['/15', '/epijinfo', '/epijinfo/15'];
        yield 'portal, path from the root' => ['/15/pdf', '/', '/15/pdf'];
        yield 'portal, relative path' => ['15', '/', '/15'];
        yield 'no prefix' => ['/15', '', '/15'];
    }

    /**
     * @dataProvider paths
     */
    public function testPathIsServedUnderThePrefix(string $path, string $prefixUrl, string $expected): void
    {
        self::assertSame($expected, UrlBuilderUser::prefixPath($path, $prefixUrl));
    }

    public function testPrefixUrlIsTheDefaultPrefix(): void
    {
        self::assertSame(rtrim(PREFIX_URL, '/') . '/15/pdf', UrlBuilderUser::prefixPath('/15/pdf'));
    }
}

final class UrlBuilderUser
{
    use UrlBuilder;
}
