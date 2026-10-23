<?php

declare(strict_types=1);

namespace Episciences\Paper\GraphicalAbstract;

use Episciences\AppRegistry;
use Episciences_Paper;
use RuntimeException;
use Throwable;
use Zend_Db;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Statement_Exception;
use Zend_Db_Table_Abstract;

/**
 * Reads and writes a paper's illustration (graphical abstract): its three keys in
 * PAPERS.DOCUMENT and its image file in the journal's public documents directory.
 */
final class GraphicalAbstractRepository
{
    public const JSON_PATH_FILE = Episciences_Paper::JSON_PATH_ABS_FILE;
    public const JSON_PATH_ALT = '$.database.current.graphical_abstract_alt';
    public const JSON_PATH_LICENSE = '$.database.current.graphical_abstract_license';

    private const DOCUMENT_COLUMN = Episciences_Paper::JSON_DOCUMENT_COLUMN;

    /** Public URL prefix of REVIEW_PATH/public/documents/ */
    private const PUBLIC_URL_PREFIX = '/public/documents/';

    public static function find(int $docId): ?GraphicalAbstract
    {
        $db = self::db();

        $sql = sprintf(
            'SELECT %s, %s, %s FROM %s WHERE DOCID = ?',
            self::extract($db, self::JSON_PATH_FILE),
            self::extract($db, self::JSON_PATH_ALT),
            self::extract($db, self::JSON_PATH_LICENSE),
            T_PAPERS
        );

        try {
            $row = $db->query($sql, [$docId])->fetch(Zend_Db::FETCH_NUM);
        } catch (Zend_Db_Statement_Exception) {
            return null;
        }

        if (!is_array($row)) {
            return null;
        }

        $file = self::normalize($row[0] ?? null);

        if ($file === null) {
            return null;
        }

        return new GraphicalAbstract(
            $file,
            self::normalize($row[1] ?? null),
            self::normalize($row[2] ?? null)
        );
    }

    /**
     * Stores the three keys at once. An empty alt or license removes the key.
     *
     * @throws Zend_Db_Statement_Exception
     */
    public static function save(int $docId, GraphicalAbstract $graphicalAbstract): void
    {
        self::writeKeys($docId, basename($graphicalAbstract->file), $graphicalAbstract->alt, $graphicalAbstract->license);
    }

    /**
     * Removes the three keys and the image file.
     *
     * @throws Zend_Db_Statement_Exception
     */
    public static function delete(int $docId, ?string $reviewPath = null): void
    {
        $graphicalAbstract = self::find($docId);

        self::writeKeys($docId, null, null, null);

        if ($graphicalAbstract !== null) {
            self::removeFile($docId, $graphicalAbstract->file, $reviewPath);
        }
    }

    /**
     * Carries the illustration of a paper version over to a newer version.
     *
     * Does nothing if the target version already has its own illustration. A new version
     * cloned from the previous Episciences_Paper inherits the stored keys but not the file,
     * so the target is only considered as having an illustration if its file exists.
     * If the source version has no illustration on disk, the keys inherited by the target are removed.
     *
     * Never throws: a failure is logged and must not prevent the version from being created.
     *
     * @param string|null $reviewPath journal data directory, defaults to REVIEW_PATH (required from CLI)
     * @return bool true if the illustration was copied
     */
    public static function copyToVersion(int $fromDocId, int $toDocId, ?string $reviewPath = null): bool
    {
        if ($fromDocId === $toDocId) {
            return false;
        }

        try {
            $source = self::find($fromDocId);
            $sourcePath = $source !== null ? self::filePath($fromDocId, $source->file, $reviewPath) : null;
            $target = self::find($toDocId);
            $targetHasFile = $target !== null && is_file(self::filePath($toDocId, $target->file, $reviewPath));

            if ($targetHasFile) {
                return false;
            }

            if ($source === null || $sourcePath === null || !is_file($sourcePath)) {
                // a clone inherits the stored keys: without a file to carry over, they would point to nothing
                if ($target !== null) {
                    self::writeKeys($toDocId, null, null, null);
                }

                return false;
            }

            $targetPath = self::ensureDocumentsDir($toDocId, $reviewPath) . basename($source->file);

            if (!copy($sourcePath, $targetPath)) {
                throw new RuntimeException(sprintf('Unable to copy "%s" to "%s"', $sourcePath, $targetPath));
            }

            chmod($targetPath, 0644);
            self::save($toDocId, $source);
        } catch (Throwable $e) {
            AppRegistry::getMonoLogger()?->warning(sprintf(
                'Failed to copy the graphical abstract of document #%d to document #%d: %s',
                $fromDocId,
                $toDocId,
                $e->getMessage()
            ));
            return false;
        }

        return true;
    }

