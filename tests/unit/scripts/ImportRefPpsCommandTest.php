<?php
declare(strict_types=1);

namespace unit\scripts;

use ImportRefPpsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/ImportRefPpsCommand.php';

/**
 * Unit tests for ImportRefPpsCommand.
 *
 * Tests pure logic only — no bootstrap, no Solr, no filesystem writes.
 */
class ImportRefPpsCommandTest extends TestCase
{
    /** @var list<string> */
    private array $createdFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->createdFiles = [];
    }

    // -------------------------------------------------------------------------
    // Command metadata
    // -------------------------------------------------------------------------

    public function testCommandName(): void
    {
        $this->assertSame('import:ref-pps', (new ImportRefPpsCommand())->getName());
    }

    public function testCommandHasCsvFileArgument(): void
    {
        $definition = (new ImportRefPpsCommand())->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasArgument('csv-file'));
    }

    public function testCsvFileArgumentIsOptional(): void
    {
        $arg = (new ImportRefPpsCommand())->getDefinition()->getArgument('csv-file');
        $this->assertFalse($arg->isRequired(), 'csv-file argument must be optional');
    }

    public function testCsvFileArgumentDefaultValue(): void
    {
        $arg = (new ImportRefPpsCommand())->getDefinition()->getArgument('csv-file');
        $this->assertSame('data/ref_pps/pps-current.csv', $arg->getDefault());
    }

    // -------------------------------------------------------------------------
    // isValidRow()
    // -------------------------------------------------------------------------

    public function testIsValidRow_ExactlySixFields_ReturnsTrue(): void
    {
        $this->assertTrue(ImportRefPpsCommand::isValidRow(['a', 'b', 'c', 'd', 'e', 'f']));
    }

    public function testIsValidRow_MoreThanSixFields_ReturnsTrue(): void
    {
        $this->assertTrue(ImportRefPpsCommand::isValidRow(['a', 'b', 'c', 'd', 'e', 'f', 'g']));
    }

    public function testIsValidRow_FiveFields_ReturnsFalse(): void
    {
        $this->assertFalse(ImportRefPpsCommand::isValidRow(['a', 'b', 'c', 'd', 'e']));
    }

    public function testIsValidRow_EmptyArray_ReturnsFalse(): void
    {
        $this->assertFalse(ImportRefPpsCommand::isValidRow([]));
    }

    public function testIsValidRow_OneNullField_ReturnsFalse(): void
    {
        // fgetcsv returns [null] on blank line
        $this->assertFalse(ImportRefPpsCommand::isValidRow([null]));
    }

    // -------------------------------------------------------------------------
    // mapRowToDocument()
    // -------------------------------------------------------------------------

    public function testMapRowToDocument_ContainsAllRequiredKeys(): void
    {
        $data = ['det1', '10.1234/test', 'Some Title', 'user1', 'https://pubpeer.com/1', 'retracted'];
        $doc  = ImportRefPpsCommand::mapRowToDocument($data);

        $this->assertArrayHasKey('id', $doc);
        $this->assertArrayHasKey('detectors', $doc);
        $this->assertArrayHasKey('doi', $doc);
        $this->assertArrayHasKey('title', $doc);
        $this->assertArrayHasKey('pubpeerusers', $doc);
        $this->assertArrayHasKey('pubpeerurl', $doc);
        $this->assertArrayHasKey('status', $doc);
    }

    public function testMapRowToDocument_DetectorsIsAlwaysArray(): void
    {
        $data = ['det1', '10.1234/test', 'Title', 'user', 'url', 'ok'];
        $doc  = ImportRefPpsCommand::mapRowToDocument($data);

        $this->assertIsArray($doc['detectors']);
    }

    public function testMapRowToDocument_SingleDetector(): void
    {
        $data = ['clayFeet', '10.1234/test', 'Title', 'user', 'url', 'ok'];
        $doc  = ImportRefPpsCommand::mapRowToDocument($data);

        $this->assertSame(['clayFeet'], $doc['detectors']);
    }

    public function testMapRowToDocument_MultipleDetectorsSplitByComma(): void
    {
        $data = ['annulled, problematic-cell-lines', '10.1234/test', 'Title', 'user', 'url', 'ok'];
        $doc  = ImportRefPpsCommand::mapRowToDocument($data);

        $this->assertSame(['annulled', 'problematic-cell-lines'], $doc['detectors']);
    }

    public function testMapRowToDocument_ThreeDetectors(): void
    {
        $data = ['annulled, clayFeet, mathgen', '10.1234/test', 'Title', 'user', 'url', 'ok'];
        $doc  = ImportRefPpsCommand::mapRowToDocument($data);

        $this->assertSame(['annulled', 'clayFeet', 'mathgen'], $doc['detectors']);
    }

    public function testMapRowToDocument_DashDetectorYieldsEmptyArray(): void
    {
        $data = ['-', '10.1234/test', 'Title', 'user', 'url', 'ok'];
        $doc  = ImportRefPpsCommand::mapRowToDocument($data);

        $this->assertSame([], $doc['detectors']);
    }

    public function testMapRowToDocument_EmptyDetectorYieldsEmptyArray(): void
    {
        $data = ['', '10.1234/test', 'Title', 'user', 'url', 'ok'];
        $doc  = ImportRefPpsCommand::mapRowToDocument($data);

        $this->assertSame([], $doc['detectors']);
    }

    public function testMapRowToDocument_IdIsLowercasedDoi(): void
    {
        $data = ['det', '10.1234/TEST.DOI', 'Title', 'user', 'url', 'ok'];
        $doc  = ImportRefPpsCommand::mapRowToDocument($data);

        $this->assertSame('10.1234/test.doi', $doc['id']);
    }

    public function testMapRowToDocument_IdTrimsWhitespaceFromDoi(): void
    {
        $data = ['det', '  10.1234/abc  ', 'Title', 'user', 'url', 'ok'];
        $doc  = ImportRefPpsCommand::mapRowToDocument($data);

        $this->assertSame('10.1234/abc', $doc['id']);
    }

    public function testMapRowToDocument_PreservesOriginalDoiField(): void
    {
        $data = ['det', '10.1234/Original', 'Title', 'user', 'url', 'ok'];
        $doc  = ImportRefPpsCommand::mapRowToDocument($data);

        $this->assertSame('10.1234/Original', $doc['doi']);
    }

    public function testMapRowToDocument_MapsColumnsInCorrectOrder(): void
    {
        $data = ['DETECTOR_VAL', 'DOI_VAL', 'TITLE_VAL', 'USERS_VAL', 'URL_VAL', 'STATUS_VAL'];
        $doc  = ImportRefPpsCommand::mapRowToDocument($data);

        $this->assertSame(['DETECTOR_VAL'], $doc['detectors']);
        $this->assertSame('DOI_VAL', $doc['doi']);
        $this->assertSame('TITLE_VAL', $doc['title']);
        $this->assertSame(['USERS_VAL'], $doc['pubpeerusers']);
        $this->assertSame('URL_VAL', $doc['pubpeerurl']);
        $this->assertSame('STATUS_VAL', $doc['status']);
    }

    public function testMapRowToDocument_PubpeerusersIsAlwaysArray(): void
    {
        $data = ['det', '10.1234/test', 'Title', 'user1', 'url', 'ok'];
        $doc  = ImportRefPpsCommand::mapRowToDocument($data);

        $this->assertIsArray($doc['pubpeerusers']);
    }

    public function testMapRowToDocument_MultiplePubpeerusersSplitByComma(): void
    {
        $data = ['det', '10.1234/test', 'Title', 'Parashorea Tomentella, Hoya Camphorifolia', 'url', 'ok'];
        $doc  = ImportRefPpsCommand::mapRowToDocument($data);

        $this->assertSame(['Parashorea Tomentella', 'Hoya Camphorifolia'], $doc['pubpeerusers']);
    }

    public function testMapRowToDocument_DashPubpeerusersYieldsEmptyArray(): void
    {
        $data = ['det', '10.1234/test', 'Title', '-', 'url', 'ok'];
        $doc  = ImportRefPpsCommand::mapRowToDocument($data);

        $this->assertSame([], $doc['pubpeerusers']);
    }

    public function testMapRowToDocument_SameDoiProducesSameId(): void
    {
        $data1 = ['det1', '10.1234/abc', 'Title A', 'user1', 'url1', 'ok'];
        $data2 = ['det2', '10.1234/abc', 'Title B', 'user2', 'url2', 'retracted'];

        $this->assertSame(
            ImportRefPpsCommand::mapRowToDocument($data1)['id'],
            ImportRefPpsCommand::mapRowToDocument($data2)['id'],
            'Same DOI must produce the same document ID regardless of other fields'
        );
    }

    public function testMapRowToDocument_DifferentDoisProduceDifferentIds(): void
    {
        $data1 = ['det', '10.1234/first', 'T', 'u', 'url', 'ok'];
        $data2 = ['det', '10.1234/second', 'T', 'u', 'url', 'ok'];

        $this->assertNotSame(
            ImportRefPpsCommand::mapRowToDocument($data1)['id'],
            ImportRefPpsCommand::mapRowToDocument($data2)['id']
        );
    }

    // -------------------------------------------------------------------------
    // countDataLines()
    // -------------------------------------------------------------------------

    public function testCountDataLines_WithHeaderAndThreeDataRows_ReturnsThree(): void
    {
        $tmpFile = $this->createTempCsv(
            "Detectors,Doi,Title,Pubpeerusers,Pubpeerurl,Status\n" .
            "d1,10.1/a,T1,u1,url1,ok\n" .
            "d2,10.1/b,T2,u2,url2,ok\n" .
            "d3,10.1/c,T3,u3,url3,ok\n"
        );

        $this->assertSame(3, (new ImportRefPpsCommand())->countDataLines($tmpFile));
    }

    public function testCountDataLines_HeaderOnly_ReturnsZero(): void
    {
        $tmpFile = $this->createTempCsv("Detectors,Doi,Title,Pubpeerusers,Pubpeerurl,Status\n");
        $this->assertSame(0, (new ImportRefPpsCommand())->countDataLines($tmpFile));
    }

    public function testCountDataLines_WithTrailingNewline_CountsCorrectly(): void
    {
        // File with trailing newline should not add phantom extra line
        $content = "Header\n" . implode("\n", array_fill(0, 5, 'd,doi,t,u,url,ok')) . "\n";
        $tmpFile = $this->createTempCsv($content);
        $this->assertSame(5, (new ImportRefPpsCommand())->countDataLines($tmpFile));
    }

    public function testCountDataLines_WithoutTrailingNewline_CountsCorrectly(): void
    {
        $content = "Header\n" . implode("\n", array_fill(0, 5, 'd,doi,t,u,url,ok'));
        $tmpFile = $this->createTempCsv($content);
        $this->assertSame(5, (new ImportRefPpsCommand())->countDataLines($tmpFile));
    }

    public function testCountDataLines_NonexistentFile_ReturnsNull(): void
    {
        $this->assertNull((new ImportRefPpsCommand())->countDataLines('/nonexistent/path/file.csv'));
    }

    public function testCountDataLines_EmptyFile_ReturnsZero(): void
    {
        $tmpFile = $this->createTempCsv('');
        $this->assertSame(0, (new ImportRefPpsCommand())->countDataLines($tmpFile));
    }

    // -------------------------------------------------------------------------
    // Batch boundary: isValidRow + mapRowToDocument contract
    // -------------------------------------------------------------------------

    public function testMapRowToDocument_StatusChangeSameDoiUpdatesDoc(): void
    {
        // When a DOI status changes, the document ID is the same → Solr updates the existing doc
        $original = ['det', '10.1234/doi', 'Title', 'user', 'url', 'peer_review'];
        $updated  = ['det', '10.1234/doi', 'Title', 'user', 'url', 'retracted'];

        $docOriginal = ImportRefPpsCommand::mapRowToDocument($original);
        $docUpdated  = ImportRefPpsCommand::mapRowToDocument($updated);

        $this->assertSame($docOriginal['id'], $docUpdated['id']);
        $this->assertSame('peer_review', $docOriginal['status']);
        $this->assertSame('retracted', $docUpdated['status']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createTempCsv(string $content): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'import_ref_pps_test_');
        $this->assertNotFalse($tmpFile);
        file_put_contents($tmpFile, $content);
        $this->createdFiles[] = $tmpFile;
        return $tmpFile;
    }
}
