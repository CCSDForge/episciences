<?php
declare(strict_types=1);

namespace unit\library\Episciences\Api;

use Episciences\Api\CrossrefDiagnosticParser;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CrossrefDiagnosticParser.
 */
class CrossrefDiagnosticParserTest extends TestCase
{
    private CrossrefDiagnosticParser $parser;

    protected function setUp(): void
    {
        $this->parser = new CrossrefDiagnosticParser();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function xml(string $batchStatus, string $records, int $success, int $failure = 0, int $warning = 0): string
    {
        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <doi_batch_diagnostic status="{$batchStatus}" sp="test-node">
               <submission_id>12345</submission_id>
               <batch_id>test_batch</batch_id>
               {$records}
               <batch_data>
                  <record_count>{$success}</record_count>
                  <success_count>{$success}</success_count>
                  <warning_count>{$warning}</warning_count>
                  <failure_count>{$failure}</failure_count>
               </batch_data>
            </doi_batch_diagnostic>
            XML;
    }

    private function record(string $doi, string $status, string $msg): string
    {
        return <<<XML
            <record_diagnostic status="{$status}">
               <doi>{$doi}</doi>
               <msg>{$msg}</msg>
            </record_diagnostic>
            XML;
    }

    // -------------------------------------------------------------------------
    // parse() — invalid XML
    // -------------------------------------------------------------------------

    public function testParse_InvalidXml_ReturnsNull(): void
    {
        $result = $this->parser->parse('this is not xml', '10.1234/test');
        $this->assertNull($result);
    }

    public function testParse_EmptyString_ReturnsNull(): void
    {
        $result = $this->parser->parse('', '10.1234/test');
        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // parse() — DOI found, single record
    // -------------------------------------------------------------------------

    public function testParse_SingleRecord_MatchingDoi_ReturnsSuccess(): void
    {
        $xml    = $this->xml('completed', $this->record('10.46298/dmtcs.16955', 'Success', 'Successfully added'), 1);
        $result = $this->parser->parse($xml, '10.46298/dmtcs.16955');

        $this->assertNotNull($result);
        $this->assertTrue($result->doiFound);
        $this->assertSame('Success', $result->doiStatus);
        $this->assertSame('Successfully added', $result->doiMsg);
        $this->assertTrue($result->isSuccess());
    }

    public function testParse_UpdateRecord_ReturnsSuccessfullyUpdated(): void
    {
        $xml    = $this->xml('completed', $this->record('10.46298/jsedi.17320', 'Success', 'Successfully updated in handle'), 1);
        $result = $this->parser->parse($xml, '10.46298/jsedi.17320');

        $this->assertNotNull($result);
        $this->assertTrue($result->isSuccess());
        $this->assertSame('Successfully updated in handle', $result->doiMsg);
    }

    public function testParse_FailureRecord_IsSuccessReturnsFalse(): void
    {
        $xml    = $this->xml('completed', $this->record('10.1234/fail', 'Failure', 'Invalid DOI format'), 0, 1);
        $result = $this->parser->parse($xml, '10.1234/fail');

        $this->assertNotNull($result);
        $this->assertTrue($result->doiFound);
        $this->assertSame('Failure', $result->doiStatus);
        $this->assertFalse($result->isSuccess());
    }

    // -------------------------------------------------------------------------
    // parse() — multiple records (article DOI + journal DOI)
    // -------------------------------------------------------------------------

    public function testParse_MultipleRecords_FindsArticleDoi(): void
    {
        $records = $this->record('10.46298/journals/jsedi', 'Success', 'Successfully updated in handle')
                 . $this->record('10.46298/jsedi.17320', 'Success', 'Successfully added');

        $xml    = $this->xml('completed', $records, 2);
        $result = $this->parser->parse($xml, '10.46298/jsedi.17320');

        $this->assertNotNull($result);
        $this->assertTrue($result->doiFound);
        $this->assertSame('Successfully added', $result->doiMsg);
        $this->assertTrue($result->isSuccess());
    }

    public function testParse_MultipleRecords_DoesNotConfuseJournalDoi(): void
    {
        $records = $this->record('10.46298/journals/jsedi', 'Success', 'Successfully updated in handle')
                 . $this->record('10.46298/jsedi.17320', 'Failure', 'Duplicate DOI');

        $xml    = $this->xml('completed', $records, 1, 1);
        $result = $this->parser->parse($xml, '10.46298/jsedi.17320');

        $this->assertNotNull($result);
        $this->assertTrue($result->doiFound);
        $this->assertSame('Failure', $result->doiStatus);
        $this->assertFalse($result->isSuccess());
    }

    // -------------------------------------------------------------------------
    // parse() — DOI not found in records
    // -------------------------------------------------------------------------

    public function testParse_DoiNotInRecords_DoiFoundFalse(): void
    {
        $xml    = $this->xml('completed', $this->record('10.46298/other.999', 'Success', 'OK'), 1);
        $result = $this->parser->parse($xml, '10.46298/article.42');

        $this->assertNotNull($result);
        $this->assertFalse($result->doiFound);
        $this->assertSame('', $result->doiStatus);
        $this->assertFalse($result->isSuccess());
    }

    public function testParse_DoiNotFound_BatchCountsStillParsed(): void
    {
        $xml    = $this->xml('completed', $this->record('10.46298/other.999', 'Success', 'OK'), 1);
        $result = $this->parser->parse($xml, '10.1234/missing');

        $this->assertNotNull($result);
        $this->assertSame(1, $result->batchSuccess);
        $this->assertSame(0, $result->batchFailure);
    }

    // -------------------------------------------------------------------------
    // parse() — DOI normalisation
    // -------------------------------------------------------------------------

    public function testParse_DoiWithHttpsPrefix_NormalisedForComparison(): void
    {
        $xml    = $this->xml('completed', $this->record('10.46298/dmtcs.16955', 'Success', 'OK'), 1);
        $result = $this->parser->parse($xml, 'https://doi.org/10.46298/dmtcs.16955');

        $this->assertNotNull($result);
        $this->assertTrue($result->doiFound);
    }

    public function testParse_CaseInsensitiveDoi(): void
    {
        $xml    = $this->xml('completed', $this->record('10.46298/DMTCS.16955', 'Success', 'OK'), 1);
        $result = $this->parser->parse($xml, '10.46298/dmtcs.16955');

        $this->assertNotNull($result);
        $this->assertTrue($result->doiFound);
    }

    // -------------------------------------------------------------------------
    // parse() — batch status
    // -------------------------------------------------------------------------

    public function testParse_QueuedForBatch_IsCompletedFalse(): void
    {
        $xml    = $this->xml('queued_for_batch', '', 0);
        $result = $this->parser->parse($xml, '10.1234/test');

        $this->assertNotNull($result);
        $this->assertSame('queued_for_batch', $result->batchStatus);
        $this->assertFalse($result->isCompleted());
    }

    public function testParse_Completed_IsCompletedTrue(): void
    {
        $xml    = $this->xml('completed', $this->record('10.1234/test', 'Success', 'OK'), 1);
        $result = $this->parser->parse($xml, '10.1234/test');

        $this->assertNotNull($result);
        $this->assertTrue($result->isCompleted());
    }

    // -------------------------------------------------------------------------
    // parse() — batch counts
    // -------------------------------------------------------------------------

    public function testParse_BatchCounts_ParsedCorrectly(): void
    {
        $records = $this->record('10.1234/ok', 'Success', 'OK')
                 . $this->record('10.1234/warn', 'Warning', 'Check metadata');

        $xmlBody = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <doi_batch_diagnostic status="completed" sp="test">
               {$records}
               <batch_data>
                  <record_count>3</record_count>
                  <success_count>1</success_count>
                  <warning_count>1</warning_count>
                  <failure_count>1</failure_count>
               </batch_data>
            </doi_batch_diagnostic>
            XML;

        $result = $this->parser->parse($xmlBody, '10.1234/ok');

        $this->assertNotNull($result);
        $this->assertSame(1, $result->batchSuccess);
        $this->assertSame(1, $result->batchWarning);
        $this->assertSame(1, $result->batchFailure);
    }

    // -------------------------------------------------------------------------
    // Real-world Crossref response examples from the project
    // -------------------------------------------------------------------------

    public function testParse_RealWorldSingleRecord(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <doi_batch_diagnostic status="completed" sp="ip-10-4-2-147.ec2.internal">
               <submission_id>1742144298</submission_id>
               <batch_id>episciences.org_16955_20260416150255726</batch_id>
               <record_diagnostic status="Success">
                  <doi>10.46298/dmtcs.16955</doi>
                  <msg>Successfully added</msg>
               </record_diagnostic>
               <batch_data>
                  <record_count>1</record_count>
                  <success_count>1</success_count>
                  <warning_count>0</warning_count>
                  <failure_count>0</failure_count>
               </batch_data>
            </doi_batch_diagnostic>
            XML;

        $result = $this->parser->parse($xml, '10.46298/dmtcs.16955');

        $this->assertNotNull($result);
        $this->assertTrue($result->isCompleted());
        $this->assertTrue($result->doiFound);
        $this->assertTrue($result->isSuccess());
        $this->assertSame('Successfully added', $result->doiMsg);
        $this->assertSame(1, $result->batchSuccess);
    }

    public function testParse_RealWorldTwoRecords_ArticleDoiIdentified(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <doi_batch_diagnostic status="completed" sp="ip-10-4-2-147.ec2.internal">
               <submission_id>1740939187</submission_id>
               <batch_id>episciences.org_17320_20260407204206682</batch_id>
               <record_diagnostic status="Success">
                  <doi>10.46298/journals/jsedi</doi>
                  <msg>Successfully updated in handle</msg>
               </record_diagnostic>
               <record_diagnostic status="Success">
                  <doi>10.46298/jsedi.17320</doi>
                  <msg>Successfully added</msg>
               </record_diagnostic>
               <batch_data>
                  <record_count>2</record_count>
                  <success_count>2</success_count>
                  <warning_count>0</warning_count>
                  <failure_count>0</failure_count>
               </batch_data>
            </doi_batch_diagnostic>
            XML;

        $articleResult = $this->parser->parse($xml, '10.46298/jsedi.17320');
        $this->assertNotNull($articleResult);
        $this->assertTrue($articleResult->doiFound);
        $this->assertSame('Successfully added', $articleResult->doiMsg);
        $this->assertTrue($articleResult->isSuccess());

        // The journal DOI record must NOT be mistaken for the article
        $journalResult = $this->parser->parse($xml, '10.46298/journals/jsedi');
        $this->assertNotNull($journalResult);
        $this->assertTrue($journalResult->doiFound);
        $this->assertSame('Successfully updated in handle', $journalResult->doiMsg);
    }
}
