<?php

namespace unit\library\Episciences;

use Episciences_Tools;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;


class Episciences_ToolsTest extends TestCase
{
    public static function testIsInUppercase(){

        self::assertEquals(true, Episciences_Tools::isInUppercase('SCREEN_NAME'));
        self::assertEquals(false, Episciences_Tools::isInUppercase('SCREEN_name'));

    }

    public function testCheckValueType()
    {
        // Test HAL identifier
        self::assertEquals('hal', Episciences_Tools::checkValueType('hal_12345678'));
        self::assertEquals('hal', Episciences_Tools::checkValueType('hal-12345678v1'));
        
        // Test DOI
        self::assertEquals('doi', Episciences_Tools::checkValueType('10.1000/123456'));
        self::assertEquals('doi', Episciences_Tools::checkValueType('10.1038/nature.2022.12345'));
        
        // Test Software Heritage ID  
        self::assertEquals('software', Episciences_Tools::checkValueType('swh:1:dir:0123456789abcdef0123456789abcdef01234567'));
        self::assertEquals('software', Episciences_Tools::checkValueType('swh:1:cnt:0123456789abcdef0123456789abcdef01234567;origin=https://example.com'));
        
        // Test URL
        self::assertEquals('url', Episciences_Tools::checkValueType('https://example.com'));
        self::assertEquals('url', Episciences_Tools::checkValueType('http://example.org/page'));
        
        // Test Handle
        self::assertEquals('handle', Episciences_Tools::checkValueType('123456789/12345'));
        self::assertEquals('handle', Episciences_Tools::checkValueType('hdl.handle.net/123456789/12345'));
        
        // Test ArXiv
        self::assertEquals('arxiv', Episciences_Tools::checkValueType('2023.12345'));
        self::assertEquals('arxiv', Episciences_Tools::checkValueType('math.AG/0123456'));
        
        // Test unrecognized value
        self::assertFalse(Episciences_Tools::checkValueType('invalid-value'));
        self::assertFalse(Episciences_Tools::checkValueType(''));
        self::assertFalse(Episciences_Tools::checkValueType('just some text'));
    }

    // -------------------------------------------------------------------------
    // Security S5 — XSS in getTmpFilesLinks() (source inspection)
    // -------------------------------------------------------------------------

    /**
     * Regression S5: $fileName from JSON payload injected into href and link text
     * without encoding — allows XSS and path traversal.
     * The fix uses urlencode() for the href and htmlspecialchars() for the display text.
     */
    public function testBuildHtmlTmpDocUrlsEscapesFileName(): void
    {
        $method = new ReflectionMethod(Episciences_Tools::class, 'buildHtmlTmpDocUrls');
        $lines = file($method->getFileName());
        $source = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        self::assertStringContainsString(
            'urlencode($fileName)',
            $source,
            'Security S5: $fileName must be urlencode()d in the href attribute'
        );
        self::assertMatchesRegularExpression(
            '/htmlspecialchars\s*\(\s*\$href/',
            $source,
            'Security S5: href must be escaped with htmlspecialchars() before HTML injection'
        );
        self::assertMatchesRegularExpression(
            '/htmlspecialchars\s*\(\s*\$fileName/',
            $source,
            'Security S5: $fileName must be escaped with htmlspecialchars() in the link text'
        );
    }


    private function makeAttachmentSandbox(): array
    {
        $root = sys_get_temp_dir() . '/attach_' . bin2hex(random_bytes(4));
        mkdir($root . '/base', 0755, true);
        file_put_contents($root . '/base/report 1.pdf', 'ok');
        file_put_contents($root . '/base/é.txt', 'ok');
        file_put_contents($root . '/secret.txt', 'secret');
        mkdir($root . '/base/sub');
        file_put_contents($root . '/base/sub/x.txt', 'x');

        return [$root, $root . '/base/'];
    }

