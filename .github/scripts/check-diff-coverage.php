<?php

declare(strict_types=1);

/**
 * Checks line coverage on added/modified PHP lines against a Clover XML report.
 *
 * Parses a PHPUnit Clover report (build/coverage.xml) and compares it with the
 * added/modified lines from `git diff` to verify the new-code coverage target
 * (default: 85%). Only executable statements recorded in Clover are counted.
 *
 * Usage:
 *   php .github/scripts/check-diff-coverage.php [--clover=build/coverage.xml] [--base=origin/main] [--threshold=85]
 *
 * Exit code 0 when coverage >= threshold (or no executable lines changed), 1 otherwise.
 */

const DEFAULT_CLOVER = 'build/coverage.xml';
const DEFAULT_BASE = 'origin/main';
const DEFAULT_THRESHOLD = 85.0;
const SOURCE_DIRS = ['application', 'library', 'scripts', 'src'];

/**
 * @param list<string> $argv
 * @return array{clover: string, base: string, threshold: float, stdin: bool}
 */
function parseOptions(array $argv): array
{
    $options = [
        'clover' => DEFAULT_CLOVER,
        'base' => DEFAULT_BASE,
        'threshold' => DEFAULT_THRESHOLD,
        'stdin' => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--clover=')) {
            $options['clover'] = substr($arg, 9);
        } elseif (str_starts_with($arg, '--base=')) {
            $options['base'] = substr($arg, 7);
        } elseif (str_starts_with($arg, '--threshold=')) {
            $options['threshold'] = (float) substr($arg, 12);
        } elseif ($arg === '--stdin') {
            $options['stdin'] = true;
        }
    }

    return $options;
}

/**
 * Normalizes an absolute or container path to a repository-relative path.
 */
function normalizeSourcePath(string $path): string
{
    $normalized = str_replace('\\', '/', $path);
    $pattern = '#(?:^|/)(' . implode('|', SOURCE_DIRS) . ')/(.+)$#';

    if (preg_match($pattern, $normalized, $matches) === 1) {
        return $matches[1] . '/' . $matches[2];
    }

    return ltrim($normalized, '/');
}

/**
 * Loads executable statement line counts from a Clover XML file.
 *
 * @return array<string, array<int, int>> Map of relative file path => [lineNumber => hitCount]
 */
function loadCloverCoverage(string $cloverPath): array
{
    if (!is_file($cloverPath)) {
        fwrite(STDERR, "Clover report not found: {$cloverPath}\n");
        fwrite(STDERR, "Run 'make test-php-coverage' or 'make test-coverage' first.\n");
        exit(1);
    }

    $xml = simplexml_load_file($cloverPath);
    if ($xml === false) {
        fwrite(STDERR, "Failed to parse Clover XML: {$cloverPath}\n");
        exit(1);
    }

    $coverage = [];
    $files = $xml->xpath('//file');
    if (!is_array($files)) {
        return $coverage;
    }

    foreach ($files as $fileNode) {
        $name = (string) ($fileNode['name'] ?? '');
        if ($name === '') {
            continue;
        }

        $relPath = normalizeSourcePath($name);
        $lines = $coverage[$relPath] ?? [];

        foreach ($fileNode->line as $lineNode) {
            if ((string) ($lineNode['type'] ?? '') !== 'stmt') {
                continue;
            }
            $num = (int) ($lineNode['num'] ?? 0);
            $count = (int) ($lineNode['count'] ?? 0);
            if ($num > 0) {
                $lines[$num] = max($lines[$num] ?? 0, $count);
            }
        }

        $coverage[$relPath] = $lines;
    }

    return $coverage;
}

/**
 * Returns added/modified line numbers per PHP source file from git diff.
 *
 * @return array<string, list<int>>
 */
