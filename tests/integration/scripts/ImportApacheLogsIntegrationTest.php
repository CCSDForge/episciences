<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Monolog\Logger;
use Monolog\Handler\NullHandler;

require_once __DIR__ . '/../../../scripts/ImportApacheLogsCommand.php';

/**
 * Integration tests for ImportApacheLogsCommand log-file parsing.
 *
 * These tests exercise the parsing pipeline end-to-end (file → accesses array)
 * without touching the database.
 */
class ImportApacheLogsIntegrationTest extends TestCase
{
    private ImportApacheLogsCommand $command;
    private string $tempLogDir;
    private Logger $nullLogger;

    protected function setUp(): void
    {
        $this->tempLogDir = sys_get_temp_dir() . '/epi_integration_test_' . uniqid();
        mkdir($this->tempLogDir, 0755, true);

        $this->command = new ImportApacheLogsCommand();
        $prop = (new ReflectionClass($this->command))->getProperty('logsBasePath');
        $prop->setAccessible(true);
        $prop->setValue($this->command, $this->tempLogDir);

        $this->nullLogger = new Logger('test');
        $this->nullLogger->pushHandler(new NullHandler());
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempLogDir);
    }

    // -------------------------------------------------------------------------
    // collectArticleAccesses
    // -------------------------------------------------------------------------

    public function testParsesNoticeDownloadAndPreview(): void
    {
        $logPath = $this->writeLog([
            '192.168.1.1 - - [01/Jan/2023:10:30:00 +0100] "GET /articles/123 HTTP/1.1" 200 1 "-" "UA-A"',
            '192.168.1.2 - - [01/Jan/2023:10:31:00 +0100] "GET /articles/456/download HTTP/1.1" 200 1 "-" "UA-B"',
            '192.168.1.3 - - [01/Jan/2023:10:32:00 +0100] "GET /articles/789/preview HTTP/1.1" 200 1 "-" "UA-C"',
            '192.168.1.4 - - [01/Jan/2023:10:33:00 +0100] "GET /other/path HTTP/1.1" 200 1 "-" "UA-D"',
        ]);

        $results = $this->collect($logPath, '2023-01-01');

        $this->assertCount(3, $results);
        $this->assertSame(123,      $results[0]['doc_id']);
        $this->assertSame('notice', $results[0]['access_type']);
        $this->assertSame(456,      $results[1]['doc_id']);
        $this->assertSame('file',   $results[1]['access_type']);
        $this->assertSame(789,      $results[2]['doc_id']);
        $this->assertSame('file',   $results[2]['access_type']);
    }

    public function testGzippedFileTransparentlyRead(): void
    {
        $gzPath = $this->tempLogDir . '/test.access_log.gz';
        $gz     = gzopen($gzPath, 'w');
        gzwrite($gz, '192.168.1.1 - - [01/Jan/2023:10:30:00 +0100] "GET /articles/123 HTTP/1.1" 200 1 "-" "UA"' . "\n");
        gzclose($gz);

        $results = $this->collect($gzPath, '2023-01-01');

        $this->assertCount(1, $results);
        $this->assertSame(123, $results[0]['doc_id']);
    }

    public function testFiltersEntriesOutsideTargetDate(): void
    {
        $logPath = $this->writeLog([
            '192.168.1.1 - - [31/Dec/2022:23:59:59 +0100] "GET /articles/111 HTTP/1.1" 200 1 "-" "UA"',
            '192.168.1.2 - - [01/Jan/2023:00:00:00 +0100] "GET /articles/222 HTTP/1.1" 200 1 "-" "UA"',
            '192.168.1.3 - - [01/Jan/2023:12:00:00 +0100] "GET /articles/333 HTTP/1.1" 200 1 "-" "UA"',
            '192.168.1.4 - - [01/Jan/2023:23:59:59 +0100] "GET /articles/444 HTTP/1.1" 200 1 "-" "UA"',
            '192.168.1.5 - - [02/Jan/2023:00:00:01 +0100] "GET /articles/555 HTTP/1.1" 200 1 "-" "UA"',
        ]);

        $results = $this->collect($logPath, '2023-01-01');

        $this->assertCount(3, $results);
        $this->assertSame(222, $results[0]['doc_id']);
        $this->assertSame(333, $results[1]['doc_id']);
        $this->assertSame(444, $results[2]['doc_id']);
    }

    public function testSkipsInvalidDocIds(): void
    {
        $logPath = $this->writeLog([
            '192.168.1.1 - - [01/Jan/2023:10:00:00 +0100] "GET /articles/0 HTTP/1.1" 200 1 "-" "UA"',
            '192.168.1.2 - - [01/Jan/2023:10:01:00 +0100] "GET /articles/10000000 HTTP/1.1" 200 1 "-" "UA"',
            '192.168.1.3 - - [01/Jan/2023:10:02:00 +0100] "GET /articles/123 HTTP/1.1" 200 1 "-" "UA"',
        ]);

        $results = $this->collect($logPath, '2023-01-01');

        $this->assertCount(1, $results);
        $this->assertSame(123, $results[0]['doc_id']);
    }

    public function testSkipsInvalidIpAddresses(): void
    {
        $logPath = $this->writeLog([
            'not-an-ip - - [01/Jan/2023:10:00:00 +0100] "GET /articles/111 HTTP/1.1" 200 1 "-" "UA"',
            '192.168.1.1 - - [01/Jan/2023:10:01:00 +0100] "GET /articles/222 HTTP/1.1" 200 1 "-" "UA"',
        ]);

        $results = $this->collect($logPath, '2023-01-01');

        $this->assertCount(1, $results);
        $this->assertSame(222, $results[0]['doc_id']);
    }

    public function testResultsSortedByTimestamp(): void
    {
        // Lines in reverse chronological order in the file
        $logPath = $this->writeLog([
            '192.168.1.3 - - [01/Jan/2023:10:03:00 +0100] "GET /articles/333 HTTP/1.1" 200 1 "-" "UA"',
            '192.168.1.1 - - [01/Jan/2023:10:01:00 +0100] "GET /articles/111 HTTP/1.1" 200 1 "-" "UA"',
            '192.168.1.2 - - [01/Jan/2023:10:02:00 +0100] "GET /articles/222 HTTP/1.1" 200 1 "-" "UA"',
        ]);

        $results = $this->collect($logPath, '2023-01-01');

        $this->assertSame(111, $results[0]['doc_id']);
        $this->assertSame(222, $results[1]['doc_id']);
        $this->assertSame(333, $results[2]['doc_id']);
    }

    public function testUserAgentControlCharsRemoved(): void
    {
        $logPath = $this->writeLog([
            '192.168.1.1 - - [01/Jan/2023:10:00:00 +0100] "GET /articles/123 HTTP/1.1" 200 1 "-" "Mozilla' . "\x00\x1F" . '/5.0"',
        ]);

        $results = $this->collect($logPath, '2023-01-01');

        $this->assertCount(1, $results);
        $this->assertStringNotContainsString("\x00", $results[0]['user_agent']);
        $this->assertStringNotContainsString("\x1F", $results[0]['user_agent']);
        $this->assertStringContainsString('Mozilla', $results[0]['user_agent']);
    }

    public function testIpConvertedToUnsignedInt(): void
    {
        $logPath = $this->writeLog([
            '1.2.3.4 - - [01/Jan/2023:10:00:00 +0100] "GET /articles/123 HTTP/1.1" 200 1 "-" "UA"',
        ]);

        $results = $this->collect($logPath, '2023-01-01');

        $this->assertCount(1, $results);
        $this->assertSame((int) sprintf('%u', ip2long('1.2.3.4')), $results[0]['ip']);
    }

    public function testDateTimeFormattedCorrectly(): void
    {
        $logPath = $this->writeLog([
            '1.2.3.4 - - [15/Mar/2023:14:30:45 +0100] "GET /articles/1 HTTP/1.1" 200 1 "-" "UA"',
        ]);

        $results = $this->collect($logPath, '2023-03-15');

        $this->assertCount(1, $results);
        $this->assertSame('2023-03-15 14:30:45', $results[0]['date_time']);
    }

    public function testEmptyFileReturnsNoAccesses(): void
    {
        $logPath = $this->writeLog([]);
        $results = $this->collect($logPath, '2023-01-01');
        $this->assertCount(0, $results);
    }

    // -------------------------------------------------------------------------
    // Timestamp extraction
    // -------------------------------------------------------------------------

    public function testExtractTimestampValidLine(): void
    {
        $line      = '1.2.3.4 - - [01/Jan/2023:10:30:45 +0100] "GET /articles/1 HTTP/1.1" 200 0';
        $timestamp = $this->invokePrivate('extractTimestamp', [$line, $this->nullLogger]);
        $this->assertSame('2023-01-01 10:30:45', date('Y-m-d H:i:s', $timestamp));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @param string[] $lines */
    private function writeLog(array $lines): string
    {
        $path = $this->tempLogDir . '/test_' . uniqid() . '.access_log';
        file_put_contents($path, implode("\n", $lines) . "\n");
        return $path;
    }

    /** @return array<int, array<string, mixed>> */
    private function collect(string $logPath, string $date): array
    {
        $m = (new ReflectionClass($this->command))->getMethod('collectArticleAccesses');
        $m->setAccessible(true);
        return $m->invoke($this->command, $logPath, $date, $this->nullLogger);
    }

    /** @param mixed[] $args */
    private function invokePrivate(string $method, array $args): mixed
    {
        $m = (new ReflectionClass($this->command))->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($this->command, $args);
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
