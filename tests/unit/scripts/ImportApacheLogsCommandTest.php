<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Monolog\Logger;
use Monolog\Handler\NullHandler;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/../../../scripts/ImportApacheLogsCommand.php';

class ImportApacheLogsCommandTest extends TestCase
{
    private ImportApacheLogsCommand $command;
    private CommandTester $commandTester;
    private string $tempLogDir;

    protected function setUp(): void
    {
        $this->tempLogDir = sys_get_temp_dir() . '/epi_test_logs_' . uniqid();
        mkdir($this->tempLogDir, 0755, true);

        $application = new Application();
        $application->add(new ImportApacheLogsCommand());
        $this->command = $application->find('stats:import-logs');
        $this->commandTester = new CommandTester($this->command);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempLogDir);
    }

    // -------------------------------------------------------------------------
    // Option validation
    // -------------------------------------------------------------------------

    public function testFailsWithNoJournalOption(): void
    {
        $this->commandTester->execute([]);
        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('--rvcode', $this->commandTester->getDisplay());
    }

    public function testFailsWhenBothRvcodeAndAllProvided(): void
    {
        $this->commandTester->execute(['--rvcode' => 'mbj', '--all' => true]);
        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('mutually exclusive', $this->commandTester->getDisplay());
    }

    public function testFailsWithInvalidDate(): void
    {
        $this->commandTester->execute(['--rvcode' => 'test', '--date' => 'not-a-date']);
        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Invalid date format', $this->commandTester->getDisplay());
    }

    public function testFailsWhenStartDateAfterEndDate(): void
    {
        $this->commandTester->execute([
            '--rvcode'     => 'test',
            '--start-date' => '2023-01-10',
            '--end-date'   => '2023-01-01',
        ]);
        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('before or equal to', $this->commandTester->getDisplay());
    }

    public function testFailsWithMultipleDateOptions(): void
    {
        $this->commandTester->execute([
            '--rvcode' => 'test',
            '--date'   => '2023-01-01',
            '--month'  => '2023-01',
        ]);
        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('only one of', $this->commandTester->getDisplay());
    }

    public function testFailsWithYearAndMonthTogether(): void
    {
        $this->commandTester->execute([
            '--rvcode' => 'test',
            '--year'   => '2023',
            '--month'  => '2023-01',
        ]);
        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('only one of', $this->commandTester->getDisplay());
    }

    public function testFailsWithInvalidMonthFormat(): void
    {
        $this->commandTester->execute(['--rvcode' => 'test', '--month' => '2023/01']);
        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Invalid --month format', $this->commandTester->getDisplay());
    }

    public function testFailsWithInvalidYearFormat(): void
    {
        $this->commandTester->execute(['--rvcode' => 'test', '--year' => '23']);
        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Invalid --year format', $this->commandTester->getDisplay());
    }

    public function testFailsWithOnlyStartDate(): void
    {
        $this->commandTester->execute(['--rvcode' => 'test', '--start-date' => '2023-01-01']);
        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('both be provided', $this->commandTester->getDisplay());
    }

    // -------------------------------------------------------------------------
    // Date range helpers (via reflection)
    // -------------------------------------------------------------------------

    public function testMonthRangeJanuary(): void
    {
        $dates = $this->invokePrivate('monthRange', ['2023-01']);
        $this->assertCount(31, $dates);
        $this->assertSame('2023-01-01', $dates[0]->format('Y-m-d'));
        $this->assertSame('2023-01-31', $dates[30]->format('Y-m-d'));
    }

    public function testMonthRangeFebruaryNonLeap(): void
    {
        $dates = $this->invokePrivate('monthRange', ['2023-02']);
        $this->assertCount(28, $dates);
        $this->assertSame('2023-02-28', $dates[27]->format('Y-m-d'));
    }

    public function testMonthRangeFebruaryLeap(): void
    {
        $dates = $this->invokePrivate('monthRange', ['2024-02']);
        $this->assertCount(29, $dates);
        $this->assertSame('2024-02-29', $dates[28]->format('Y-m-d'));
    }

    public function testYearRangeNonLeap(): void
    {
        $dates = $this->invokePrivate('yearRange', ['2023']);
        $this->assertCount(365, $dates);
        $this->assertSame('2023-01-01', $dates[0]->format('Y-m-d'));
        $this->assertSame('2023-12-31', $dates[364]->format('Y-m-d'));
    }

    public function testYearRangeLeap(): void
    {
        $dates = $this->invokePrivate('yearRange', ['2024']);
        $this->assertCount(366, $dates);
        $this->assertSame('2024-12-31', $dates[365]->format('Y-m-d'));
    }

    public function testDateRangeSingleDay(): void
    {
        $dates = $this->invokePrivate('dateRange', ['2023-03-15', '2023-03-15']);
        $this->assertCount(1, $dates);
        $this->assertSame('2023-03-15', $dates[0]->format('Y-m-d'));
    }

    public function testDateRangeMultipleDays(): void
    {
        $dates = $this->invokePrivate('dateRange', ['2023-01-01', '2023-01-03']);
        $this->assertCount(3, $dates);
        $this->assertSame('2023-01-02', $dates[1]->format('Y-m-d'));
    }

    // -------------------------------------------------------------------------
    // User-agent sanitization
    // -------------------------------------------------------------------------

    public function testSanitizeUserAgentNormal(): void
    {
        $result = $this->invokePrivate('sanitizeUserAgent', ['Mozilla/5.0 (Windows NT 10.0)']);
        $this->assertSame('Mozilla/5.0 (Windows NT 10.0)', $result);
    }

    public function testSanitizeUserAgentStripsControlChars(): void
    {
        $result = $this->invokePrivate('sanitizeUserAgent', ["Mozilla\x00\x08\x1F/5.0"]);
        $this->assertSame('Mozilla/5.0', $result);
    }

    public function testSanitizeUserAgentTruncatesAt2000(): void
    {
        $result = $this->invokePrivate('sanitizeUserAgent', [str_repeat('A', 2100)]);
        $this->assertSame(2000, mb_strlen($result));
    }

    // -------------------------------------------------------------------------
    // Log file path resolution
    // -------------------------------------------------------------------------

    public function testBuildLogFilePathReturnsNullWhenMissing(): void
    {
        $command = $this->commandWithLogsPath($this->tempLogDir);
        $result  = $this->invokePrivateOn($command, 'buildLogFilePath', ['test.episciences.org', new DateTime('2023-01-01')]);
        $this->assertNull($result);
    }

    public function testBuildLogFilePathFindsPlainFile(): void
    {
        $command  = $this->commandWithLogsPath($this->tempLogDir);
        $logPath  = $this->tempLogDir . '/test.episciences.org/2023/01/01-test.episciences.org.access_log';
        $this->mkfile($logPath, 'x');

        $result = $this->invokePrivateOn($command, 'buildLogFilePath', ['test.episciences.org', new DateTime('2023-01-01')]);
        $this->assertSame($logPath, $result);
    }

    public function testBuildLogFilePathFindsGzFile(): void
    {
        $command = $this->commandWithLogsPath($this->tempLogDir);
        $gzPath  = $this->tempLogDir . '/test.episciences.org/2023/01/01-test.episciences.org.access_log.gz';
        mkdir(dirname($gzPath), 0755, true);
        $gz = gzopen($gzPath, 'w');
        gzwrite($gz, 'x');
        gzclose($gz);

        $result = $this->invokePrivateOn($command, 'buildLogFilePath', ['test.episciences.org', new DateTime('2023-01-01')]);
        $this->assertSame($gzPath, $result);
    }

    public function testBuildLogFilePathPrefersPlainOverGz(): void
    {
        $command = $this->commandWithLogsPath($this->tempLogDir);
        $logPath = $this->tempLogDir . '/test.episciences.org/2023/01/01-test.episciences.org.access_log';
        $gzPath  = $logPath . '.gz';
        $this->mkfile($logPath, 'x');
        // Directory already created by mkfile(); no need to mkdir again.
        $gz = gzopen($gzPath, 'w');
        gzwrite($gz, 'x');
        gzclose($gz);

        $result = $this->invokePrivateOn($command, 'buildLogFilePath', ['test.episciences.org', new DateTime('2023-01-01')]);
        $this->assertSame($logPath, $result);
    }

    // -------------------------------------------------------------------------
    // Pattern matching
    // -------------------------------------------------------------------------

    /** @dataProvider provideMatchingLines */
    public function testMatchAccessPatternMatches(string $line, string $expectedType, int $expectedDocId): void
    {
        $result = $this->invokePrivate('matchAccessPattern', [$line]);
        $this->assertNotNull($result);
        $this->assertSame($expectedType, $result['accessType']);
        $this->assertSame((string) $expectedDocId, $result['docId']);
    }

    /** @return array<string, array{string, string, int}> */
    public static function provideMatchingLines(): array
    {
        return [
            'notice'   => ['192.168.1.1 - - [01/Jan/2023:10:00:00 +0100] "GET /articles/123 HTTP/1.1" 200 1', 'notice', 123],
            'download' => ['192.168.1.1 - - [01/Jan/2023:10:00:00 +0100] "GET /articles/456/download HTTP/1.1" 200 1', 'file', 456],
            'preview'  => ['192.168.1.1 - - [01/Jan/2023:10:00:00 +0100] "GET /articles/789/preview HTTP/1.1" 200 1', 'file', 789],
        ];
    }

    /** @dataProvider provideNonMatchingLines */
    public function testMatchAccessPatternIgnores(string $line): void
    {
        $result = $this->invokePrivate('matchAccessPattern', [$line]);
        $this->assertNull($result);
    }

    /** @return array<string, array{string}> */
    public static function provideNonMatchingLines(): array
    {
        return [
            'other path'   => ['192.168.1.1 - - [01/Jan/2023:10:00:00 +0100] "GET /other/path HTTP/1.1" 200 1'],
            'POST request' => ['192.168.1.1 - - [01/Jan/2023:10:00:00 +0100] "POST /articles/123 HTTP/1.1" 200 1'],
            'empty line'   => [''],
        ];
    }

    // -------------------------------------------------------------------------
    // IP and User-Agent extraction
    // -------------------------------------------------------------------------

    public function testExtractIPValid(): void
    {
        $line = '192.168.1.100 - - [01/Jan/2023:10:30:45 +0100] "GET /articles/1 HTTP/1.1" 200 0';
        $ip   = $this->invokePrivate('extractIP', [$line]);
        $this->assertSame('192.168.1.100', $ip);
    }

    public function testExtractIPReturnsEmptyOnInvalidLine(): void
    {
        $ip = $this->invokePrivate('extractIP', ['not-an-ip line']);
        $this->assertSame('', $ip);
    }

    public function testExtractUserAgentCombinedFormat(): void
    {
        $line = '1.2.3.4 - - [01/Jan/2023:10:00:00 +0100] "GET /articles/1 HTTP/1.1" 200 0 "http://ref.com" "Mozilla/5.0 (Test)"';
        $ua   = $this->invokePrivate('extractUserAgent', [$line]);
        $this->assertSame('Mozilla/5.0 (Test)', $ua);
    }

    public function testExtractUserAgentReturnsEmptyWhenAbsent(): void
    {
        $line = '1.2.3.4 - - [01/Jan/2023:10:00:00 +0100] "GET /articles/1 HTTP/1.1" 200 0';
        $ua   = $this->invokePrivate('extractUserAgent', [$line]);
        $this->assertSame('', $ua);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @param mixed[] $args */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        return $this->invokePrivateOn(new ImportApacheLogsCommand(), $method, $args);
    }

    /** @param mixed[] $args */
    private function invokePrivateOn(object $obj, string $method, array $args = []): mixed
    {
        $m = (new ReflectionClass($obj))->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($obj, $args);
    }

    private function commandWithLogsPath(string $path): ImportApacheLogsCommand
    {
        $command = new ImportApacheLogsCommand();
        $prop    = (new ReflectionClass($command))->getProperty('logsBasePath');
        $prop->setAccessible(true);
        $prop->setValue($command, $path);
        return $command;
    }

    private function mkfile(string $path, string $content): void
    {
        mkdir(dirname($path), 0755, true);
        file_put_contents($path, $content);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff((array) scandir($dir), ['.', '..']) as $file) {
            $p = "$dir/$file";
            is_dir($p) ? $this->removeDirectory($p) : unlink($p);
        }
        rmdir($dir);
    }
}
