<?php

declare(strict_types=1);

/**
 * Refuses new tests that read the source code of the application instead of running it.
 *
 * Such tests check that some text is present in a method (file_get_contents() on a controller,
 * extractMethod(), ReflectionMethod::getStartLine()...): they break on any refactoring and pass
 * on code that does not work. Tests must call the code (see tests/unit/modules/ControllerTestCase.php).
 *
 * A test file is reported when it:
 * - slices source lines (getStartLine(), getEndLine(), extractMethod()), or
 * - reads a file (file_get_contents(), file(), fopen()...) while referring to application code
 *   (a .php or .phtml path, a code directory, or ReflectionClass::getFileName()).
 *
 * The files that did so before this check are listed in tests/legacy-source-reading-tests.txt.
 * The list may only shrink: a converted file must be removed from it.
 *
 * Usage: php .github/scripts/check-source-reading-tests.php
 * Exit code 1 when a test file not in the list reads source code.
 */

const ROOT = __DIR__ . '/../..';
const TESTS_DIR = 'tests';
const LEGACY_LIST = 'tests/legacy-source-reading-tests.txt';

const SLICING_CALLS = ['getstartline', 'getendline', 'extractmethod'];
const READING_CALLS = ['file_get_contents', 'file', 'fopen', 'splfileobject', 'php_strip_whitespace', 'highlight_file'];
const CODE_REFERENCE_CALLS = ['getfilename'];
const CODE_PATH = '#(\.php|\.phtml)$|/(modules|controllers|views|library|scripts|src)(/|$)#';

/**
 * @return list<string> paths of the PHP files under tests/, relative to the root
 */
function testFiles(): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT . '/' . TESTS_DIR, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = TESTS_DIR . substr($file->getPathname(), strlen(ROOT . '/' . TESTS_DIR));
        }
    }
    sort($files);

    return $files;
}

/**
 * @return list<string> paths listed in the legacy file
 */
function legacyFiles(): array
{
    $lines = file(ROOT . '/' . LEGACY_LIST, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $files = array_filter(array_map('trim', $lines), static fn(string $line): bool => $line !== '' && $line[0] !== '#');

    return array_values($files);
}

function readsSourceCode(string $code): bool
{
    $calls = [];
    $refersToCode = false;
    $inInclude = false;
    $tokens = token_get_all($code);

    foreach ($tokens as $i => $token) {
        if (!is_array($token)) {
            if ($token === ';') {
                $inInclude = false;
            }
            continue;
        }

        [$id, $text] = $token;

        if (in_array($id, [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true)) {
            $inInclude = true;
        } elseif (in_array($id, [T_STRING, T_NAME_FULLY_QUALIFIED], true) && meaningfulToken($tokens, $i, 1) === '(') {
            $name = strtolower(ltrim($text, '\\'));
            // A method of the same name as a reading function (e.g. $this->file()) reads nothing
            $isMethod = in_array(meaningfulToken($tokens, $i, -1), ['->', '?->', '::'], true);
            if (!$isMethod || !in_array($name, READING_CALLS, true)) {
                $calls[] = $name;
            }
        } elseif ($id === T_CONSTANT_ENCAPSED_STRING && !$inInclude && preg_match(CODE_PATH, trim($text, '\'"'))) {
            $refersToCode = true;
        }
    }

    if (array_intersect($calls, SLICING_CALLS) !== []) {
        return true;
    }

    $refersToCode = $refersToCode || array_intersect($calls, CODE_REFERENCE_CALLS) !== [];

    return $refersToCode && array_intersect($calls, READING_CALLS) !== [];
}

/**
 * Text of the first token after (+1) or before (-1) $index that is not blank or a comment.
 *
 * @param array<int, mixed> $tokens
 */
function meaningfulToken(array $tokens, int $index, int $direction): ?string
{
    for ($i = $index + $direction, $count = count($tokens); $i >= 0 && $i < $count; $i += $direction) {
        $token = $tokens[$i];
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return is_array($token) ? $token[1] : $token;
    }

    return null;
}

$legacy = legacyFiles();
$offending = array_values(array_filter(testFiles(), static fn(string $file): bool => readsSourceCode((string)file_get_contents(ROOT . '/' . $file))));

$new = array_diff($offending, $legacy);
$converted = array_diff($legacy, $offending);

foreach ($converted as $file) {
    // A warning only: the conversion and the list update may land in different pull requests
    printf("::warning file=%s::No longer reads source code: remove it from %s\n", $file, LEGACY_LIST);
}

foreach ($new as $file) {
    printf("::error file=%s::This test reads the source code of the application: run the code instead (see tests/unit/modules/ControllerTestCase.php)\n", $file);
}

printf("%d test file(s) still read source code, %d new.\n", count($offending), count($new));

exit($new === [] ? 0 : 1);