    private function removeSandbox(string $root): void
    {
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $f) {
            $f->isLink() || $f->isFile() ? unlink($f->getPathname()) : rmdir($f->getPathname());
        }
        rmdir($root);
    }

    public function testResolveAttachmentPathAcceptsFlatExistingFiles(): void
    {
        [$root, $base] = $this->makeAttachmentSandbox();
        try {
            self::assertSame($base . 'report 1.pdf', Episciences_Tools::resolveAttachmentPath($base, 'report 1.pdf'));
            self::assertSame($base . 'é.txt', Episciences_Tools::resolveAttachmentPath($base, 'é.txt'));
            // base directory without trailing separator
            self::assertSame($base . 'report 1.pdf', Episciences_Tools::resolveAttachmentPath(rtrim($base, '/'), 'report 1.pdf'));
        } finally {
            $this->removeSandbox($root);
        }
    }

    public function testResolveAttachmentPathRejectsTraversalAndInvalidNames(): void
    {
        [$root, $base] = $this->makeAttachmentSandbox();
        try {
            $invalid = [
                '../secret.txt', '..', '.', '', 'sub/x.txt', '/etc/passwd', '..\\secret.txt',
                "report 1.pdf\0.txt", 'nonexistent.txt', 'sub', ['../secret.txt'], null, 12,
            ];
            foreach ($invalid as $name) {
                self::assertNull(
                    Episciences_Tools::resolveAttachmentPath($base, $name),
                    'Must reject: ' . var_export($name, true)
                );
            }
        } finally {
            $this->removeSandbox($root);
        }
    }

    public function testResolveAttachmentPathRejectsSymlinkOutsideBase(): void
    {
        [$root, $base] = $this->makeAttachmentSandbox();
        try {
            symlink($root . '/secret.txt', $base . 'link.txt');
            self::assertNull(Episciences_Tools::resolveAttachmentPath($base, 'link.txt'));
        } finally {
            $this->removeSandbox($root);
        }
    }

    public function testResolveAttachmentPathRejectsMissingBaseDirectory(): void
    {
        self::assertNull(Episciences_Tools::resolveAttachmentPath('/nonexistent/dir/', 'a.txt'));
    }

    public function testFilterAttachmentNamesKeepsOnlyResolvableNames(): void
    {
        [$root, $base] = $this->makeAttachmentSandbox();
        try {
            $names = [0 => 'report 1.pdf', 1 => '../secret.txt', 2 => '', 3 => 'é.txt', 4 => 'sub/x.txt', 5 => ['a']];
            self::assertSame(
                [0 => 'report 1.pdf', 1 => 'é.txt'],
                Episciences_Tools::filterAttachmentNames($base, $names)
            );
            self::assertSame([], Episciences_Tools::filterAttachmentNames($base, []));
        } finally {
            $this->removeSandbox($root);
        }
    }

    /**
     * A sibling directory sharing the prefix of the base directory ("base" and "base2") must not
     * be taken for a part of it: this is what the separator in the prefix check is for.
     */
    public function testResolveAttachmentPathRejectsSymlinkToSiblingDirectorySharingThePrefix(): void
    {
        [$root, $base] = $this->makeAttachmentSandbox();
        try {
            mkdir($root . '/base2');
            file_put_contents($root . '/base2/other.txt', 'other');
            symlink($root . '/base2/other.txt', $base . 'sibling.txt');

            self::assertNull(Episciences_Tools::resolveAttachmentPath($base, 'sibling.txt'));
        } finally {
            $this->removeSandbox($root);
        }
    }

    public function testResolveAttachmentPathAcceptsSymlinkToAFileInsideTheBase(): void
    {
        [$root, $base] = $this->makeAttachmentSandbox();
        try {
            symlink($base . 'report 1.pdf', $base . 'alias.pdf');

            self::assertSame($base . 'alias.pdf', Episciences_Tools::resolveAttachmentPath($base, 'alias.pdf'));
        } finally {
            $this->removeSandbox($root);
        }
    }

    public function testResolveAttachmentPathDoesNotFollowADirectorySymlinkOutsideTheBase(): void
    {
        [$root, $base] = $this->makeAttachmentSandbox();
        try {
            symlink($root, $base . 'up');

            self::assertNull(Episciences_Tools::resolveAttachmentPath($base, 'up'), 'a directory is not a file');
            self::assertNull(Episciences_Tools::resolveAttachmentPath($base, 'up/secret.txt'), 'not a flat name');
        } finally {
            $this->removeSandbox($root);
        }
    }

    public function testResolveAttachmentPathIsNotFooledByTrailingDotsAndSpaces(): void
    {
        [$root, $base] = $this->makeAttachmentSandbox();
        try {
            foreach (['report 1.pdf ', 'report 1.pdf.', './report 1.pdf', 'sub/../report 1.pdf'] as $name) {
                self::assertNull(Episciences_Tools::resolveAttachmentPath($base, $name), $name);
            }
        } finally {
            $this->removeSandbox($root);
        }
    }

    public function testFilterAttachmentNamesReindexesTheKeptNames(): void
    {
        [$root, $base] = $this->makeAttachmentSandbox();
        try {
            self::assertSame(['é.txt'], Episciences_Tools::filterAttachmentNames($base, [3 => '../secret.txt', 7 => 'é.txt']));
        } finally {
            $this->removeSandbox($root);
        }
    }
}
