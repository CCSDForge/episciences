<?php

namespace unit\library\Episciences\Journal\Provisioning;

use Episciences\Journal\Provisioning\DataDirectoryProvisioner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * provision() itself is verified end-to-end (it touches the real filesystem under data/<rvcode>
 * and is exercised against a real template journal in docs/journal-provisioning.md). This
 * covers pathFor() and rollback()'s safety guards, using a throwaway rvcode under data/ that
 * this test creates and removes itself.
 */
class DataDirectoryProvisionerTest extends TestCase
{
    private const FIXTURE_RVCODE = '__test_rollback_fixture__';

    protected function tearDown(): void
    {
        $this->removeDirRecursive(DataDirectoryProvisioner::pathFor(self::FIXTURE_RVCODE));
        parent::tearDown();
    }

    public function testPathForIsUnderTheDataDirectory(): void
    {
        $expected = dirname(APPLICATION_PATH) . '/data/some-code/';
        $this->assertSame($expected, DataDirectoryProvisioner::pathFor('some-code'));
    }

    public function testRollbackRefusesAPathOutsideTheJournalsDataDirectory(): void
    {
        $provisioner = new DataDirectoryProvisioner();

        $this->expectException(RuntimeException::class);
        $provisioner->rollback('some-code', ['/etc/passwd']);
    }

    public function testRollbackOnlyRemovesThePathsItWasGiven(): void
    {
        $base = DataDirectoryProvisioner::pathFor(self::FIXTURE_RVCODE);
        $createdDir = $base . 'config';
        $createdFile = $createdDir . '/navigation.json';
        $untrackedFile = $base . 'config/untracked.txt';

        mkdir($createdDir, 0770, true);
        file_put_contents($createdFile, '{}');
        file_put_contents($untrackedFile, 'not created by this run');

        $provisioner = new DataDirectoryProvisioner();
        $provisioner->rollback(self::FIXTURE_RVCODE, [$createdDir, $createdFile]);

        $this->assertFileDoesNotExist($createdFile, 'A path passed to rollback() must be removed');
        $this->assertFileExists($untrackedFile, 'rollback() must never remove a file it was not told it created');
        $this->assertDirectoryExists($createdDir, 'A non-empty directory must be left in place, not force-removed');
    }

    public function testRollbackRemovesAnEmptyDirectoryItCreated(): void
    {
        $base = DataDirectoryProvisioner::pathFor(self::FIXTURE_RVCODE);
        $emptyDir = $base . 'tmp';
        mkdir($emptyDir, 0770, true);

        $provisioner = new DataDirectoryProvisioner();
        $provisioner->rollback(self::FIXTURE_RVCODE, [$emptyDir, $base]);

        $this->assertDirectoryDoesNotExist($emptyDir);
    }

    private function removeDirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDirRecursive($path) : unlink($path);
        }

        rmdir($dir);
    }
}
