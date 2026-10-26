<?php

declare(strict_types=1);

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
 * Mostly triggered by the merges from main / staging, where the prefix does not exist: this script only
 * exists on the *-epi-manager branches.
 *
 * Usage: php .github/scripts/check-hardcoded-internal-urls.php
 * Exit code 1 when an internal URL is hardcoded.
 */

const ROOT = __DIR__ . '/../..';

/** JavaScript not maintained in this project */
const JS_EXCLUDED_DIRS = ['/public/js/vendor/', '/public/js/tinymce/'];

const PHP_DIRS = ['application', 'library/Episciences'];

/** Lines where a path starting with a controller name is a view script or a file path, not a URL */
const PHP_NOT_URL_CONTEXTS = '~viewScript|renderScript|->render\(|partial(?:Loop)?\(|setScriptAction|setFilePath|buildDocumentPath~';

/** Calls building the URL from the route: the journal prefix is a route parameter */
const REDIRECTOR_CALLS = '~(?:_helper->redirector|->gotoSimple(?:AndExit)?|->gotoRoute(?:AndExit)?)\s*\(~';

/** Known exceptions for a whole file: relative path => reason */
const ALLOWED_FILES = [
    'application/modules/common/controllers/RobotsDefaultController.php' => 'robots.txt paths, only written for the journals not switched to the new front (served without prefix)',
];

/**
 * Known exceptions for some lines, found by their text rather than by their number (which moves on every edit
 * above them): relative path => [text contained in the line => reason]
 */
const ALLOWED_LINES = [
    'application/modules/common/controllers/PaperDefaultController.php' => [
        'COMMENTS_STR' => 'directory of the comment files, not a URL',
    ],
];

/**
 * @param string[] $dirs
 * @return string[] absolute paths
 */
function findFiles(array $dirs, string $extension): array
{
    $files = [];

    foreach ($dirs as $dir) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT . '/' . $dir, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile() && str_ends_with($file->getFilename(), $extension)) {
                $files[] = str_replace('\\', '/', $file->getPathname());
            }
        }
    }

    sort($files);

    return $files;
}

function relativePath(string $file): string
{
    return ltrim(str_replace(str_replace('\\', '/', (string)realpath(ROOT)), '', (string)realpath($file)), '/');
}

function isExcludedJs(string $file): bool
{
    if (str_ends_with($file, '.min.js')) {
        return true;
    }

    foreach (JS_EXCLUDED_DIRS as $dir) {
        if (str_contains($file, $dir)) {
            return true;
        }
    }

    return false;
}

function isComment(string $code): bool
{
    return str_starts_with($code, '//') || str_starts_with($code, '*') || str_starts_with($code, '/*') || str_starts_with($code, '#');
}

function isAllowedLine(string $relative, string $code): bool
{
    foreach (array_keys(ALLOWED_LINES[$relative] ?? []) as $text) {
        if (str_contains($code, $text)) {
            return true;
        }
    }

    return false;
}