function getChangedPhpLines(string $base): array
{
    $range = ($base === 'HEAD' || str_contains($base, '...')) ? $base : "{$base}...HEAD";
    $dirs = implode(' ', array_map('escapeshellarg', SOURCE_DIRS));
    $cmd = sprintf('git diff --unified=0 --diff-filter=AM %s -- %s 2>&1', escapeshellarg($range), $dirs);

    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);

    if ($exitCode !== 0) {
        fwrite(STDERR, "git diff failed (range: {$range}):\n" . implode("\n", $output) . "\n");
        exit(1);
    }

    return parseUnifiedDiff($output);
}

/**
 * @param list<string> $diffLines
 * @return array<string, list<int>>
 */
function parseUnifiedDiff(array $diffLines): array
{
    $changed = [];
    $currentFile = null;

    foreach ($diffLines as $line) {
        if (str_starts_with($line, '+++ b/')) {
            $file = substr($line, 6);
            $currentFile = str_ends_with($file, '.php') ? $file : null;
            continue;
        }

        if ($currentFile === null || !str_starts_with($line, '@@ ')) {
            continue;
        }

        if (preg_match('/\+([0-9]+)(?:,([0-9]+))?/', $line, $matches) === 1) {
            $start = (int) $matches[1];
            $count = isset($matches[2]) ? (int) $matches[2] : 1;
            for ($i = 0; $i < $count; $i++) {
                $changed[$currentFile][] = $start + $i;
            }
        }
    }

    return $changed;
}

/**
 * @param array<string, list<int>> $changedLines
 * @param array<string, array<int, int>> $clover
 * @return array{covered: int, total: int, uncoveredByFile: array<string, list<int>>, missingFiles: list<string>}
 */
function evaluateDiffCoverage(array $changedLines, array $clover): array
{
    $covered = 0;
    $total = 0;
    $uncoveredByFile = [];
    $missingFiles = [];

    foreach ($changedLines as $file => $lines) {
        if (!isset($clover[$file])) {
            $missingFiles[] = $file;
            continue;
        }

        foreach ($lines as $lineNum) {
            if (!isset($clover[$file][$lineNum])) {
                continue;
            }
            $total++;
            if ($clover[$file][$lineNum] > 0) {
                $covered++;
            } else {
                $uncoveredByFile[$file][] = $lineNum;
            }
        }
    }

    return [
        'covered' => $covered,
        'total' => $total,
        'uncoveredByFile' => $uncoveredByFile,
        'missingFiles' => $missingFiles,
    ];
}

/**
 * @param list<string> $argv
 */
function main(array $argv): int
{
    $options = parseOptions($argv);
    $clover = loadCloverCoverage($options['clover']);

    if ($options['stdin']) {
        $raw = file_get_contents('php://stdin');
        $diffLines = $raw === false ? [] : explode("\n", $raw);
        $changedLines = parseUnifiedDiff($diffLines);
    } else {
        $changedLines = getChangedPhpLines($options['base']);
    }

    if ($changedLines === []) {
        echo "No added or modified PHP source lines found compared to {$options['base']}.\n";
        return 0;
    }

    $result = evaluateDiffCoverage($changedLines, $clover);

    if ($result['missingFiles'] !== []) {
        echo "Warning: no coverage data in {$options['clover']} for:\n";
        foreach ($result['missingFiles'] as $file) {
            echo "  - {$file}\n";
        }
    }

    if ($result['total'] === 0) {
        echo "No executable PHP lines found in the diff.\n";
        return 0;
    }

    $percent = ($result['covered'] / $result['total']) * 100.0;
    printf(
        "Diff coverage: %.2f%% (%d/%d executable lines covered, threshold: %.2f%%)\n",
        $percent,
        $result['covered'],
        $result['total'],
        $options['threshold']
    );

    if ($result['uncoveredByFile'] !== []) {
        echo "Uncovered lines:\n";
        foreach ($result['uncoveredByFile'] as $file => $lines) {
            echo "  {$file}: " . implode(', ', $lines) . "\n";
        }
    }

    return $percent >= $options['threshold'] ? 0 : 1;
}

exit(main($argv));
