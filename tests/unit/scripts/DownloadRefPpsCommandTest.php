<?php
declare(strict_types=1);

namespace unit\scripts;

use DownloadRefPpsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/DownloadRefPpsCommand.php';

/**
 * Unit tests for DownloadRefPpsCommand.
 *
 * Tests pure logic only — no HTTP calls, no filesystem writes.
 */
class DownloadRefPpsCommandTest extends TestCase
{
    private DownloadRefPpsCommand $command;

    /** @var list<string> */
    private array $createdFiles = [];

    protected function setUp(): void
    {
        $this->command = new DownloadRefPpsCommand();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->createdFiles = [];
    }

    // -------------------------------------------------------------------------
    // Command metadata
    // -------------------------------------------------------------------------

    public function testCommandName(): void
    {
        $this->assertSame('download:ref-pps', $this->command->getName());
    }

    public function testCommandHasForceOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasOption('force'));
        $this->assertFalse($definition->getOption('force')->acceptValue(), 'force must be a flag');
    }

    public function testCommandHasForceShortcut(): void
    {
        $option = $this->command->getDefinition()->getOption('force');
        $this->assertSame('f', $option->getShortcut());
    }

    // -------------------------------------------------------------------------
    // isRateLimited()
    // -------------------------------------------------------------------------

    public function testIsRateLimited_RecentDownload_ReturnsTrue(): void
    {
        $this->assertTrue($this->command->isRateLimited(24.0));
    }

    public function testIsRateLimited_OldDownload_ReturnsFalse(): void
    {
        $this->assertFalse($this->command->isRateLimited(72.0));
    }

    public function testIsRateLimited_ExactlyAtLimit_ReturnsFalse(): void
    {
        // 48.0 is not < 48: should allow download
        $this->assertFalse($this->command->isRateLimited(48.0));
    }

    public function testIsRateLimited_JustUnderLimit_ReturnsTrue(): void
    {
        $this->assertTrue($this->command->isRateLimited(47.9));
    }

    public function testIsRateLimited_JustOverLimit_ReturnsFalse(): void
    {
        $this->assertFalse($this->command->isRateLimited(48.1));
    }

    public function testIsRateLimited_Zero_ReturnsTrue(): void
    {
        $this->assertTrue($this->command->isRateLimited(0.0));
    }

    // -------------------------------------------------------------------------
    // hoursSince()
    // -------------------------------------------------------------------------

    public function testHoursSince_OneHourAgo_ReturnsApproxOne(): void
    {
        $oneHourAgo = time() - 3600;
        $hours      = $this->command->hoursSince($oneHourAgo);
        $this->assertEqualsWithDelta(1.0, $hours, 0.01);
    }

    public function testHoursSince_FortyEightHoursAgo_ReturnsApproxFortyEight(): void
    {
        $fortyEightHoursAgo = time() - (3600 * 48);
        $hours              = $this->command->hoursSince($fortyEightHoursAgo);
        $this->assertEqualsWithDelta(48.0, $hours, 0.01);
    }

    public function testHoursSince_FutureTimestamp_ReturnsNegative(): void
    {
        $futureTimestamp = time() + 3600;
        $this->assertLessThan(0.0, $this->command->hoursSince($futureTimestamp));
    }

    // -------------------------------------------------------------------------
    // buildBackupPath()
    // -------------------------------------------------------------------------

    public function testBuildBackupPath_ContainsDirectory(): void
    {
        $path = $this->command->buildBackupPath('/data/ref_pps', mktime(12, 0, 0, 3, 15, 2026));
        $this->assertStringStartsWith('/data/ref_pps/', $path);
    }

    public function testBuildBackupPath_ContainsDateTimestamp(): void
    {
        $mtime = mktime(14, 30, 0, 6, 1, 2025);
        $path  = $this->command->buildBackupPath('/data/ref_pps', $mtime);
        $this->assertStringContainsString('20250601_143000', $path);
    }

    public function testBuildBackupPath_EndsWithCsvExtension(): void
    {
        $path = $this->command->buildBackupPath('/data/ref_pps', mktime(0, 0, 0, 1, 1, 2025));
        $this->assertStringEndsWith('.csv', $path);
    }

    public function testBuildBackupPath_StartsWithPpsPrefix(): void
    {
        $path     = $this->command->buildBackupPath('/data/ref_pps', mktime(0, 0, 0, 1, 1, 2025));
        $filename = basename($path);
        $this->assertStringStartsWith('pps-', $filename);
    }

    public function testBuildBackupPath_DifferentMtimes_ProduceDifferentPaths(): void
    {
        $path1 = $this->command->buildBackupPath('/data', mktime(12, 0, 0, 1, 1, 2025));
        $path2 = $this->command->buildBackupPath('/data', mktime(13, 0, 0, 1, 1, 2025));
        $this->assertNotSame($path1, $path2);
    }

    public function testBuildBackupPath_SameMtime_ProducesSamePath(): void
    {
        $mtime = mktime(8, 0, 0, 4, 15, 2026);
        $this->assertSame(
            $this->command->buildBackupPath('/data', $mtime),
            $this->command->buildBackupPath('/data', $mtime)
        );
    }

    // -------------------------------------------------------------------------
    // Rate-limit integration: isRateLimited + hoursSince
    // -------------------------------------------------------------------------

    public function testIntegration_RecentMtime_IsRateLimited(): void
    {
        $twelveHoursAgo = time() - (3600 * 12);
        $hours          = $this->command->hoursSince($twelveHoursAgo);
        $this->assertTrue($this->command->isRateLimited($hours));
    }

    public function testIntegration_OldMtime_IsNotRateLimited(): void
    {
        $threeDaysAgo = time() - (3600 * 72);
        $hours        = $this->command->hoursSince($threeDaysAgo);
        $this->assertFalse($this->command->isRateLimited($hours));
    }
}
