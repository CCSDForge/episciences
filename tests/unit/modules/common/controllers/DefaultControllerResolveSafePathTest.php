<?php

namespace unit\modules\common\controllers;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Behavioural tests for DefaultController::resolveSafePath().
 *
 * resolveSafePath() canonicalises a user-influenced relative path under a trusted
 * base directory and returns the absolute path only when it stays inside that base
 * (otherwise null). It is a pure method (realpath + string comparison, no $this, no
 * DB, no MVC stack), so we instantiate DefaultController without its constructor and
 * invoke the protected method by reflection. Real temporary directories/files are
 * created under the project build/ directory and removed in tearDown().
 *
 * @covers DefaultController::resolveSafePath
 */
final class DefaultControllerResolveSafePathTest extends TestCase
{
    private object $controller;
    private ReflectionMethod $method;

    /** Fixture root, created inside the project. */
    private string $root;
    /** Trusted base directory passed to resolveSafePath(). */
    private string $base;

    protected function setUp(): void
    {
        require_once APPLICATION_PATH . '/modules/common/controllers/DefaultController.php';

        $class = new ReflectionClass(\DefaultController::class);
        // Bypass Zend_Controller_Action::__construct() — the method under test
        // does not use $this.
        $this->controller = $class->newInstanceWithoutConstructor();

        $this->method = new ReflectionMethod(\DefaultController::class, 'resolveSafePath');
        $this->method->setAccessible(true);

        // Build a fixture tree inside the project (never outside it). The project
        // build/ directory is made writable (0777) by the `make test-php` target.
        //   <root>/outside.txt         (a file OUTSIDE the trusted base)
        //   <root>/files/              (the trusted base)
        //   <root>/files/doc.pdf       (a file inside the base)
        //   <root>/files/sub/          (a subdirectory inside the base)
        $buildDir = dirname(APPLICATION_PATH) . '/build';
        if (!is_dir($buildDir) || !is_writable($buildDir)) {
            self::markTestSkipped('Project build/ directory not writable for temporary fixtures');
        }

        $this->root = $buildDir . '/rsp_fixtures_' . uniqid('', false);
        $this->base = $this->root . '/files';

        if (!mkdir($concurrentTarget = $this->base . '/sub', 0777, true) && !is_dir($concurrentTarget)) {
            self::markTestSkipped('Unable to create temporary fixture directories');
        }
        file_put_contents($this->root . '/outside.txt', "outside\n");
        file_put_contents($this->base . '/doc.pdf', "%PDF-1.4\n");
        file_put_contents($this->base . '/sub/inner.txt', "inner\n");
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
    }

    private function resolve(string $relativePath): ?string
    {
        /** @var string|null $r */
        $r = $this->method->invoke($this->controller, $this->base, $relativePath);
        return $r;
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) && !is_link($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    // -----------------------------------------------------------------------
    // Paths that stay inside the base (must resolve)
    // -----------------------------------------------------------------------

    public function testReturnsCanonicalPathForFileInsideBase(): void
    {
        $result = $this->resolve('doc.pdf');

        self::assertSame(realpath($this->base . '/doc.pdf'), $result,
            'A file directly inside the base must resolve to its canonical path');
    }

    public function testReturnsCanonicalPathForFileInSubdirectory(): void
    {
        $result = $this->resolve('sub/inner.txt');

        self::assertSame(realpath($this->base . '/sub/inner.txt'), $result,
            'A file in a subdirectory of the base must resolve');
    }

    public function testDotSegmentsStayingInsideBaseAreAllowed(): void
    {
        // "./sub/../doc.pdf" stays inside the base
        $result = $this->resolve('./sub/../doc.pdf');

        self::assertSame(realpath($this->base . '/doc.pdf'), $result,
            'Dot segments that remain inside the base must resolve normally');
    }

    // -----------------------------------------------------------------------
    // Paths that resolve outside the base (must return null)
    // -----------------------------------------------------------------------

    public function testPathResolvingOutsideBaseReturnsNullEvenWhenTargetExists(): void
    {
        // outside.txt exists, but it sits OUTSIDE the trusted base.
        $result = $this->resolve('../outside.txt');

        self::assertNull($result,
            'A relative path resolving outside the base must return null even if the target exists');
    }

    public function testManyParentSegmentsReturnNull(): void
    {
        $result = $this->resolve('../../../../../../etc/hostname');

        self::assertNull($result, 'A path climbing above the base must return null');
    }

    public function testParentSegmentsFromSubdirectoryReturnNull(): void
    {
        $result = $this->resolve('sub/../../outside.txt');

        self::assertNull($result, 'A path that nets to a location outside the base must return null');
    }

    public function testSymlinkResolvingOutsideBaseReturnsNull(): void
    {
        $link = $this->base . '/link';
        if (!@symlink($this->root . '/outside.txt', $link)) {
            self::markTestSkipped('Symlinks not supported in this environment');
        }

        $result = $this->resolve('link');

        self::assertNull($result,
            'A symlink inside the base that resolves outside it must return null (realpath follows links)');
    }

    // -----------------------------------------------------------------------
    // Malformed input (must return null)
    // -----------------------------------------------------------------------

    public function testNulByteReturnsNull(): void
    {
        self::assertNull($this->resolve("doc.pdf\0.png"),
            'A NUL byte in the relative path must return null');
    }

    public function testControlCharacterReturnsNull(): void
    {
        self::assertNull($this->resolve("doc\n.pdf"),
            'A control character in the relative path must return null');
    }

    public function testEmptyRelativePathReturnsNull(): void
    {
        self::assertNull($this->resolve(''), 'An empty relative path must return null');
    }

    public function testNonExistentFileReturnsNull(): void
    {
        self::assertNull($this->resolve('does-not-exist.pdf'),
            'A non-existent file inside the base must return null (realpath fails)');
    }

    public function testNonExistentBaseReturnsNull(): void
    {
        $r = $this->method->invoke($this->controller, $this->root . '/no-such-base', 'doc.pdf');
        self::assertNull($r, 'A non-existent base directory must return null');
    }

    // -----------------------------------------------------------------------
    // Sibling directory sharing the base-name prefix
    // -----------------------------------------------------------------------

    public function testSiblingDirectoryWithSharedPrefixReturnsNull(): void
    {
        // Create "<root>/files_x/y.txt" — its path starts with the base path
        // string ("<root>/files") but it is NOT inside "<root>/files/".
        mkdir($this->root . '/files_x', 0777, true);
        file_put_contents($this->root . '/files_x/y.txt', "y\n");

        $result = $this->resolve('../files_x/y.txt');

        self::assertNull($result,
            'A sibling directory sharing the base-name prefix must not be treated as inside the base '
            . '(the DIRECTORY_SEPARATOR boundary check prevents prefix confusion)');
    }
}
