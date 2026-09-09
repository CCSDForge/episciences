<?php

declare(strict_types=1);

namespace Episciences\Journal\Provisioning;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Creates the on-disk directory tree for a new journal (data/<rvcode>/), mirroring
 * Bootstrap::_initcheckApplicationDirectories(), and — when a template journal is given —
 * copies a deliberately narrow set of its static files across: only config/ and languages/,
 * skipping secrets and never overwriting a file that already exists on the target (that
 * "skip if present" rule is what makes this safe to re-run under --complete).
 *
 * files/ and public/ are NOT cloned wholesale, even though that is what a naive "copy the
 * template's data directory" would do: on a real journal, files/ is almost entirely
 * per-paper submission storage (files/<docid>/...), and public/ holds per-volume generated
 * exports (DOAJ XML, merged PDFs) alongside the header/style assets — verified against actual
 * dev-dump journal directories, one of which held 73 MB of real per-paper files under files/
 * with no journal-level content there at all. The handful of header logo images that *do*
 * need cloning are copied individually by WebsiteCloner, which knows their exact filenames
 * from the WEBSITE_HEADER rows — not by copying the whole directory they live in.
 *
 * Deliberately never uses the REVIEW_PATH constant: it is only correct once
 * defineJournalConstants() has run for this journal's own code, and by construction this class
 * runs *before* that call (see CreateJournalCommand's docblock for why). pathFor() recomputes
 * the same value directly from APPLICATION_PATH instead.
 */
final class DataDirectoryProvisioner
{
    /**
     * Mirrors Bootstrap::_initcheckApplicationDirectories(): the base skeleton always created
     * for a new journal, regardless of whether a template is given.
     *
     * @var string[]
     */
    public const DIRECTORIES = ['config', 'files', 'languages', 'layout', 'public', 'tmp'];

    /**
     * Subset of DIRECTORIES whose contents are safe to blanket-copy from a template: small,
     * genuinely journal-level configuration (config/navigation.json, languages/<lang>/*.php
     * translation overrides), never per-paper or per-volume generated content.
     *
     * @var string[]
     */
    private const TEMPLATE_COPYABLE_DIRECTORIES = ['config', 'languages'];

    /**
     * Filename patterns never copied from a template journal: crypto keys, local passwords,
     * and anything that looks like a secret or token by name.
     *
     * @var string[]
     */
    private const SECRET_FILENAME_PATTERNS = [
        '/-crypto\.json$/i',
        '/^pwd\.json$/i',
        '/\.key$/i',
        '/\.pem$/i',
        '/secret/i',
        '/token/i',
    ];

    public static function pathFor(string $rvcode): string
    {
        return dirname(APPLICATION_PATH) . '/data/' . $rvcode . '/';
    }

    /**
     * @param string[] $created out-parameter, appended to as paths are created — passed by
     *                          reference so the caller still has the paths created so far even
     *                          if this method throws partway through (e.g. one mkdir() in a
     *                          series fails): the exact compensation list for rollback(), so
     *                          pre-existing paths are never reported as ours to delete
     * @return string[] paths this call actually created (directories and files), same as $created
     */
    public function provision(string $rvcode, ?string $templateRvcode, bool $dryRun, array &$created = []): array
    {
        $target = self::pathFor($rvcode);

        foreach (self::DIRECTORIES as $dir) {
            $path = $target . $dir;
            if (!is_dir($path)) {
                $created[] = $path;
                if (!$dryRun) {
                    $this->mkdir($path);
                }
            }
        }

        if ($templateRvcode !== null) {
            $templatePath = self::pathFor($templateRvcode);
            foreach (self::TEMPLATE_COPYABLE_DIRECTORIES as $dir) {
                $this->copyMissing($templatePath . $dir, $target . $dir, $dryRun, $created);
            }
        }

        return $created;
    }

    /**
     * @param string[] $created appended to by reference, see provision()
     */
    private function copyMissing(string $sourceDir, string $targetDir, bool $dryRun, array &$created): void
    {
        if (!is_dir($sourceDir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($sourceDir));
            $destination = rtrim($targetDir, '/') . '/' . ltrim($relative, '/');

            if ($item->isDir()) {
                if (!is_dir($destination)) {
                    $created[] = $destination;
                    if (!$dryRun) {
                        $this->mkdir($destination);
                    }
                }
                continue;
            }

            if ($this->isSecret($item->getFilename())) {
                continue;
            }

            if (file_exists($destination)) {
                continue;
            }

            if ($dryRun) {
                $created[] = $destination;
                continue;
            }

            if (!is_dir(dirname($destination))) {
                $this->mkdir(dirname($destination));
            }

            // Only reported as created once the copy actually succeeds, so a disk-full or
            // permission-denied failure never gets recorded as if the file were provisioned —
            // and the RuntimeException below routes the failure into JournalCreator's rollback
            // instead of letting the transaction commit over a missing file.
            if (!copy($item->getPathname(), $destination)) {
                throw new RuntimeException("Failed to copy \"{$item->getPathname()}\" to \"$destination\"");
            }
            chmod($destination, 0644);
            $created[] = $destination;
        }
    }

    private function isSecret(string $filename): bool
    {
        foreach (self::SECRET_FILENAME_PATTERNS as $pattern) {
            if (preg_match($pattern, $filename) === 1) {
                return true;
            }
        }

        return false;
    }

    private function mkdir(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0770, true) && !is_dir($path)) {
            throw new RuntimeException("Directory \"$path\" was not created");
        }
    }

    /**
     * Deletes only the paths this run actually created, deepest-first, and refuses anything
     * outside data/<rvcode>/ as a guard against a runaway deletion. A directory that is not
     * empty (something unexpected got written into it after provision()) is left in place
     * rather than forced — this only ever removes what it knows it created.
     *
     * @param string[] $createdPaths
     */
    public function rollback(string $rvcode, array $createdPaths): void
    {
        $allowedPrefix = self::pathFor($rvcode);

        $paths = $createdPaths;
        usort($paths, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($paths as $path) {
            if (!str_starts_with($path, $allowedPrefix)) {
                throw new RuntimeException("Refusing to remove path outside of $allowedPrefix: $path");
            }

            if (is_file($path) || is_link($path)) {
                unlink($path);
            } elseif (is_dir($path) && count(scandir($path) ?: ['.', '..']) <= 2) {
                rmdir($path);
            }
        }
    }

    /**
     * Removes the whole data/<rvcode>/ tree, unconditionally. Only safe to call for a journal
     * that did not exist before this run: JournalCreator's own guard (Episciences_Review::exist())
     * already established that no REVIEW row existed for this code, so nothing legitimate can
     * have been under data/<rvcode>/ either — anything found there is either this run's own
     * writes or debris from an earlier failed attempt, safe to discard either way. Never call
     * this for a --complete run resuming an existing journal: rollback() (the path-list variant)
     * is the only safe option there, since the directory may already hold real content from a
     * previously successful step.
     */
    public function rollbackAll(string $rvcode): void
    {
        $target = self::pathFor($rvcode);

        if (!is_dir($target)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($target);
    }
}
