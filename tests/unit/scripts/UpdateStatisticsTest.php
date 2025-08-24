<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests for UpdateStatistics command
 */
class UpdateStatisticsTest extends TestCase
{
    private $command;
    private $commandTester;
    private $tempLogDir;

    protected function setUp(): void
    {
        // Include the script
        require_once __DIR__ . '/../../../scripts/UpdateStatistics.php';
        
        // Create temporary directory for test logs
        $this->tempLogDir = sys_get_temp_dir() . '/episciences_test_logs_' . uniqid();
        mkdir($this->tempLogDir, 0755, true);

        // Set up command
        $application = new Application();
        $application->add(new UpdateStatistics());
        $this->command = $application->find('update:statistics');
        $this->commandTester = new CommandTester($this->command);
    }

    protected function tearDown(): void
    {
        // Clean up temporary directories
        if ($this->tempLogDir && is_dir($this->tempLogDir)) {
            $this->removeDirectory($this->tempLogDir);
        }
    }

    public function testRequiresRvcodeParameter()
    {
        $this->commandTester->execute([]);
        
        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('The rvcode parameter is required', $this->commandTester->getDisplay());
    }

    public function testValidateDateFormat()
    {
        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--rvcode' => 'test',
            '--date' => 'invalid-date'
        ]);
        
        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Invalid date format', $this->commandTester->getDisplay());
    }

    public function testInvalidDateRangeOrder()
    {
        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--rvcode' => 'test',
            '--start-date' => '2023-01-02',
            '--end-date' => '2023-01-01'
        ]);
        
        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Start date must be before or equal to end date', $this->commandTester->getDisplay());
    }

    public function testMutuallyExclusiveDateOptions()
    {
        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--rvcode' => 'test',
            '--date' => '2023-01-01',
            '--month' => '2023-01'
        ]);
        
        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Please specify only one of', $this->commandTester->getDisplay());
    }

    public function testInvalidMonthFormat()
    {
        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--rvcode' => 'test',
            '--month' => 'invalid-month'
        ]);
        
        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Invalid month format', $this->commandTester->getDisplay());
    }

    public function testIncompleteDateRange()
    {
        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--rvcode' => 'test',
            '--start-date' => '2023-01-01'
        ]);
        
        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Both --start-date and --end-date are required', $this->commandTester->getDisplay());
    }

    /**
     * Test helper methods via reflection
     */
    public function testSanitizeDocId()
    {
        $updateStats = new UpdateStatistics();
        $reflection = new ReflectionClass($updateStats);
        $method = $reflection->getMethod('sanitizeDocId');
        $method->setAccessible(true);

        // Valid doc IDs
        $this->assertEquals(123, $method->invoke($updateStats, '123'));
        $this->assertEquals(999999, $method->invoke($updateStats, 999999));

        // Invalid doc IDs should throw exceptions
        $this->expectException(Exception::class);
        $method->invoke($updateStats, 'invalid');
    }

    public function testSanitizeDocIdInvalidValues()
    {
        $updateStats = new UpdateStatistics();
        $reflection = new ReflectionClass($updateStats);
        $method = $reflection->getMethod('sanitizeDocId');
        $method->setAccessible(true);

        // Test invalid values
        $invalidValues = [0, -1, 10000000, 'abc', '', null];
        
        foreach ($invalidValues as $value) {
            try {
                $method->invoke($updateStats, $value);
                $this->fail("Expected exception for value: " . var_export($value, true));
            } catch (Exception $e) {
                $this->assertStringContainsString('Invalid document ID', $e->getMessage());
            }
        }
    }

    public function testSanitizeIp()
    {
        $updateStats = new UpdateStatistics();
        $reflection = new ReflectionClass($updateStats);
        $method = $reflection->getMethod('sanitizeIp');
        $method->setAccessible(true);

        // Valid IPs (as numeric values)
        $this->assertEquals(0, $method->invoke($updateStats, 0));
        $this->assertEquals(4294967295, $method->invoke($updateStats, 4294967295)); // Max unsigned int

        // Invalid IPs should throw exceptions
        $this->expectException(Exception::class);
        $method->invoke($updateStats, 'not-numeric');
    }

    public function testSanitizeUserAgent()
    {
        $updateStats = new UpdateStatistics();
        $reflection = new ReflectionClass($updateStats);
        $method = $reflection->getMethod('sanitizeUserAgent');
        $method->setAccessible(true);

        // Normal user agent
        $result = $method->invoke($updateStats, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        $this->assertEquals('Mozilla/5.0 (Windows NT 10.0; Win64; x64)', $result);

        // User agent with control characters (should be removed)
        $result = $method->invoke($updateStats, "Mozilla\x00\x08\x1F/5.0");
        $this->assertEquals('Mozilla/5.0', $result);

        // Very long user agent (should be truncated)
        $longUserAgent = str_repeat('A', 2100);
        $result = $method->invoke($updateStats, $longUserAgent);
        $this->assertEquals(2000, mb_strlen($result));
    }

    public function testGetMonthDateRange()
    {
        $updateStats = new UpdateStatistics();
        $reflection = new ReflectionClass($updateStats);
        $method = $reflection->getMethod('getMonthDateRange');
        $method->setAccessible(true);

        // Test January 2023 (31 days)
        $dates = $method->invoke($updateStats, '2023-01');
        $this->assertCount(31, $dates);
        $this->assertEquals('2023-01-01', $dates[0]->format('Y-m-d'));
        $this->assertEquals('2023-01-31', $dates[30]->format('Y-m-d'));

        // Test February 2023 (28 days - not a leap year)
        $dates = $method->invoke($updateStats, '2023-02');
        $this->assertCount(28, $dates);
        $this->assertEquals('2023-02-01', $dates[0]->format('Y-m-d'));
        $this->assertEquals('2023-02-28', $dates[27]->format('Y-m-d'));

        // Test February 2024 (29 days - leap year)
        $dates = $method->invoke($updateStats, '2024-02');
        $this->assertCount(29, $dates);
        $this->assertEquals('2024-02-01', $dates[0]->format('Y-m-d'));
        $this->assertEquals('2024-02-29', $dates[28]->format('Y-m-d'));
    }

    public function testGetDateRange()
    {
        $updateStats = new UpdateStatistics();
        $reflection = new ReflectionClass($updateStats);
        $method = $reflection->getMethod('getDateRange');
        $method->setAccessible(true);

        // Test single day range
        $dates = $method->invoke($updateStats, '2023-01-01', '2023-01-01');
        $this->assertCount(1, $dates);
        $this->assertEquals('2023-01-01', $dates[0]->format('Y-m-d'));

        // Test multi-day range
        $dates = $method->invoke($updateStats, '2023-01-01', '2023-01-03');
        $this->assertCount(3, $dates);
        $this->assertEquals('2023-01-01', $dates[0]->format('Y-m-d'));
        $this->assertEquals('2023-01-02', $dates[1]->format('Y-m-d'));
        $this->assertEquals('2023-01-03', $dates[2]->format('Y-m-d'));
    }

    /**
     * Create a test log file
     */
    private function createTestLogFile($path, $content, $compressed = false)
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if ($compressed) {
            $gz = gzopen($path, 'w');
            gzwrite($gz, $content);
            gzclose($gz);
        } else {
            file_put_contents($path, $content);
        }
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

    public function testBuildLogFilePath()
    {
        $updateStats = new UpdateStatistics();
        $reflection = new ReflectionClass($updateStats);
        
        // Set temporary logs base path
        $logsBasePathProperty = $reflection->getProperty('logsBasePath');
        $logsBasePathProperty->setAccessible(true);
        $logsBasePathProperty->setValue($updateStats, $this->tempLogDir);
        
        $method = $reflection->getMethod('buildLogFilePath');
        $method->setAccessible(true);

        $date = new DateTime('2023-01-01');
        $siteName = 'test.episciences.org';

        // Test when no log file exists
        $result = $method->invoke($updateStats, $siteName, $date);
        $this->assertNull($result);

        // Create uncompressed log file
        $logPath = $this->tempLogDir . '/test.episciences.org/2023/01/01-test.episciences.org.access_log';
        $this->createTestLogFile($logPath, "test log content\n");

        $result = $method->invoke($updateStats, $siteName, $date);
        $this->assertEquals($logPath, $result);

        // Remove uncompressed file and create compressed file
        unlink($logPath);
        $gzLogPath = $logPath . '.gz';
        $this->createTestLogFile($gzLogPath, "test log content\n", true);

        $result = $method->invoke($updateStats, $siteName, $date);
        $this->assertEquals($gzLogPath, $result);
    }
}