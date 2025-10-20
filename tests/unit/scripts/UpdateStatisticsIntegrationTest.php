<?php

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for UpdateStatistics log file processing
 */
class UpdateStatisticsIntegrationTest extends TestCase
{
    private $tempLogDir;
    private $updateStats;

    protected function setUp(): void
    {
        // Include the script
        require_once __DIR__ . '/../../../scripts/UpdateStatistics.php';
        
        // Create temporary directory for test logs
        $this->tempLogDir = sys_get_temp_dir() . '/episciences_integration_test_' . uniqid();
        mkdir($this->tempLogDir, 0755, true);

        $this->updateStats = new UpdateStatistics();
        
        // Set temporary logs base path
        $reflection = new ReflectionClass($this->updateStats);
        $logsBasePathProperty = $reflection->getProperty('logsBasePath');
        $logsBasePathProperty->setAccessible(true);
        $logsBasePathProperty->setValue($this->updateStats, $this->tempLogDir);
    }

    protected function tearDown(): void
    {
        // Clean up temporary directories
        if ($this->tempLogDir && is_dir($this->tempLogDir)) {
            $this->removeDirectory($this->tempLogDir);
        }
    }

    public function testLogFileReading()
    {
        $reflection = new ReflectionClass($this->updateStats);
        $method = $reflection->getMethod('collectArticleAccesses');
        $method->setAccessible(true);

        // Create test log content with realistic Apache log format
        $logContent = implode("\n", [
            '192.168.1.1 - - [01/Jan/2023:10:30:00 +0100] "GET /articles/123 HTTP/1.1" 200 1234 "http://example.com" "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"',
            '192.168.1.2 - - [01/Jan/2023:10:31:00 +0100] "GET /articles/456/download HTTP/1.1" 200 5678 "http://example.com" "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)"',
            '192.168.1.3 - - [01/Jan/2023:10:32:00 +0100] "GET /articles/789/preview HTTP/1.1" 200 9012 "http://example.com" "Mozilla/5.0 (X11; Linux x86_64)"',
            '192.168.1.4 - - [01/Jan/2023:10:33:00 +0100] "GET /other/path HTTP/1.1" 200 1111 "http://example.com" "Some Bot"',
            ''
        ]);

        // Test uncompressed file
        $logPath = $this->tempLogDir . '/test.access_log';
        file_put_contents($logPath, $logContent);

        $result = $method->invoke($this->updateStats, $logPath, '2023-01-01');
        
        $this->assertCount(3, $result); // Should find 3 article accesses, skip the /other/path
        
        // Check first access (article notice)
        $this->assertEquals(123, $result[0]['doc_id']);
        $this->assertEquals('notice', $result[0]['access_type']);
        $this->assertStringContainsString('Windows NT 10.0', $result[0]['user_agent']);
        
        // Check second access (file download)
        $this->assertEquals(456, $result[1]['doc_id']);
        $this->assertEquals('file', $result[1]['access_type']);
        
        // Check third access (file preview)
        $this->assertEquals(789, $result[2]['doc_id']);
        $this->assertEquals('file', $result[2]['access_type']);
    }

    public function testGzippedLogFileReading()
    {
        $reflection = new ReflectionClass($this->updateStats);
        $method = $reflection->getMethod('collectArticleAccesses');
        $method->setAccessible(true);

        // Create test log content
        $logContent = '192.168.1.1 - - [01/Jan/2023:10:30:00 +0100] "GET /articles/123 HTTP/1.1" 200 1234 "http://example.com" "Mozilla/5.0 (Test)"' . "\n";

        // Test gzipped file
        $gzLogPath = $this->tempLogDir . '/test.access_log.gz';
        $gz = gzopen($gzLogPath, 'w');
        gzwrite($gz, $logContent);
        gzclose($gz);

        $result = $method->invoke($this->updateStats, $gzLogPath, '2023-01-01');
        
        $this->assertCount(1, $result);
        $this->assertEquals(123, $result[0]['doc_id']);
        $this->assertEquals('notice', $result[0]['access_type']);
    }

    public function testDateFiltering()
    {
        $reflection = new ReflectionClass($this->updateStats);
        $method = $reflection->getMethod('collectArticleAccesses');
        $method->setAccessible(true);

        // Create log content with different dates
        $logContent = implode("\n", [
            '192.168.1.1 - - [31/Dec/2022:23:59:59 +0100] "GET /articles/111 HTTP/1.1" 200 1234 "http://example.com" "Mozilla/5.0"', // Wrong date
            '192.168.1.2 - - [01/Jan/2023:00:00:00 +0100] "GET /articles/222 HTTP/1.1" 200 1234 "http://example.com" "Mozilla/5.0"', // Correct date start
            '192.168.1.3 - - [01/Jan/2023:12:30:00 +0100] "GET /articles/333 HTTP/1.1" 200 1234 "http://example.com" "Mozilla/5.0"', // Correct date middle
            '192.168.1.4 - - [01/Jan/2023:23:59:59 +0100] "GET /articles/444 HTTP/1.1" 200 1234 "http://example.com" "Mozilla/5.0"', // Correct date end
            '192.168.1.5 - - [02/Jan/2023:00:00:01 +0100] "GET /articles/555 HTTP/1.1" 200 1234 "http://example.com" "Mozilla/5.0"', // Wrong date
            ''
        ]);

        $logPath = $this->tempLogDir . '/test.access_log';
        file_put_contents($logPath, $logContent);

        $result = $method->invoke($this->updateStats, $logPath, '2023-01-01');
        
        $this->assertCount(3, $result); // Should only find articles 222, 333, 444
        $this->assertEquals(222, $result[0]['doc_id']);
        $this->assertEquals(333, $result[1]['doc_id']);
        $this->assertEquals(444, $result[2]['doc_id']);
    }

