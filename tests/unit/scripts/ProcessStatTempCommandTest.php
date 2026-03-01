<?php
declare(strict_types=1);

namespace unit\scripts;

use Episciences\Paper\Visits\BotDetector;
use PHPUnit\Framework\TestCase;
use ProcessStatTempCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

require_once __DIR__ . '/../../../scripts/ProcessStatTempCommand.php';

/**
 * Unit tests for ProcessStatTempCommand.
 *
 * Focuses on pure helper methods — no bootstrap, no DB, no GeoIP.
 */
class ProcessStatTempCommandTest extends TestCase
{
    private ProcessStatTempCommand $command;

    protected function setUp(): void
    {
        $this->command = new ProcessStatTempCommand();
    }

    // -------------------------------------------------------------------------
    // Command metadata
    // -------------------------------------------------------------------------

    public function testCommandName(): void
    {
        $this->assertSame('stats:process', $this->command->getName());
    }

    public function testCommandHasDateSOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasOption('date-s'));
        $this->assertTrue($definition->getOption('date-s')->isValueRequired(), 'date-s must require a value');
    }

    public function testCommandHasAllOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('all'));
        $this->assertFalse($definition->getOption('all')->acceptValue(), 'all must be a flag');
    }

    public function testCommandHasDryRunOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertFalse($definition->getOption('dry-run')->acceptValue(), 'dry-run must be a flag');
    }

    // -------------------------------------------------------------------------
    // resolveOptions()
    // -------------------------------------------------------------------------

    /**
     * Build ArrayInput bound to the command's definition.
     *
     * @param array<string, mixed> $params
     */
    private function makeInput(array $params): ArrayInput
    {
        $input = new ArrayInput($params, $this->command->getDefinition());
        $input->setInteractive(false);
        return $input;
    }

    private function makeIo(ArrayInput $input): SymfonyStyle
    {
        return new SymfonyStyle($input, new NullOutput());
    }

    public function testResolveOptions_AllAndDateS_ReturnsFailure(): void
    {
        $input = $this->makeInput(['--all' => true, '--date-s' => '2024-01-01']);
        $result = $this->command->resolveOptions($input, $this->makeIo($input));
        $this->assertSame(Command::FAILURE, $result);
    }

    public function testResolveOptions_InvalidDateFormat_ReturnsFailure(): void
    {
        $input = $this->makeInput(['--date-s' => 'not-a-date']);
        $result = $this->command->resolveOptions($input, $this->makeIo($input));
        $this->assertSame(Command::FAILURE, $result);
    }

    public function testResolveOptions_InvalidCalendarDate_ReturnsFailure(): void
    {
        $input = $this->makeInput(['--date-s' => '2024-13-45']);
        $result = $this->command->resolveOptions($input, $this->makeIo($input));
        $this->assertSame(Command::FAILURE, $result);
    }

    public function testResolveOptions_ValidDateS_ReturnsCorrectDate(): void
    {
        $input  = $this->makeInput(['--date-s' => '2024-03-15']);
        $result = $this->command->resolveOptions($input, $this->makeIo($input));
        $this->assertIsArray($result);
        $this->assertSame('2024-03-15', $result['date']);
        $this->assertFalse($result['all']);
        $this->assertFalse($result['dryRun']);
    }

    public function testResolveOptions_AllFlag_ReturnsNullDate(): void
    {
        $input  = $this->makeInput(['--all' => true]);
        $result = $this->command->resolveOptions($input, $this->makeIo($input));
        $this->assertIsArray($result);
        $this->assertTrue($result['all']);
        $this->assertNull($result['date']);
    }

    public function testResolveOptions_NoOptions_ReturnsYesterdayDate(): void
    {
        $input    = $this->makeInput([]);
        $result   = $this->command->resolveOptions($input, $this->makeIo($input));
        $this->assertIsArray($result);
        $this->assertFalse($result['all']);
        $this->assertSame(date('Y-m-d', strtotime('-1 day')), $result['date']);
    }

    public function testResolveOptions_DryRunFlag_IsPreserved(): void
    {
        $input  = $this->makeInput(['--dry-run' => true]);
        $result = $this->command->resolveOptions($input, $this->makeIo($input));
        $this->assertIsArray($result);
        $this->assertTrue($result['dryRun']);
    }

    // -------------------------------------------------------------------------
    // classifyRow()
    // -------------------------------------------------------------------------

    private function makeBotDetector(string $patterns): BotDetector
    {
        BotDetector::resetCache();
        $tmp = tempnam(sys_get_temp_dir(), 'bd_');
        file_put_contents((string) $tmp, $patterns);
        $detector = new BotDetector((string) $tmp);
        // Store path for cleanup
        $this->registerCleanup((string) $tmp);
        return $detector;
    }

    /** @var list<string> */
    private array $tmpFiles = [];

    private function registerCleanup(string $path): void
    {
        $this->tmpFiles[] = $path;
    }

    protected function tearDown(): void
    {
        BotDetector::resetCache();
        foreach ($this->tmpFiles as $f) {
            if (file_exists($f)) {
                unlink($f);
            }
        }
        $this->tmpFiles = [];
    }

    public function testClassifyRow_InvalidIp_ReturnsInvalidIp(): void
    {
        $detector = $this->makeBotDetector("bot\n");
        $this->assertSame('invalid_ip', $this->command->classifyRow('not-an-ip', 'Mozilla/5.0', $detector));
    }

    public function testClassifyRow_EmptyIp_ReturnsInvalidIp(): void
    {
        $detector = $this->makeBotDetector("bot\n");
        $this->assertSame('invalid_ip', $this->command->classifyRow('', 'Mozilla/5.0', $detector));
    }

    public function testClassifyRow_BotUserAgent_ReturnsBot(): void
    {
        $detector = $this->makeBotDetector("bot\nspider\n");
        $this->assertSame('bot', $this->command->classifyRow('91.120.10.45', 'Googlebot/2.1', $detector));
    }

    public function testClassifyRow_EmptyUserAgent_ReturnsBot(): void
    {
        $detector = $this->makeBotDetector("bot\n");
        $this->assertSame('bot', $this->command->classifyRow('91.120.10.45', '', $detector));
    }

    public function testClassifyRow_HumanUserAgent_ReturnsHuman(): void
    {
        $detector = $this->makeBotDetector("bot\nspider\ncrawl\n");
        $ua       = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        $this->assertSame('human', $this->command->classifyRow('91.120.10.45', $ua, $detector));
    }

    public function testClassifyRow_ValidIpNoPatternFile_ReturnsHuman(): void
    {
        BotDetector::resetCache();
        $detector = new BotDetector('/nonexistent/COUNTER_Robots_list.txt');
        $this->assertSame('human', $this->command->classifyRow('1.2.3.4', 'Mozilla/5.0', $detector));
    }

    // -------------------------------------------------------------------------
    // buildInsertBind()
    // -------------------------------------------------------------------------

    /**
     * @return array{domain: string, continent: string, country: string, city: string, lat: float, lon: float}
     */
    private function makeGeo(): array
    {
        return ['domain' => 'example.com', 'continent' => 'EU', 'country' => 'FR', 'city' => '', 'lat' => 48.85, 'lon' => 2.35];
    }

    public function testBuildInsertBind_ContainsAllRequiredKeys(): void
    {
        $row  = ['DOCID' => '42', 'CONSULT' => 'notice', 'HTTP_USER_AGENT' => 'Mozilla/5.0', 'DHIT' => '2024-03-15'];
        $bind = $this->command->buildInsertBind($row, $this->makeGeo(), '91.120.0.0', '2024-03-01');

        foreach ([':DOCID', ':CONSULT', ':IP', ':ROBOT', ':AGENT', ':DOMAIN', ':CONTINENT', ':COUNTRY', ':CITY', ':LAT', ':LON', ':HIT', ':COUNTER'] as $key) {
            $this->assertArrayHasKey($key, $bind, "Missing key $key");
        }
    }

    public function testBuildInsertBind_DocIdIsCastToInt(): void
    {
        $row  = ['DOCID' => '99', 'CONSULT' => 'file', 'HTTP_USER_AGENT' => 'Mozilla/5.0'];
        $bind = $this->command->buildInsertBind($row, $this->makeGeo(), '91.120.0.0', '2024-03-01');
        $this->assertSame(99, $bind[':DOCID']);
    }

    public function testBuildInsertBind_RobotIsAlwaysZero(): void
    {
        $row  = ['DOCID' => 1, 'CONSULT' => 'notice', 'HTTP_USER_AGENT' => 'Mozilla/5.0'];
        $bind = $this->command->buildInsertBind($row, $this->makeGeo(), '91.120.0.0', '2024-03-01');
        $this->assertSame(0, $bind[':ROBOT']);
    }

    public function testBuildInsertBind_GeoValuesPassedThrough(): void
    {
        $geo  = ['domain' => 'cnrs.fr', 'continent' => 'EU', 'country' => 'FR', 'city' => '', 'lat' => 48.85, 'lon' => 2.35];
        $row  = ['DOCID' => 1, 'CONSULT' => 'notice', 'HTTP_USER_AGENT' => 'Mozilla/5.0'];
        $bind = $this->command->buildInsertBind($row, $geo, '91.120.0.0', '2024-03-01');
        $this->assertSame('cnrs.fr', $bind[':DOMAIN']);
        $this->assertSame('EU', $bind[':CONTINENT']);
        $this->assertSame('FR', $bind[':COUNTRY']);
        $this->assertSame(48.85, $bind[':LAT']);
        $this->assertSame(2.35, $bind[':LON']);
    }

    public function testBuildInsertBind_AnonymizedIpAndHitPreserved(): void
    {
        $row  = ['DOCID' => 1, 'CONSULT' => 'notice', 'HTTP_USER_AGENT' => 'Mozilla/5.0'];
        $bind = $this->command->buildInsertBind($row, $this->makeGeo(), '91.120.0.0', '2024-03-01');
        $this->assertSame('91.120.0.0', $bind[':IP']);
        $this->assertSame('2024-03-01', $bind[':HIT']);
    }

    public function testBuildInsertBind_CounterIsOne(): void
    {
        $row  = ['DOCID' => 1, 'CONSULT' => 'notice', 'HTTP_USER_AGENT' => 'Mozilla/5.0'];
        $bind = $this->command->buildInsertBind($row, $this->makeGeo(), '91.120.0.0', '2024-03-01');
        $this->assertSame(1, $bind[':COUNTER']);
    }

    // -------------------------------------------------------------------------
    // buildSummary()
    // -------------------------------------------------------------------------

    public function testBuildSummary_FormatsAllCounters(): void
    {
        $s = $this->command->buildSummary(100, 5, 20, 2);
        $this->assertStringContainsString('Processed: 100', $s);
        $this->assertStringContainsString('Skipped (invalid IP): 5', $s);
        $this->assertStringContainsString('Robots: 20', $s);
        $this->assertStringContainsString('Errors: 2', $s);
    }

    public function testBuildSummary_AllZeros(): void
    {
        $s = $this->command->buildSummary(0, 0, 0, 0);
        $this->assertStringContainsString('Processed: 0', $s);
        $this->assertStringContainsString('Robots: 0', $s);
    }

    // -------------------------------------------------------------------------
    // anonymizeIp()
    // -------------------------------------------------------------------------

    public function testAnonymizeIp_IPv4_MasksLastTwoOctets(): void
    {
        $this->assertSame('91.120.0.0', $this->command->anonymizeIp('91.120.10.45'));
    }

    public function testAnonymizeIp_AnotherIPv4(): void
    {
        $this->assertSame('66.249.0.0', $this->command->anonymizeIp('66.249.64.123'));
    }

    public function testAnonymizeIp_AlreadyMasked_ReturnsConsistentResult(): void
    {
        $this->assertSame('192.168.0.0', $this->command->anonymizeIp('192.168.0.0'));
    }

    public function testAnonymizeIp_InvalidIp_ReturnsFallback(): void
    {
        $this->assertSame('127.0.0.1', $this->command->anonymizeIp('not-an-ip'));
    }

    public function testAnonymizeIp_EmptyString_ReturnsFallback(): void
    {
        $this->assertSame('127.0.0.1', $this->command->anonymizeIp(''));
    }

    // -------------------------------------------------------------------------
    // extractDomain()
    // -------------------------------------------------------------------------

    public function testExtractDomain_UnresolvableIp_ReturnsEmpty(): void
    {
        // 192.0.2.1 is in TEST-NET — guaranteed to not resolve
        $this->assertSame('', $this->command->extractDomain('192.0.2.1'));
    }

    public function testExtractDomain_CachedNonResolved_ReturnsEmptyWithoutDns(): void
    {
        $ip = '192.0.2.2';
        $this->assertSame('', $this->command->extractDomain($ip));
        $this->assertSame('', $this->command->extractDomain($ip)); // second call hits cache
    }

    // -------------------------------------------------------------------------
    // normalizeHit()
    // -------------------------------------------------------------------------

    public function testNormalizeHit_ConvertsToFirstDayOfMonth(): void
    {
        $this->assertSame('2024-03-01', $this->command->normalizeHit('2024-03-15 10:30:00'));
    }

    public function testNormalizeHit_EndOfMonth(): void
    {
        $this->assertSame('2024-01-01', $this->command->normalizeHit('2024-01-31 23:59:59'));
    }

    public function testNormalizeHit_AlreadyFirstDay(): void
    {
        $this->assertSame('2024-06-01', $this->command->normalizeHit('2024-06-01 00:00:00'));
    }

    // -------------------------------------------------------------------------
    // formatDryRunLine()
    // -------------------------------------------------------------------------

    public function testFormatDryRunLine_BotRow_ContainsBotTag(): void
    {
        $line = $this->command->formatDryRunLine(true, '66.249.64.123', 'Googlebot/2.1');
        $this->assertStringContainsString('[BOT]', $line);
        $this->assertStringContainsString('66.249.64.123', $line);
        $this->assertStringContainsString('Googlebot/2.1', $line);
    }

    public function testFormatDryRunLine_HumanRow_ContainsOkTag(): void
    {
        $ua   = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
        $line = $this->command->formatDryRunLine(false, '91.120.10.45', $ua);
        $this->assertStringContainsString('[OK]', $line);
        $this->assertStringContainsString('91.120.10.45', $line);
        $this->assertStringContainsString($ua, $line);
    }

    public function testFormatDryRunLine_BotRowDoesNotContainOkTag(): void
    {
        $line = $this->command->formatDryRunLine(true, '1.2.3.4', 'spider/1.0');
        $this->assertStringNotContainsString('[OK]', $line);
    }

    public function testFormatDryRunLine_HumanRowDoesNotContainBotTag(): void
    {
        $line = $this->command->formatDryRunLine(false, '1.2.3.4', 'Mozilla/5.0');
        $this->assertStringNotContainsString('[BOT]', $line);
    }

    // -------------------------------------------------------------------------
    // formatRowOutput()
    // -------------------------------------------------------------------------

    public function testFormatRowOutput_InvalidIp_ContainsSkipTag(): void
    {
        $detector = $this->makeBotDetector("bot\n");
        $row      = ['TIP' => 'not-an-ip', 'HTTP_USER_AGENT' => 'Mozilla/5.0'];
        $line     = $this->command->formatRowOutput($row, $detector);
        $this->assertStringContainsString('[SKIP]', $line);
        $this->assertStringContainsString('not-an-ip', $line);
    }

    public function testFormatRowOutput_BotUserAgent_ContainsBotTag(): void
    {
        $detector = $this->makeBotDetector("bot\nspider\n");
        $row      = ['TIP' => '91.120.10.45', 'HTTP_USER_AGENT' => 'Googlebot/2.1'];
        $line     = $this->command->formatRowOutput($row, $detector);
        $this->assertStringContainsString('[BOT]', $line);
        $this->assertStringContainsString('91.120.10.45', $line);
        $this->assertStringNotContainsString('[OK]', $line);
    }

    public function testFormatRowOutput_HumanUserAgent_ContainsOkTag(): void
    {
        $detector = $this->makeBotDetector("bot\nspider\ncrawl\n");
        $ua       = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
        $row      = ['TIP' => '91.120.10.45', 'HTTP_USER_AGENT' => $ua];
        $line     = $this->command->formatRowOutput($row, $detector);
        $this->assertStringContainsString('[OK]', $line);
        $this->assertStringContainsString('91.120.10.45', $line);
        $this->assertStringNotContainsString('[BOT]', $line);
    }

    public function testFormatRowOutput_MissingTip_ContainsSkipTag(): void
    {
        $detector = $this->makeBotDetector("bot\n");
        $row      = ['HTTP_USER_AGENT' => 'Mozilla/5.0'];
        $line     = $this->command->formatRowOutput($row, $detector);
        $this->assertStringContainsString('[SKIP]', $line);
    }

    public function testFormatRowOutput_EmptyUserAgent_ContainsBotTag(): void
    {
        $detector = $this->makeBotDetector("bot\n");
        $row      = ['TIP' => '91.120.10.45', 'HTTP_USER_AGENT' => ''];
        $line     = $this->command->formatRowOutput($row, $detector);
        $this->assertStringContainsString('[BOT]', $line);
    }

    public function testFormatRowOutput_ReturnsNonEmptyString(): void
    {
        $detector = $this->makeBotDetector("bot\n");
        $row      = ['TIP' => '91.120.10.45', 'HTTP_USER_AGENT' => 'Mozilla/5.0'];
        $this->assertNotEmpty($this->command->formatRowOutput($row, $detector));
    }
}
