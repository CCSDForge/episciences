<?php

declare(strict_types=1);

namespace unit\modules;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guard for the path-based URL routing of the *-epi-manager branches: a journal is served under a prefix
 * (e.g. /epijinfo/administratepaper/view), so an internal URL hardcoded from the web root
 * (e.g. '/administratepaper/view') reaches the wrong application.
 *
 * Internal URLs must be built with JS_PREFIX_URL (JavaScript), PREFIX_URL or $this->url() (views).
 * An internal URL is a path starting with the name of a journal controller.
 *
 * Mostly triggered by the merges from main / staging, where the prefix does not exist.
 */
final class HardcodedInternalUrlsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /** JavaScript not maintained in this project */
    private const JS_EXCLUDED_DIRS = ['/public/js/vendor/', '/public/js/tinymce/'];

    /**
     * Known exceptions, as "relative/path:line" => reason
     * @var array<string, string>
     */
    private const ALLOWED = [];

    public function testJavaScriptHasNoUnprefixedInternalUrl(): void
    {
        $violations = [];

        foreach ($this->findFiles('public/js', '.js') as $file) {
            if ($this->isExcludedJs($file)) {
                continue;
            }
            array_push($violations, ...$this->findUnprefixedLiterals($file));
        }

        self::assertSame([], $violations, $this->buildMessage($violations, 'JS_PREFIX_URL + \'controller/action\''));
    }

    public function testViewsHaveNoUnprefixedInternalUrl(): void
    {
        $violations = [];

        foreach ($this->findFiles('application', '.phtml') as $file) {
            array_push($violations, ...$this->findUnprefixedAttributes($file), ...$this->findUnprefixedLiterals($file));
        }

        self::assertSame([], array_values(array_unique($violations)), $this->buildMessage($violations, '$this->url([...]) or PREFIX_URL'));
    }

    /**
     * String literals starting with an internal path, not appended to a previous string
     * ('...' + '/paper/' in JS, $url . '/paper/' in PHP are path segments, not URLs)
     * @return string[]
     */
    private function findUnprefixedLiterals(string $file): array
    {
        $pattern = sprintf('~(\S)\s*([\'"`])/(?:%s)(?=[/?#\'"`])~i', $this->controllersPattern());

        return $this->collect($file, $pattern, static fn(array $match): bool => !in_array($match[1][0], ['+', '.'], true));
    }

    /**
     * HTML attributes holding an internal path: href="/user/logout", action="/administratepaper/save"...
     * @return string[]
     */
    private function findUnprefixedAttributes(string $file): array
    {
        $pattern = sprintf('~\b(?:href|src|action|formaction|data-[\w-]+)\s*=\s*(["\'])/(?:%s)(?=[/?#"\'])~i', $this->controllersPattern());

        return $this->collect($file, $pattern);
    }

    /**
     * @param callable(array): bool|null $filter
     * @return string[] "relative/path:line: code"
     */
    private function collect(string $file, string $pattern, ?callable $filter = null): array
    {
        $content = (string)file_get_contents($file);
        $lines = explode("\n", $content);
        $relative = $this->relativePath($file);
        $violations = [];

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            if ($filter !== null && !$filter($match)) {
                continue;
            }

            // line of the URL itself, not of the preceding character
            $offset = $match[0][1] + strlen($match[0][0]) - 1;
            $line = substr_count($content, "\n", 0, $offset) + 1;
            $code = trim($lines[$line - 1]);

            if ($this->isComment($code) || array_key_exists("$relative:$line", self::ALLOWED)) {
                continue;
            }

            $violations[] = sprintf('%s:%d: %s', $relative, $line, $code);
        }

        return $violations;
    }

    private function controllersPattern(): string
    {
        static $pattern = null;

        if ($pattern === null) {
            $controllers = [];

            foreach (glob(self::ROOT . '/application/modules/journal/controllers/*Controller.php') ?: [] as $file) {
                $controllers[] = strtolower(basename($file, 'Controller.php'));
            }

            self::assertNotEmpty($controllers, 'No journal controller found');

            // longest names first: "administratepaper" before "administrate"
            usort($controllers, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
            $pattern = implode('|', array_map(static fn(string $c): string => preg_quote($c, '~'), $controllers));
        }

        return $pattern;
    }

    private function isComment(string $code): bool
    {
        return str_starts_with($code, '//') || str_starts_with($code, '*') || str_starts_with($code, '/*');
    }

    private function isExcludedJs(string $file): bool
    {
        if (str_ends_with($file, '.min.js')) {
            return true;
        }

        foreach (self::JS_EXCLUDED_DIRS as $dir) {
            if (str_contains($file, $dir)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    private function findFiles(string $dir, string $extension): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::ROOT . '/' . $dir, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), $extension)) {
                $files[] = str_replace('\\', '/', $file->getPathname());
            }
        }

        sort($files);

        return $files;
    }

    private function relativePath(string $file): string
    {
        return ltrim(str_replace(str_replace('\\', '/', (string)realpath(self::ROOT)), '', (string)realpath($file)), '/');
    }

    /**
     * @param string[] $violations
     */
    private function buildMessage(array $violations, string $fix): string
    {
        return sprintf(
            "Internal URLs hardcoded from the web root: the journal prefix is lost on the *-epi-manager branches.\nUse %s instead (or add a justified exception to %s::ALLOWED):\n  %s",
            $fix,
            self::class,
            implode("\n  ", array_unique($violations))
        );
    }
}
