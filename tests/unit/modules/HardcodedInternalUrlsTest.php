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
 * Internal URLs must be built with JS_PREFIX_URL (JavaScript), PREFIX_URL or $this->url() (views, PHP),
 * and the redirector / gotoSimple() / gotoRoute() calls must pass the PREFIX_ROUTE parameter.
 * $this->_helper->url($action, $controller) is prefixed by Episciences_Controller_Action_Helper_Url.
 * An internal URL is a path starting with the name of a journal controller.
 *
 * Mostly triggered by the merges from main / staging, where the prefix does not exist.
 */
final class HardcodedInternalUrlsTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /** JavaScript not maintained in this project */
    private const JS_EXCLUDED_DIRS = ['/public/js/vendor/', '/public/js/tinymce/'];

    private const PHP_DIRS = ['application', 'library/Episciences'];

    /** Lines where a path starting with a controller name is a view script or a file path, not a URL */
    private const PHP_NOT_URL_CONTEXTS = '~viewScript|renderScript|->render\(|partial(?:Loop)?\(|setScriptAction|setFilePath|buildDocumentPath~';

    /** Calls building the URL from the route: the journal prefix is a route parameter */
    private const REDIRECTOR_CALLS = '~(?:_helper->redirector|->gotoSimple(?:AndExit)?|->gotoRoute(?:AndExit)?)\s*\(~';

    /**
     * Known exceptions, as "relative/path" (whole file) or "relative/path:line" => reason
     * @var array<string, string>
     */
    private const ALLOWED = [
        'application/modules/common/controllers/RobotsDefaultController.php' => 'robots.txt paths, only written for the journals not switched to the new front (served without prefix)',
        'application/modules/common/controllers/PaperDefaultController.php:30' => 'COMMENTS_STR: directory of the comment files, not a URL',
    ];

    public function testJavaScriptHasNoUnprefixedInternalUrl(): void
    {
        $violations = [];

        foreach ($this->findFiles(['public/js'], '.js') as $file) {
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

        foreach ($this->findFiles(['application'], '.phtml') as $file) {
            array_push($violations, ...$this->findUnprefixedAttributes($file), ...$this->findUnprefixedLiterals($file));
        }

        $violations = array_values(array_unique($violations));

        self::assertSame([], $violations, $this->buildMessage($violations, '$this->url([...]) or PREFIX_URL'));
    }

    public function testPhpHasNoUnprefixedInternalUrl(): void
    {
        $violations = [];

        foreach ($this->findFiles(self::PHP_DIRS, '.php') as $file) {
            array_push($violations, ...$this->findUnprefixedLiterals($file, true), ...$this->findUnprefixedLocationHeaders($file));
        }

        self::assertSame([], $violations, $this->buildMessage($violations, '$this->url([...]) or PREFIX_URL . \'controller/action\''));
    }

    public function testRedirectorCallsKeepTheJournalPrefix(): void
    {
        $violations = [];

        foreach ($this->findFiles(self::PHP_DIRS, '.php') as $file) {
            array_push($violations, ...$this->findRedirectorCallsWithoutPrefix($file));
        }

        self::assertSame([], $violations, $this->buildMessage($violations, 'a [PREFIX_ROUTE => RVCODE] parameter'));
    }

    /**
     * Zend_Controller_Action_Helper_Url::simple() ($this->_helper->url($action, $controller)) builds the URL
     * without the router: Bootstrap replaces it with a helper adding PREFIX_URL, which must not be bypassed
     */
    public function testUrlActionHelperKeepsTheJournalPrefix(): void
    {
        $bootstrap = (string)file_get_contents(self::ROOT . '/application/Bootstrap.php');

        self::assertStringContainsString(
            'addHelper(new Episciences_Controller_Action_Helper_Url())',
            $bootstrap,
            'application/Bootstrap.php must register Episciences_Controller_Action_Helper_Url in _initRoutes()'
        );

        $violations = [];

        foreach ($this->findFiles(self::PHP_DIRS, '.php') as $file) {
            array_push($violations, ...$this->collect(
                $file,
                '~\bZend_Controller_Action_Helper_Url\b~',
                static fn(array $match, string $content, string $code): bool => !str_contains($code, 'extends Zend_Controller_Action_Helper_Url')
            ));
        }

        self::assertSame([], $violations, $this->buildMessage($violations, '$this->_helper->url() or $this->url([...])'));
    }

    /**
     * String literals starting with an internal path, not appended to a previous string
     * ('...' + '/paper/' in JS, $url . '/paper/' or $base . sprintf('/paper/%s') in PHP are path segments, not URLs)
     * @return string[]
     */
    private function findUnprefixedLiterals(string $file, bool $isPhp = false): array
    {
        $pattern = sprintf('~(\S)\s*([\'"`])/(?:%s)(?=[/?#\'"`])~i', $this->controllersPattern());

        return $this->collect($file, $pattern, static function (array $match, string $content, string $code) use ($isPhp): bool {
            if (in_array($match[1][0], ['+', '.'], true)) {
                return false;
            }

            if (!$isPhp) {
                return true;
            }

            $before = substr($content, max(0, $match[0][1] - 40), min(40, $match[0][1]) + 1);

            return !preg_match('~\.\s*sprintf\($~', $before) && !preg_match(self::PHP_NOT_URL_CONTEXTS, $code);
        });
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
     * header('Location: /paper/view/id/1')
     * @return string[]
     */
    private function findUnprefixedLocationHeaders(string $file): array
    {
        return $this->collect($file, sprintf('~Location:\s*/(?:%s)(?=[/?#\'"])~i', $this->controllersPattern()));
    }

    /**
     * redirector('view', 'paper', null, ['id' => 1]) builds the URL from the route: without the PREFIX_ROUTE
     * parameter, the journal prefix is lost
     * @return string[]
     */
    private function findRedirectorCallsWithoutPrefix(string $file): array
    {
        return $this->collect($file, self::REDIRECTOR_CALLS, static function (array $match, string $content): bool {
            $arguments = self::extractArguments($content, $match[0][1] + strlen($match[0][0]));

            // redirector() without arguments returns the helper (e.g. ->redirector()->gotoUrl(...))
            return trim($arguments) !== '' && !str_contains($arguments, 'PREFIX_ROUTE');
        });
    }

    /**
     * Text between the opening parenthesis (excluded, at $offset - 1) and the matching closing one
     */
    private static function extractArguments(string $content, int $offset): string
    {
        $depth = 1;
        $length = strlen($content);

        for ($i = $offset; $i < $length; $i++) {
            if ($content[$i] === '(') {
                $depth++;
            } elseif ($content[$i] === ')' && --$depth === 0) {
                return substr($content, $offset, $i - $offset);
            }
        }

        return substr($content, $offset);
    }

    /**
     * @param callable(array, string, string): bool|null $filter receives the match, the file content and the code line
     * @return string[] "relative/path:line: code"
     */
    private function collect(string $file, string $pattern, ?callable $filter = null): array
    {
        $relative = $this->relativePath($file);

        if (array_key_exists($relative, self::ALLOWED)) {
            return [];
        }

        $content = (string)file_get_contents($file);
        $lines = explode("\n", $content);
        $violations = [];

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            // line of the URL itself, not of the preceding character
            $offset = $match[0][1] + strlen($match[0][0]) - 1;
            $line = substr_count($content, "\n", 0, $offset) + 1;
            $code = trim($lines[$line - 1]);

            if ($this->isComment($code) || array_key_exists("$relative:$line", self::ALLOWED)) {
                continue;
            }

            if ($filter !== null && !$filter($match, $content, $code)) {
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
        return str_starts_with($code, '//') || str_starts_with($code, '*') || str_starts_with($code, '/*') || str_starts_with($code, '#');
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
     * @param string[] $dirs
     * @return string[]
     */
    private function findFiles(array $dirs, string $extension): array
    {
        $files = [];

        foreach ($dirs as $dir) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::ROOT . '/' . $dir, RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), $extension)) {
                    $files[] = str_replace('\\', '/', $file->getPathname());
                }
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