    public function testSecuritySanitization()
    {
        $reflection = new ReflectionClass($this->updateStats);
        $method = $reflection->getMethod('collectArticleAccesses');
        $method->setAccessible(true);

        // Create log content with potentially malicious user agents
        $maliciousUserAgent = "Mozilla/5.0\x00\x01\x02 <script>alert('xss')</script>";
        $logContent = '192.168.1.1 - - [01/Jan/2023:10:30:00 +0100] "GET /articles/123 HTTP/1.1" 200 1234 "http://example.com" "' . $maliciousUserAgent . '"' . "\n";

        $logPath = $this->tempLogDir . '/test.access_log';
        file_put_contents($logPath, $logContent);

        $result = $method->invoke($this->updateStats, $logPath, '2023-01-01');
        
        $this->assertCount(1, $result);
        
        // User agent should be sanitized (control characters removed)
        $sanitizedUserAgent = $result[0]['user_agent'];
        $this->assertStringNotContainsString("\x00", $sanitizedUserAgent);
        $this->assertStringNotContainsString("\x01", $sanitizedUserAgent);
        $this->assertStringNotContainsString("\x02", $sanitizedUserAgent);
        // But script tags should still be there (we don't do HTML sanitization, just control char removal)
        $this->assertStringContainsString("Mozilla/5.0", $sanitizedUserAgent);
    }

    public function testInvalidDocumentIds()
    {
        $reflection = new ReflectionClass($this->updateStats);
        $method = $reflection->getMethod('collectArticleAccesses');
        $method->setAccessible(true);

        // Create log content with invalid document IDs
        $logContent = implode("\n", [
            '192.168.1.1 - - [01/Jan/2023:10:30:00 +0100] "GET /articles/0 HTTP/1.1" 200 1234 "http://example.com" "Mozilla/5.0"', // Invalid: 0
            '192.168.1.2 - - [01/Jan/2023:10:31:00 +0100] "GET /articles/-1 HTTP/1.1" 200 1234 "http://example.com" "Mozilla/5.0"', // Invalid: negative
            '192.168.1.3 - - [01/Jan/2023:10:32:00 +0100] "GET /articles/abc HTTP/1.1" 200 1234 "http://example.com" "Mozilla/5.0"', // Invalid: non-numeric
            '192.168.1.4 - - [01/Jan/2023:10:33:00 +0100] "GET /articles/10000000 HTTP/1.1" 200 1234 "http://example.com" "Mozilla/5.0"', // Invalid: too large
            '192.168.1.5 - - [01/Jan/2023:10:34:00 +0100] "GET /articles/123 HTTP/1.1" 200 1234 "http://example.com" "Mozilla/5.0"', // Valid
            ''
        ]);

        $logPath = $this->tempLogDir . '/test.access_log';
        file_put_contents($logPath, $logContent);

        $result = $method->invoke($this->updateStats, $logPath, '2023-01-01');
        
        // Should only find the valid document ID (123)
        $this->assertCount(1, $result);
        $this->assertEquals(123, $result[0]['doc_id']);
    }

    public function testTimestampExtraction()
    {
        $reflection = new ReflectionClass($this->updateStats);
        $extractMethod = $reflection->getMethod('extractTimestamp');
        $extractMethod->setAccessible(true);

        // Test valid timestamp extraction
        $logLine = '192.168.1.1 - - [01/Jan/2023:10:30:45 +0100] "GET /articles/123 HTTP/1.1" 200 1234';
        $timestamp = $extractMethod->invoke($this->updateStats, $logLine);
        
        // Convert back to date to verify
        $date = date('Y-m-d H:i:s', $timestamp);
        $this->assertEquals('2023-01-01 10:30:45', $date);
    }

    public function testIpExtraction()
    {
        $reflection = new ReflectionClass($this->updateStats);
        $extractMethod = $reflection->getMethod('extractIP');
        $extractMethod->setAccessible(true);

        // Test valid IP extraction
        $logLine = '192.168.1.100 - - [01/Jan/2023:10:30:45 +0100] "GET /articles/123 HTTP/1.1" 200 1234';
        $ip = $extractMethod->invoke($this->updateStats, $logLine);
        
        $this->assertEquals('192.168.1.100', $ip);

        // Test invalid IP (should return 'unknown')
        $logLine = 'invalid-ip - - [01/Jan/2023:10:30:45 +0100] "GET /articles/123 HTTP/1.1" 200 1234';
        $ip = $extractMethod->invoke($this->updateStats, $logLine);
        
        $this->assertEquals('unknown', $ip);
    }

    public function testUserAgentExtraction()
    {
        $reflection = new ReflectionClass($this->updateStats);
        $extractMethod = $reflection->getMethod('extractUserAgent');
        $extractMethod->setAccessible(true);

        // Test typical log line with user agent
        $logLine = '192.168.1.1 - - [01/Jan/2023:10:30:45 +0100] "GET /articles/123 HTTP/1.1" 200 1234 "http://example.com" "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"';
        $userAgent = $extractMethod->invoke($this->updateStats, $logLine);
        
        $this->assertEquals('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', $userAgent);

        // Test log line without user agent
        $logLine = '192.168.1.1 - - [01/Jan/2023:10:30:45 +0100] "GET /articles/123 HTTP/1.1" 200 1234';
        $userAgent = $extractMethod->invoke($this->updateStats, $logLine);
        
        $this->assertEquals('unknown', $userAgent);
    }

    /**
     * Remove directory recursively
     */
    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}