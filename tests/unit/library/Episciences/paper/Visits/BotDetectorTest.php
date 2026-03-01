<?php
declare(strict_types=1);

namespace unit\library\Episciences\paper\Visits;

use Episciences\Paper\Visits\BotDetector;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for BotDetector.
 *
 * Uses temporary files to simulate the COUNTER Robots list.
 */
class BotDetectorTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        BotDetector::resetCache();
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'counter_robots_');
    }

    protected function tearDown(): void
    {
        BotDetector::resetCache();
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    // -------------------------------------------------------------------------
    // Empty / short UA
    // -------------------------------------------------------------------------

    public function testEmptyUaIsBot(): void
    {
        $detector = new BotDetector($this->tmpFile);
        $this->assertTrue($detector->isBot(''));
    }

    public function testSingleCharUaIsBot(): void
    {
        $detector = new BotDetector($this->tmpFile);
        $this->assertTrue($detector->isBot('-'));
    }

    public function testWhitespaceOnlyUaIsBot(): void
    {
        $detector = new BotDetector($this->tmpFile);
        $this->assertTrue($detector->isBot('   '));
    }

    // -------------------------------------------------------------------------
    // Pattern matching — known bots
    // -------------------------------------------------------------------------

    public function testGooglebotIsDetectedAsBot(): void
    {
        file_put_contents($this->tmpFile, "bot\nspider\n");
        $detector = new BotDetector($this->tmpFile);
        $this->assertTrue($detector->isBot('Googlebot/2.1 (+http://www.google.com/bot.html)'));
    }

    public function testBotKeywordIsDetected(): void
    {
        file_put_contents($this->tmpFile, "bot\n");
        $detector = new BotDetector($this->tmpFile);
        $this->assertTrue($detector->isBot('bot'));
    }

    public function testSpiderKeywordIsDetected(): void
    {
        file_put_contents($this->tmpFile, "spider\n");
        $detector = new BotDetector($this->tmpFile);
        $this->assertTrue($detector->isBot('spider'));
    }

    public function testCurlIsDetected(): void
    {
        file_put_contents($this->tmpFile, "curl\\/7\n");
        $detector = new BotDetector($this->tmpFile);
        $this->assertTrue($detector->isBot('curl/7.68.0'));
    }

    // -------------------------------------------------------------------------
    // Normal browser UA — should NOT be flagged
    // -------------------------------------------------------------------------

    public function testNormalBrowserIsNotBot(): void
    {
        file_put_contents($this->tmpFile, "bot\nspider\ncrawl\n");
        $detector = new BotDetector($this->tmpFile);
        $this->assertFalse($detector->isBot(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ));
    }

    // -------------------------------------------------------------------------
    // Missing file
    // -------------------------------------------------------------------------

    public function testMissingFileCausesNoException(): void
    {
        $detector = new BotDetector('/nonexistent/path/COUNTER_Robots_list.txt');
        // No exception should be thrown; only the empty-UA check applies
        $this->assertFalse($detector->isBot('Mozilla/5.0'));
    }

    // -------------------------------------------------------------------------
    // Case-insensitivity
    // -------------------------------------------------------------------------

    public function testPatternMatchIsCaseInsensitive(): void
    {
        file_put_contents($this->tmpFile, "bot\n");
        $detector = new BotDetector($this->tmpFile);
        $this->assertTrue($detector->isBot('BOT'));
        $this->assertTrue($detector->isBot('Bot'));
        $this->assertTrue($detector->isBot('bOt'));
    }

    // -------------------------------------------------------------------------
    // Invalid patterns in file are skipped gracefully
    // -------------------------------------------------------------------------

    public function testInvalidPatternInFileIsSkipped(): void
    {
        // '(?invalid' is not a valid PCRE pattern
        file_put_contents($this->tmpFile, "(?invalid\nbot\n");
        $detector = new BotDetector($this->tmpFile);
        // The valid 'bot' pattern should still work
        $this->assertTrue($detector->isBot('Googlebot/2.1'));
    }

    // -------------------------------------------------------------------------
    // resetCache() — allows new instance with different file
    // -------------------------------------------------------------------------

    public function testResetCacheAllowsNewPatternFile(): void
    {
        $file1 = tempnam(sys_get_temp_dir(), 'cr1_');
        $file2 = tempnam(sys_get_temp_dir(), 'cr2_');
        try {
            file_put_contents($file1, "bot\n");
            file_put_contents($file2, "spider\n");

            BotDetector::resetCache();
            $d1 = new BotDetector($file1);
            $this->assertTrue($d1->isBot('Googlebot'));

            BotDetector::resetCache();
            $d2 = new BotDetector($file2);
            // 'Googlebot' contains 'bot' — but pattern file2 only has 'spider'
            $this->assertFalse($d2->isBot('Googlebot'));
            $this->assertTrue($d2->isBot('spider-crawl'));
        } finally {
            @unlink($file1);
            @unlink($file2);
        }
    }

    // -------------------------------------------------------------------------
    // Comment lines are ignored
    // -------------------------------------------------------------------------

    public function testCommentLinesAreIgnored(): void
    {
        file_put_contents($this->tmpFile, "# This is a comment\nbot\n");
        $detector = new BotDetector($this->tmpFile);
        $this->assertTrue($detector->isBot('Googlebot'));
        // The comment text itself should not match anything unintentionally
    }

    // -------------------------------------------------------------------------
    // Empty patterns file
    // -------------------------------------------------------------------------

    public function testEmptyPatternsFileReturnsNoFalsePositive(): void
    {
        file_put_contents($this->tmpFile, '');
        $detector = new BotDetector($this->tmpFile);
        $this->assertFalse($detector->isBot('Mozilla/5.0'));
    }
}
