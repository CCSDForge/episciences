<?php
declare(strict_types=1);

namespace unit\scripts;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;
use UpdateGeoIpCommand;

require_once __DIR__ . '/../../../scripts/UpdateGeoIpCommand.php';

/**
 * Unit tests for UpdateGeoIpCommand.
 *
 * Tests pure logic only — no bootstrap, no HTTP calls.
 */
class UpdateGeoIpCommandTest extends TestCase
{
    private UpdateGeoIpCommand $command;

    /** @var list<string> Temp files created during tests, cleaned up in tearDown. */
    private array $createdFiles = [];

    protected function setUp(): void
    {
        $this->command = new UpdateGeoIpCommand();

        // Define constants required by buildDestinationPath() if not already set
        if (!defined('GEO_IP_DATABASE_PATH')) {
            define('GEO_IP_DATABASE_PATH', '/tmp/geoip_test/');
        }
        if (!defined('GEO_IP_DATABASE')) {
            define('GEO_IP_DATABASE', 'GeoLite2-City.mmdb');
        }
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
        $this->assertSame('geoip:update', $this->command->getName());
    }

    public function testCommandHasForceOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasOption('force'));
        $this->assertFalse($definition->getOption('force')->acceptValue(), 'force must be a flag');
    }

    public function testCommandHasDryRunOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertFalse($definition->getOption('dry-run')->acceptValue(), 'dry-run must be a flag');
    }

    // -------------------------------------------------------------------------
    // buildDestinationPath()
    // -------------------------------------------------------------------------

    public function testBuildDestinationPath_IsAbsolutePath(): void
    {
        $path = $this->command->buildDestinationPath();
        $this->assertStringStartsWith('/', $path, 'Path must be absolute');
    }

    public function testBuildDestinationPath_EndsWithMmdb(): void
    {
        $path = $this->command->buildDestinationPath();
        $this->assertStringEndsWith('.mmdb', $path);
    }

    public function testBuildDestinationPath_ContainsDatabaseFilename(): void
    {
        $path = $this->command->buildDestinationPath();
        $this->assertStringContainsString((string) GEO_IP_DATABASE, $path);
    }

    // -------------------------------------------------------------------------
    // buildBackupPath()
    // -------------------------------------------------------------------------

    public function testBuildBackupPath_AppendsSuffix(): void
    {
        $dest   = '/data/geoip/GeoLite2-City.mmdb';
        $backup = $this->command->buildBackupPath($dest, '20260301_120000');
        $this->assertSame('/data/geoip/GeoLite2-City.mmdb.20260301_120000', $backup);
    }

    public function testBuildBackupPath_DifferentSuffixes_ProduceDifferentPaths(): void
    {
        $dest    = '/data/geoip/GeoLite2-City.mmdb';
        $backup1 = $this->command->buildBackupPath($dest, '20260301_120000');
        $backup2 = $this->command->buildBackupPath($dest, '20260301_130000');
        $this->assertNotSame($backup1, $backup2);
    }

    public function testBuildBackupPath_ContainsOriginalPath(): void
    {
        $dest   = '/data/geoip/GeoLite2-City.mmdb';
        $backup = $this->command->buildBackupPath($dest, '20260301');
        $this->assertStringStartsWith($dest, $backup);
    }

    // -------------------------------------------------------------------------
    // parseChecksumContent()
    // -------------------------------------------------------------------------

    public function testParseChecksumContent_ValidLine_ReturnsHash(): void
    {
        $content = 'abc123def456  GeoLite2-City_20240101.tar.gz';
        $this->assertSame('abc123def456', $this->command->parseChecksumContent($content));
    }

    public function testParseChecksumContent_EmptyContent_ReturnsEmpty(): void
    {
        $this->assertSame('', $this->command->parseChecksumContent(''));
    }

    public function testParseChecksumContent_HashOnly_ReturnsHash(): void
    {
        $this->assertSame('abc123', $this->command->parseChecksumContent('abc123'));
    }

    public function testParseChecksumContent_ContentWithNewline_StripsNewline(): void
    {
        $content = "abc123def456  GeoLite2-City_20240101.tar.gz\n";
        $this->assertSame('abc123def456', $this->command->parseChecksumContent($content));
    }

    // -------------------------------------------------------------------------
    // validateFileChecksum()
    // -------------------------------------------------------------------------

    public function testValidateFileChecksum_CorrectHash_ReturnsTrue(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'geoip_test_');
        $this->assertNotFalse($tmpFile);
        $this->createdFiles[] = $tmpFile;

        file_put_contents($tmpFile, 'test content for checksum');
        $expectedHash = hash_file('sha256', $tmpFile);
        $this->assertNotFalse($expectedHash);

        $this->assertTrue($this->command->validateFileChecksum($tmpFile, (string) $expectedHash));
    }

    public function testValidateFileChecksum_WrongHash_ReturnsFalse(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'geoip_test_');
        $this->assertNotFalse($tmpFile);
        $this->createdFiles[] = $tmpFile;

        file_put_contents($tmpFile, 'test content');

        $this->assertFalse($this->command->validateFileChecksum($tmpFile, 'wronghash0000000000000000000000000000000000000000000000000000000000'));
    }

    public function testValidateFileChecksum_EmptyHash_ReturnsFalse(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'geoip_test_');
        $this->assertNotFalse($tmpFile);
        $this->createdFiles[] = $tmpFile;

        file_put_contents($tmpFile, 'test content');

        $this->assertFalse($this->command->validateFileChecksum($tmpFile, ''));
    }

    public function testValidateFileChecksum_NonexistentFile_ReturnsFalse(): void
    {
        $this->assertFalse($this->command->validateFileChecksum('/nonexistent/path/file.mmdb', 'abc123'));
    }

    // -------------------------------------------------------------------------
    // parseLastModifiedHeader()
    // -------------------------------------------------------------------------

    public function testParseLastModifiedHeader_ValidRfc1123_ReturnsTimestamp(): void
    {
        $ts = $this->command->parseLastModifiedHeader('Tue, 05 Mar 2024 12:00:00 GMT');
        $this->assertIsInt($ts);
        $this->assertGreaterThan(0, $ts);
        $this->assertSame('2024-03-05', date('Y-m-d', $ts));
    }

    public function testParseLastModifiedHeader_EmptyString_ReturnsNull(): void
    {
        $this->assertNull($this->command->parseLastModifiedHeader(''));
    }

    public function testParseLastModifiedHeader_InvalidValue_ReturnsNull(): void
    {
        $this->assertNull($this->command->parseLastModifiedHeader('not-a-date'));
    }

    public function testParseLastModifiedHeader_DifferentDates_ProduceDifferentTimestamps(): void
    {
        $ts1 = $this->command->parseLastModifiedHeader('Mon, 01 Jan 2024 00:00:00 GMT');
        $ts2 = $this->command->parseLastModifiedHeader('Wed, 01 May 2024 00:00:00 GMT');
        $this->assertIsInt($ts1);
        $this->assertIsInt($ts2);
        $this->assertGreaterThan($ts1, $ts2);
    }
}
