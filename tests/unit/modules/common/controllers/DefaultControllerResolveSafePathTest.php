<?php

namespace unit\modules\common\controllers;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Behavioural tests for DefaultController::resolveSafePath().
 *
 * This helper is the core of the path-traversal fix (audit finding F-1): it
 * confines a user-influenced relative path under a trusted base directory so that
 * "../../etc/passwd"-style inputs cannot escape the review files tree.
 *
 * resolveSafePath() is a pure method (realpath + string comparison, no $this, no
 * DB, no MVC stack), so we instantiate DefaultController without its constructor
 * and invoke the protected method by reflection. Real temporary directories/files
 * are created under this test's own directory (never outside the project) and
 * removed in tearDown().
 *
 * @covers DefaultController::resolveSafePath
 */
final class DefaultControllerResolveSafePathTest extends TestCase
{
    private object $controller;
    private ReflectionMethod $method;

    /** Fixture root, created inside the project (this test directory). */
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
        //   <root>/secret.txt          (OUTSIDE the trusted base)
        //   <root>/files/              (the trusted base)
        //   <root>/files/doc.pdf       (a legitimate file inside the base)
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
        file_put_contents($this->root . '/secret.txt', "TOP SECRET\n");
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
    // Legitimate access (must succeed)
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
            'A file in a subdirectory of the base must be allowed');
    }

    public function testHarmlessDotSegmentsInsideBaseAreAllowed(): void
    {
        // "./sub/../doc.pdf" stays inside the base
        $result = $this->resolve('./sub/../doc.pdf');

        self::assertSame(realpath($this->base . '/doc.pdf'), $result,
            'Dot segments that remain inside the base must resolve normally');
    }

    // -----------------------------------------------------------------------
    // Path traversal (must be blocked) — the security contract
    // -----------------------------------------------------------------------

    public function testTraversalEscapingBaseIsBlockedEvenWhenTargetExists(): void
    {
        // secret.txt exists, but it is OUTSIDE the trusted base.
        $result = $this->resolve('../secret.txt');

        self::assertNull($result,
            'A relative path escaping the base must return null even if the target file exists');
    }

    public function testDeepTraversalToSystemFileIsBlocked(): void
    {
        // The F-1 exploit shape: climb out of the files tree toward a config file.
        $result = $this->resolve('../../../../../../etc/passwd');

        self::assertNull($result, 'Deep traversal outside the base must be blocked');
    }

    public function testTraversalFromSubdirectoryIsBlocked(): void
    {
        $result = $this->resolve('sub/../../secret.txt');

        self::assertNull($result, 'Traversal that nets to a path outside the base must be blocked');
    }

    public function testSymlinkPointingOutsideBaseIsBlocked(): void
    {
        $link = $this->base . '/escape';
        if (!@symlink($this->root . '/secret.txt', $link)) {
            self::markTestSkipped('Symlinks not supported in this environment');
        }

        $result = $this->resolve('escape');

        self::assertNull($result,
            'A symlink inside the base resolving outside it must be blocked (realpath follows links)');
    }

    // -----------------------------------------------------------------------
    // Malformed / hostile input (must be rejected)
    // -----------------------------------------------------------------------

    public function testNulByteIsRejected(): void
    {
        self::assertNull($this->resolve("doc.pdf\0.png"),
            'A NUL byte in the relative path must be rejected');
    }

    public function testControlCharacterIsRejected(): void
    {
        self::assertNull($this->resolve("doc\n.pdf"),
            'Control characters in the relative path must be rejected');
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
    // Prefix-confusion guard (sibling directory sharing the base name prefix)
    // -----------------------------------------------------------------------

    public function testSiblingDirectoryWithSharedPrefixIsNotConfinedToBase(): void
    {
        // Create "<root>/files_evil/x.txt" — its path starts with the base path
        // string ("<root>/files") but it is NOT inside "<root>/files/".
        mkdir($this->root . '/files_evil', 0755, true);
        file_put_contents($this->root . '/files_evil/x.txt', "evil\n");

        $result = $this->resolve('../files_evil/x.txt');

        self::assertNull($result,
            'A sibling directory sharing the base-name prefix must not be treated as inside the base '
            . '(the DIRECTORY_SEPARATOR boundary check must prevent prefix confusion)');
    }
}
