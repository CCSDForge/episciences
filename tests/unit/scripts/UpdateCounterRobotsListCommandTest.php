<?php
declare(strict_types=1);

namespace unit\scripts;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;
use UpdateCounterRobotsListCommand;

require_once __DIR__ . '/../../../scripts/UpdateCounterRobotsListCommand.php';

/**
 * Unit tests for UpdateCounterRobotsListCommand.
 *
 * Tests pure logic only — no bootstrap, no HTTP calls.
 */
class UpdateCounterRobotsListCommandTest extends TestCase
{
    private UpdateCounterRobotsListCommand $command;

    protected function setUp(): void
    {
        $this->command = new UpdateCounterRobotsListCommand();
    }

    // -------------------------------------------------------------------------
    // Command metadata
    // -------------------------------------------------------------------------

    public function testCommandName(): void
    {
        $this->assertSame('stats:update-robots-list', $this->command->getName());
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
    // parseAndValidateContent()
    // -------------------------------------------------------------------------

    public function testParseAndValidateContent_EmptyString_ReturnsZero(): void
    {
        $this->assertSame(0, $this->command->parseAndValidateContent(''));
    }

    public function testParseAndValidateContent_OnlyComments_ReturnsZero(): void
    {
        $content = "# This is a comment\n# Another comment\n";
        $this->assertSame(0, $this->command->parseAndValidateContent($content));
    }

    public function testParseAndValidateContent_OnlyBlankLines_ReturnsZero(): void
    {
        $content = "\n\n\n";
        $this->assertSame(0, $this->command->parseAndValidateContent($content));
    }

    public function testParseAndValidateContent_ValidPatterns_ReturnsCorrectCount(): void
    {
        $content = "bot\nspider\ncrawl\n";
        $this->assertSame(3, $this->command->parseAndValidateContent($content));
    }

    public function testParseAndValidateContent_MixedContent_SkipsCommentsAndBlanks(): void
    {
        $content = "# Header comment\nbot\n\nspider\n# Another comment\ncrawl\n";
        $this->assertSame(3, $this->command->parseAndValidateContent($content));
    }

    public function testParseAndValidateContent_SinglePattern_ReturnsOne(): void
    {
        $this->assertSame(1, $this->command->parseAndValidateContent("bot\n"));
    }

    // -------------------------------------------------------------------------
    // buildDestinationPath()
    // -------------------------------------------------------------------------

    public function testBuildDestinationPath_ContainsCounterRobotsFilename(): void
    {
        // APPLICATION_PATH may not be defined in test context
        if (!defined('APPLICATION_PATH')) {
            define('APPLICATION_PATH', __DIR__ . '/../../../application');
        }
        $path = $this->command->buildDestinationPath();
        $this->assertStringEndsWith('COUNTER_Robots_list.txt', $path);
        $this->assertStringContainsString('counter-robots', $path);
    }

    public function testBuildDestinationPath_IsAbsolutePath(): void
    {
        if (!defined('APPLICATION_PATH')) {
            // Already defined above in previous test — just verify
        }
        $path = $this->command->buildDestinationPath();
        $this->assertStringStartsWith('/', $path, 'Path must be absolute');
    }
}
