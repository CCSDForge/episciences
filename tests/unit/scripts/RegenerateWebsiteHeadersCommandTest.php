<?php

namespace unit\scripts;

use Ccsd_Website_Header;
use PHPUnit\Framework\TestCase;
use RegenerateWebsiteHeadersCommand;

require_once __DIR__ . '/../../../scripts/RegenerateWebsiteHeadersCommand.php';

/**
 * Unit tests for RegenerateWebsiteHeadersCommand.
 *
 * Focuses on the file synchronisation (no bootstrap, no database).
 */
class RegenerateWebsiteHeadersCommandTest extends TestCase
{
    private RegenerateWebsiteHeadersCommand $command;
    private string $layoutDir;

    protected function setUp(): void
    {
        $this->command = new RegenerateWebsiteHeadersCommand();
        $this->layoutDir = sys_get_temp_dir() . '/regenerate-headers-test-' . uniqid('', true) . '/';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->layoutDir . '*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->layoutDir)) {
            rmdir($this->layoutDir);
        }
    }

    private function header(array $logos): Ccsd_Website_Header
    {
        $header = new Ccsd_Website_Header(1, '', '/public/', $this->layoutDir);
        $header->_logos = $logos;

        return $header;
    }

    private function textLogo(string $text): array
    {
        return ['type' => 'text', 'align' => 'left', 'text_class' => '', 'text_style' => '', 'text' => ['en' => $text, 'fr' => $text]];
    }

    private function writeFile(string $name, string $content): void
    {
        if (!is_dir($this->layoutDir)) {
            mkdir($this->layoutDir, 0777, true);
        }
        file_put_contents($this->layoutDir . $name, $content);
    }

    // -------------------------------------------------------------------------
    // Command metadata
    // -------------------------------------------------------------------------

    public function testCommandName(): void
    {
        $this->assertSame('website:regenerate-headers', $this->command->getName());
    }

    public function testCommandOptions(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->getOption('rvcode')->isValueRequired());
        $this->assertFalse($definition->getOption('all')->acceptValue());
        $this->assertFalse($definition->getOption('dry-run')->acceptValue());
        $this->assertSame('https', $definition->getOption('protocol')->getDefault());
    }

    // -------------------------------------------------------------------------
    // syncHeaderFiles()
    // -------------------------------------------------------------------------

    public function testUnsafeExistingFileIsRewrittenEscaped(): void
    {
        $this->writeFile('header.en.html', '<span><script>alert(1)</script></span>');
        $header = $this->header(['logo_0' => $this->textLogo('<script>alert(1)</script>')]);

        $results = $this->command->syncHeaderFiles($header, $this->layoutDir, ['en'], false);

        $this->assertSame(['header.en.html' => RegenerateWebsiteHeadersCommand::STATUS_UPDATED], $results);
        $html = (string)file_get_contents($this->layoutDir . 'header.en.html');
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertSame($header->renderHeader('en'), $html);
    }

    public function testMissingFileIsNotCreated(): void
    {
        $header = $this->header(['logo_0' => $this->textLogo('Journal')]);

        $results = $this->command->syncHeaderFiles($header, $this->layoutDir, ['en'], false);

        $this->assertSame(['header.en.html' => RegenerateWebsiteHeadersCommand::STATUS_MISSING], $results);
        $this->assertDirectoryDoesNotExist($this->layoutDir);
    }

    public function testUpToDateFileIsReportedUnchanged(): void
    {
        $header = $this->header(['logo_0' => $this->textLogo('Journal')]);
        $this->writeFile('header.en.html', $header->renderHeader('en'));

        $results = $this->command->syncHeaderFiles($header, $this->layoutDir, ['en'], false);

        $this->assertSame(['header.en.html' => RegenerateWebsiteHeadersCommand::STATUS_UNCHANGED], $results);
    }

    public function testDryRunDoesNotWrite(): void
    {
        $this->writeFile('header.en.html', 'old');
        $header = $this->header(['logo_0' => $this->textLogo('Journal')]);

        $results = $this->command->syncHeaderFiles($header, $this->layoutDir, ['en', 'fr'], true);

        $this->assertSame([
            'header.en.html' => RegenerateWebsiteHeadersCommand::STATUS_UPDATED,
            'header.fr.html' => RegenerateWebsiteHeadersCommand::STATUS_MISSING,
        ], $results);
        $this->assertSame('old', file_get_contents($this->layoutDir . 'header.en.html'));
        $this->assertFileDoesNotExist($this->layoutDir . 'header.fr.html');
    }

    public function testFileWithoutHeaderRowsIsLeftUntouched(): void
    {
        $this->writeFile('header.en.html', '<b>legacy</b>');

        $results = $this->command->syncHeaderFiles($this->header([]), $this->layoutDir, ['en', 'fr'], false);

        $this->assertSame(['header.en.html' => RegenerateWebsiteHeadersCommand::STATUS_ORPHAN], $results);
        $this->assertSame('<b>legacy</b>', file_get_contents($this->layoutDir . 'header.en.html'));
        $this->assertFileDoesNotExist($this->layoutDir . 'header.fr.html');
    }

    public function testJournalWithoutHeaderRowsNorFileIsSkipped(): void
    {
        $results = $this->command->syncHeaderFiles($this->header([]), $this->layoutDir, ['en'], false);

        $this->assertSame([], $results);
        $this->assertDirectoryDoesNotExist($this->layoutDir);
    }

    public function testFileOfAnUnofferedLanguageIsReportedStale(): void
    {
        $this->writeFile('header.de.html', '<b>old</b>');
        $header = $this->header(['logo_0' => $this->textLogo('Journal')]);

        $results = $this->command->syncHeaderFiles($header, $this->layoutDir, ['en'], false);

        $this->assertSame(RegenerateWebsiteHeadersCommand::STATUS_STALE, $results['header.de.html']);
        $this->assertSame('<b>old</b>', file_get_contents($this->layoutDir . 'header.de.html'));
    }
}