    /**
     * Removes an image file of the paper, if it exists.
     *
     * @param string|null $reviewPath journal data directory, defaults to REVIEW_PATH
     */
    public static function removeFile(int $docId, string $file, ?string $reviewPath = null): void
    {
        $path = self::filePath($docId, $file, $reviewPath);

        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * Absolute path of the paper's public documents directory, with a trailing slash.
     *
     * @param string|null $reviewPath journal data directory, defaults to REVIEW_PATH
     */
    public static function documentsDir(int $docId, ?string $reviewPath = null): string
    {
        $reviewPath ??= defined('REVIEW_PATH') ? (string)REVIEW_PATH : '';

        if ($reviewPath === '') {
            throw new RuntimeException('The journal data directory is not defined');
        }

        return rtrim($reviewPath, '/') . '/public/documents/' . $docId . '/';
    }

    public static function filePath(int $docId, string $file, ?string $reviewPath = null): string
    {
        return self::documentsDir($docId, $reviewPath) . basename($file);
    }

    public static function publicUrl(int $docId, string $file): string
    {
        return self::PUBLIC_URL_PREFIX . $docId . '/' . rawurlencode(basename($file));
    }

    /**
     * Public URL with the modification time of the file, so that a replaced image of the same
     * type (same file name) is not served from a browser or proxy cache.
     *
     * @param string|null $reviewPath journal data directory, defaults to REVIEW_PATH
     */
    public static function versionedPublicUrl(int $docId, string $file, ?string $reviewPath = null): string
    {
        $url = self::publicUrl($docId, $file);

        try {
            $mtime = @filemtime(self::filePath($docId, $file, $reviewPath));
        } catch (RuntimeException) {
            $mtime = false;
        }

        return $mtime !== false ? $url . '?v=' . $mtime : $url;
    }

    /**
     * Creates the paper's public documents directory if needed.
     *
     * @return string the directory, with a trailing slash
     */
    public static function ensureDocumentsDir(int $docId, ?string $reviewPath = null): string
    {
        $dir = self::documentsDir($docId, $reviewPath);

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        return $dir;
    }

    /**
     * JSON_MERGE_PATCH creates the missing intermediate objects and removes a key set to null,
     * unlike JSON_SET, which silently does nothing when database.current is missing.
     *
     * @throws Zend_Db_Statement_Exception
     */
    private static function writeKeys(int $docId, ?string $file, ?string $alt, ?string $license): void
    {
        $db = self::db();

        $sql = sprintf(
            "UPDATE %s SET %s = JSON_MERGE_PATCH(COALESCE(%s, JSON_OBJECT()), JSON_OBJECT('database', JSON_OBJECT('current', JSON_OBJECT(%s, ?, %s, ?, %s, ?)))) WHERE DOCID = ?",
            T_PAPERS,
            self::DOCUMENT_COLUMN,
            self::DOCUMENT_COLUMN,
            $db->quote(self::keyName(self::JSON_PATH_FILE)),
            $db->quote(self::keyName(self::JSON_PATH_ALT)),
            $db->quote(self::keyName(self::JSON_PATH_LICENSE))
        );

        $db->query($sql, [self::emptyToNull($file), self::emptyToNull($alt), self::emptyToNull($license), $docId]);
    }

    private static function extract(Zend_Db_Adapter_Abstract $db, string $jsonPath): string
    {
        return sprintf('JSON_UNQUOTE(JSON_EXTRACT(`%s`, %s))', self::DOCUMENT_COLUMN, $db->quote($jsonPath));
    }

    private static function keyName(string $jsonPath): string
    {
        return substr($jsonPath, (int)strrpos($jsonPath, '.') + 1);
    }

    /**
     * JSON_UNQUOTE(JSON_EXTRACT()) returns the string "null" (not SQL NULL) when the JSON value itself is null.
     */
    private static function normalize(mixed $value): ?string
    {
        if (!is_string($value) || $value === 'null') {
            return null;
        }

        return self::emptyToNull($value);
    }

    private static function emptyToNull(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value !== '' ? $value : null;
    }

    private static function db(): Zend_Db_Adapter_Abstract
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        if (!$db instanceof Zend_Db_Adapter_Abstract) {
            throw new RuntimeException('No default database adapter');
        }

        return $db;
    }
}
