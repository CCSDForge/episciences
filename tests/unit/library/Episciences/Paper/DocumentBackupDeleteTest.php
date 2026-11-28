<?php

declare(strict_types=1);

namespace unit\library\Episciences\Paper;

use Episciences_Paper_DocumentBackup;
use PHPUnit\Framework\TestCase;

/**
 * Episciences_Paper_DocumentBackup::deleteDocumentBackupFile() and deleteForPaper(): the backup copy is keyed
 * on the DOCID only and is removed when a new version is saved on the same DOCID.
 */
final class DocumentBackupDeleteTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/document-backup-test-' . bin2hex(random_bytes(4)) . '/';
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->dir)) {
            return;
        }

        chmod($this->dir, 0700);

        foreach (glob($this->dir . '*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->dir);
    }

    private function makeBackup(): Episciences_Paper_DocumentBackup
    {
        $backup = new Episciences_Paper_DocumentBackup(4242, 'test');
        $backup->setPath('test', $this->dir)->setPathFileName();

        return $backup;
    }

    public function testDeletesTheBackupCopy(): void
    {
        $backup = $this->makeBackup();
        self::assertTrue($backup->saveDocumentBackupFile('%PDF-1.4 previous version'));

        self::assertTrue($backup->deleteDocumentBackupFile());
        self::assertFalse($backup->hasDocumentBackupFile());
    }

    public function testMissingBackupCopyCountsAsDeleted(): void
    {
        self::assertTrue($this->makeBackup()->deleteDocumentBackupFile());
    }

    public function testFailedDeletionIsReportedWithoutAPhpWarning(): void
    {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            self::markTestSkipped('root can delete a file from a read-only directory.');
        }

        $backup = $this->makeBackup();
        self::assertTrue($backup->saveDocumentBackupFile('%PDF-1.4 previous version'));
        chmod($this->dir, 0500);

        $warnings = [];
        // Like the application handlers, a silenced error is not reported
        set_error_handler(static function (int $errno, string $message) use (&$warnings): bool {
            if ((error_reporting() & $errno) !== 0) {
                $warnings[] = $message;
            }
            return true;
        });

        try {
            $deleted = $backup->deleteDocumentBackupFile();
        } finally {
            restore_error_handler();
        }

        self::assertFalse($deleted);
        self::assertSame([], $warnings);
        self::assertTrue($backup->hasDocumentBackupFile());
    }

    public function testUnknownJournalIsReportedAsNotDeleted(): void
    {
        self::assertFalse(Episciences_Paper_DocumentBackup::deleteForPaper(4242, PHP_INT_MAX));
    }
}
