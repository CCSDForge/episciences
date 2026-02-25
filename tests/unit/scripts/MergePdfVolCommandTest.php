<?php

namespace unit\scripts;

use MergePdfVolCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/MergePdfVolCommand.php';

/**
 * Unit tests for MergePdfVolCommand.
 *
 * Focuses on pure static logic (no bootstrap, no DB, no HTTP).
 */
class MergePdfVolCommandTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Command metadata
    // -------------------------------------------------------------------------

    public function testCommandName(): void
    {
        $command = new MergePdfVolCommand();
        $this->assertSame('volume:merge-pdf', $command->getName());
    }

    public function testCommandHasRvcodeOption(): void
    {
        $definition = (new MergePdfVolCommand())->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasOption('rvcode'));
        $this->assertTrue($definition->getOption('rvcode')->isValueRequired(), 'rvcode must require a value');
    }

    public function testCommandHasDryRunOption(): void
    {
        $definition = (new MergePdfVolCommand())->getDefinition();
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertFalse($definition->getOption('dry-run')->acceptValue(), 'dry-run must be a flag');
    }

    public function testCommandHasIgnoreCacheOption(): void
    {
        $definition = (new MergePdfVolCommand())->getDefinition();
        $this->assertTrue($definition->hasOption('ignore-cache'));
        $this->assertFalse($definition->getOption('ignore-cache')->acceptValue(), 'ignore-cache must be a flag');
    }

    public function testCommandHasRemoveCacheOption(): void
    {
        $definition = (new MergePdfVolCommand())->getDefinition();
        $this->assertTrue($definition->hasOption('remove-cache'));
        $this->assertFalse($definition->getOption('remove-cache')->acceptValue(), 'remove-cache must be a flag');
    }

    // -------------------------------------------------------------------------
    // getPaperIdCollection() — public static, pure
    // -------------------------------------------------------------------------

    public function testGetPaperIdCollection_EmptyArray_ReturnsEmpty(): void
    {
        $this->assertSame([], MergePdfVolCommand::getPaperIdCollection([]));
    }

    public function testGetPaperIdCollection_ExtractsPaperIds(): void
    {
        $data = [
            ['paperid' => 101, 'title' => 'A'],
            ['paperid' => 202, 'title' => 'B'],
            ['paperid' => 303, 'title' => 'C'],
        ];
        $this->assertSame([101, 202, 303], MergePdfVolCommand::getPaperIdCollection($data));
    }

    public function testGetPaperIdCollection_MissingPaperid_RowSkipped(): void
    {
        // array_column() silently skips rows where the key is absent
        $data = [
            ['title' => 'A'],          // no 'paperid' -> skipped
            ['paperid' => 5, 'title' => 'B'],
        ];
        $result = MergePdfVolCommand::getPaperIdCollection($data);
        $this->assertCount(1, $result);
        $this->assertContains(5, $result);
    }

    // -------------------------------------------------------------------------
    // isValidPdf() — public static
    // -------------------------------------------------------------------------

    public function testIsValidPdf_NonExistentFile_ReturnsFalse(): void
    {
        $this->assertFalse(MergePdfVolCommand::isValidPdf('/nonexistent/path/to/file.pdf'));
    }

    public function testIsValidPdf_FileWithPdfHeader_ReturnsTrue(): void
    {
        // Create a temp file with a valid PDF header and correct MIME type
        $tmp = tempnam(sys_get_temp_dir(), 'test_pdf_');
        try {
            // A minimal valid PDF document
            $minimalPdf = "%PDF-1.0\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
                        . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
                        . "3 0 obj<</Type/Page/MediaBox[0 0 3 3]>>endobj\n"
                        . "xref\n0 4\n0000000000 65535 f\n0000000009 00000 n\n"
                        . "0000000058 00000 n\n0000000115 00000 n\n"
                        . "trailer<</Size 4/Root 1 0 R>>\nstartxref\n190\n%%EOF";
            file_put_contents($tmp, $minimalPdf);
            $this->assertTrue(MergePdfVolCommand::isValidPdf($tmp));
        } finally {
            @unlink($tmp);
        }
    }

    public function testIsValidPdf_NonPdfFile_ReturnsFalse(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_notpdf_');
        try {
            file_put_contents($tmp, 'This is not a PDF file at all.');
            $this->assertFalse(MergePdfVolCommand::isValidPdf($tmp));
        } finally {
            @unlink($tmp);
        }
    }

    // -------------------------------------------------------------------------
    // APICALLVOL constant
    // -------------------------------------------------------------------------

    public function testApiCallVolConstant(): void
    {
        $this->assertStringStartsWith('volumes', MergePdfVolCommand::APICALLVOL);
        $this->assertStringContainsString('rvcode=', MergePdfVolCommand::APICALLVOL);
    }
}