function controllersPattern(): string
{
    static $pattern = null;

    if ($pattern === null) {
        $controllers = [];

        foreach (glob(ROOT . '/application/modules/journal/controllers/*Controller.php') ?: [] as $file) {
            $controllers[] = strtolower(basename($file, 'Controller.php'));
        }

        if ($controllers === []) {
            fwrite(STDERR, "No journal controller found\n");
            exit(2);
        }

        // longest names first: "administratepaper" before "administrate"
        usort($controllers, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
        $pattern = implode('|', array_map(static fn(string $c): string => preg_quote($c, '~'), $controllers));
    }

    return $pattern;
}

/**
 * @param callable(array, string, string): bool|null $filter receives the match, the file content and the code line
 * @return list<array{string, int, string}> [relative path, line, code]
 */
function collect(string $file, string $pattern, ?callable $filter = null): array
{
    $relative = relativePath($file);

    if (array_key_exists($relative, ALLOWED_FILES)) {
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

        if (isComment($code) || isAllowedLine($relative, $code)) {
            continue;
        }

        if ($filter !== null && !$filter($match, $content, $code)) {
            continue;
        }

        $violations[] = [$relative, $line, $code];
    }

    return $violations;
}

/**
 * String literals starting with an internal path, not appended to a previous string
 * ('...' + '/paper/' in JS, $url . '/paper/' or $base . sprintf('/paper/%s') in PHP are path segments, not URLs)
 * @return list<array{string, int, string}>
 */
function findUnprefixedLiterals(string $file, bool $isPhp = false): array
{
    $pattern = sprintf('~(\S)\s*([\'"`])/(?:%s)(?=[/?#\'"`])~i', controllersPattern());

    return collect($file, $pattern, static function (array $match, string $content, string $code) use ($isPhp): bool {
        if (in_array($match[1][0], ['+', '.'], true)) {
            return false;
        }

        if (!$isPhp) {
            return true;
        }

        $before = substr($content, max(0, $match[0][1] - 40), min(40, $match[0][1]) + 1);

        return !preg_match('~\.\s*sprintf\($~', $before) && !preg_match(PHP_NOT_URL_CONTEXTS, $code);
    });
}

/**
 * HTML attributes holding an internal path: href="/user/logout", action="/administratepaper/save"...
 * @return list<array{string, int, string}>
 */
function findUnprefixedAttributes(string $file): array
{
    $pattern = sprintf('~\b(?:href|src|action|formaction|data-[\w-]+)\s*=\s*(["\'])/(?:%s)(?=[/?#"\'])~i', controllersPattern());

    return collect($file, $pattern);
}

/**
 * header('Location: /paper/view/id/1')
 * @return list<array{string, int, string}>
 */
function findUnprefixedLocationHeaders(string $file): array
{
    return collect($file, sprintf('~Location:\s*/(?:%s)(?=[/?#\'"])~i', controllersPattern()));
}

/**
 * Text between the opening parenthesis (excluded, at $offset - 1) and the matching closing one
 */
function extractArguments(string $content, int $offset): string
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
 * redirector('view', 'paper', null, ['id' => 1]) builds the URL from the route: without the PREFIX_ROUTE
 * parameter, the journal prefix is lost
 * @return list<array{string, int, string}>
 */
function findRedirectorCallsWithoutPrefix(string $file): array
{
    return collect($file, REDIRECTOR_CALLS, static function (array $match, string $content): bool {
        $arguments = extractArguments($content, $match[0][1] + strlen($match[0][0]));

        // redirector() without arguments returns the helper (e.g. ->redirector()->gotoUrl(...))
        return trim($arguments) !== '' && !str_contains($arguments, 'PREFIX_ROUTE');
    });
}

/**
 * Zend_Controller_Action_Helper_Url::simple() ($this->_helper->url($action, $controller)) builds the URL
 * without the router: Bootstrap replaces it with a helper adding PREFIX_URL, which must not be bypassed
 * @return list<array{string, int, string}>
 */
function findZendUrlHelperUses(string $file): array
{
    return collect(
        $file,
        '~\bZend_Controller_Action_Helper_Url\b~',
        static fn(array $match, string $content, string $code): bool => !str_contains($code, 'extends Zend_Controller_Action_Helper_Url')
    );
}

/**
 * @return array<string, array{string, list<array{string, int, string}>}> check => [fix, violations]
 */
function runChecks(): array
{
    $js = $views = $php = $redirector = $urlHelper = [];

    foreach (findFiles(['public/js'], '.js') as $file) {
        if (!isExcludedJs($file)) {
            array_push($js, ...findUnprefixedLiterals($file));
        }
    }

    foreach (findFiles(['application'], '.phtml') as $file) {
        array_push($views, ...findUnprefixedAttributes($file), ...findUnprefixedLiterals($file));
    }

    foreach (findFiles(PHP_DIRS, '.php') as $file) {
        array_push($php, ...findUnprefixedLiterals($file, true), ...findUnprefixedLocationHeaders($file));
        array_push($redirector, ...findRedirectorCallsWithoutPrefix($file));
        array_push($urlHelper, ...findZendUrlHelperUses($file));
    }

    $bootstrap = 'application/Bootstrap.php';
    if (!str_contains((string)file_get_contents(ROOT . '/' . $bootstrap), 'addHelper(new Episciences_Controller_Action_Helper_Url())')) {
        $urlHelper[] = [$bootstrap, 1, 'Episciences_Controller_Action_Helper_Url must be registered in _initRoutes()'];
    }

    return [
        'JavaScript' => ['JS_PREFIX_URL + \'controller/action\'', $js],
        'views' => ['$this->url([...]) or PREFIX_URL', array_values(array_unique($views, SORT_REGULAR))],
        'PHP' => ['$this->url([...]) or PREFIX_URL . \'controller/action\'', $php],
        'redirector calls' => ['a [PREFIX_ROUTE => RVCODE] parameter', $redirector],
        'Url action helper' => ['$this->_helper->url() or $this->url([...])', $urlHelper],
    ];
}

$total = 0;

foreach (runChecks() as $check => [$fix, $violations]) {
    foreach ($violations as [$file, $line, $code]) {
        printf("::error file=%s,line=%d::Internal URL hardcoded from the web root (%s): use %s. %s\n", $file, $line, $check, $fix, $code);
    }
    $total += count($violations);
}

if ($total > 0) {
    printf(
        "%d internal URL(s) hardcoded from the web root: the journal prefix is lost on the *-epi-manager branches.\n"
        . "Fix them, or add a justified exception to ALLOWED_FILES / ALLOWED_LINES in %s.\n",
        $total,
        '.github/scripts/check-hardcoded-internal-urls.php'
    );
    exit(1);
}

echo "No internal URL hardcoded from the web root.\n";
exit(0);
