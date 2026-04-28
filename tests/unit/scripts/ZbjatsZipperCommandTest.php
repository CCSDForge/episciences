<?php

namespace unit\scripts;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;
use ZbjatsZipperCommand;

require_once __DIR__ . '/../../../scripts/ZbjatsZipperCommand.php';

/**
 * Unit tests for ZbjatsZipperCommand.
 *
 * Focuses on pure static logic (no bootstrap, no DB, no HTTP).
 */
class ZbjatsZipperCommandTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Command metadata
    // -------------------------------------------------------------------------

    public function testCommandName(): void
    {
        $this->assertSame('zbjats:zip', (new ZbjatsZipperCommand())->getName());
    }

    public function testCommandHasRvidOption(): void
    {
        $definition = (new ZbjatsZipperCommand())->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasOption('rvid'));
        $this->assertTrue($definition->getOption('rvid')->isValueRequired(), 'rvid must require a value');
    }

    public function testCommandHasZipPrefixOption(): void
    {
        $definition = (new ZbjatsZipperCommand())->getDefinition();
        $this->assertTrue($definition->hasOption('zip-prefix'));
        $this->assertTrue($definition->getOption('zip-prefix')->isValueOptional(), 'zip-prefix must accept an optional value');
    }

    public function testCommandHasDryRunOption(): void
    {
        $definition = (new ZbjatsZipperCommand())->getDefinition();
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertFalse($definition->getOption('dry-run')->acceptValue(), 'dry-run must be a flag');
    }

    // -------------------------------------------------------------------------
    // buildPaperUrl() — public static, pure (depends on DOMAIN constant)
    // -------------------------------------------------------------------------

    public function testBuildPaperUrlPdf(): void
    {
        if (!defined('DOMAIN')) {
            define('DOMAIN', 'episciences.org');
        }
        $url = ZbjatsZipperCommand::buildPaperUrl('dmtcs', 42, 'pdf');
        $this->assertSame('https://dmtcs.episciences.org/42/pdf', $url);
    }

    public function testBuildPaperUrlZbjats(): void
    {
        if (!defined('DOMAIN')) {
            define('DOMAIN', 'episciences.org');
        }
        $url = ZbjatsZipperCommand::buildPaperUrl('dmtcs', 99, 'zbjats');
        $this->assertSame('https://dmtcs.episciences.org/99/zbjats', $url);
    }

    public function testBuildPaperUrlContainsRvCode(): void
    {
        if (!defined('DOMAIN')) {
            define('DOMAIN', 'episciences.org');
        }
        $url = ZbjatsZipperCommand::buildPaperUrl('jtcam', 7, 'pdf');
        $this->assertStringContainsString('jtcam.', $url);
        $this->assertStringStartsWith('https://', $url);
    }

    // -------------------------------------------------------------------------
    // buildZipPath() — public static, pure
    // -------------------------------------------------------------------------

    public function testBuildZipPath_NoPrefixUsesRvCode(): void
    {
        $path = ZbjatsZipperCommand::buildZipPath('/data/dmtcs/zbjats/', 'dmtcs');
        $this->assertSame('/data/dmtcs/zbjats/dmtcs.zip', $path);
    }

    public function testBuildZipPath_WithPrefix(): void
    {
        $path = ZbjatsZipperCommand::buildZipPath('/data/dmtcs/zbjats/', 'dmtcs', '2024_');
        $this->assertSame('/data/dmtcs/zbjats/2024_dmtcs.zip', $path);
    }

    public function testBuildZipPath_EmptyPrefixBehavesLikeNoPrefix(): void
    {
        $withEmpty = ZbjatsZipperCommand::buildZipPath('/data/x/zbjats/', 'x', '');
        $withNone  = ZbjatsZipperCommand::buildZipPath('/data/x/zbjats/', 'x');
        $this->assertSame($withNone, $withEmpty);
    }

    public function testBuildZipPath_EndsWithDotZip(): void
    {
        $path = ZbjatsZipperCommand::buildZipPath('/some/path/', 'myjournal', 'prefix_');
        $this->assertStringEndsWith('.zip', $path);
    }
}
