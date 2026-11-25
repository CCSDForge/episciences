<?php

declare(strict_types=1);

namespace unit\library\Episciences\Submit;

use Episciences\Submit\SubmittedRecord;
use DOMDocument;
use PHPUnit\Framework\TestCase;
use XSLTProcessor;
use Zend_Session_Namespace;

/**
 * The record stored with a submitted paper is the one of the searched document, remembered
 * in the session: the form can no longer replace it.
 */
final class SubmittedRecordTest extends TestCase
{
    protected function setUp(): void
    {
        (new Zend_Session_Namespace('submitted_records'))->unsetAll();
    }

    public function testRememberedRecordIsReturnedWithoutFetchingTheRepository(): void
    {
        SubmittedRecord::remember('1', 'arxiv:1234.5678', 2.0, ['record' => '<record>searched</record>']);

        self::assertSame(
            '<record>searched</record>',
            SubmittedRecord::resolve('1', 'arxiv:1234.5678', 2.0)
        );
    }

    public function testValuesComputedFromTheRepositoryAreReturnedWithTheRecord(): void
    {
        SubmittedRecord::remember('4', 'zenodo-version-2', 3.0, [
            'record' => '<record>searched</record>',
            'conceptIdentifier' => '10.5281/zenodo.1234',
            'hookVersion' => '3',
            'update' => '20240102T030405',
            'xslt' => '<div>not remembered</div>',
        ]);

        self::assertSame(
            [
                'record' => '<record>searched</record>',
                'conceptIdentifier' => '10.5281/zenodo.1234',
                'hookVersion' => 3.0,
                'update' => '20240102T030405',
            ],
            SubmittedRecord::resolveDocument('4', 'zenodo-version-2', 3.0)
        );
    }

    public function testDocumentWithoutComputedValuesHasNone(): void
    {
        SubmittedRecord::remember('1', 'doc-a', 1.0, ['record' => '<record>a</record>']);
        SubmittedRecord::remember('1', 'doc-b', 1.0, ['record' => '<record>b</record>', 'conceptIdentifier' => '', 'hookVersion' => null, 'update' => '']);

        $none = ['conceptIdentifier' => null, 'hookVersion' => null, 'update' => null];
        self::assertSame(['record' => '<record>a</record>'] + $none, SubmittedRecord::resolveDocument('1', 'doc-a', 1.0));
        self::assertSame(['record' => '<record>b</record>'] + $none, SubmittedRecord::resolveDocument('1', 'doc-b', 1.0));
    }

    public function testResponseWithoutRecordIsNotRemembered(): void
    {
        SubmittedRecord::remember('1', 'doc-a', 1.0, ['conceptIdentifier' => '10.5281/zenodo.1234']);

        self::assertEmpty((new Zend_Session_Namespace('submitted_records'))->records);
    }

    public function testRecordRememberedInTheFormerFormatIsFetchedAgain(): void
    {
        // A session opened before the other values were remembered holds the record alone
        $session = new Zend_Session_Namespace('submitted_records');
        $session->records = [sha1('0|doc-a|1') => '<record>a</record>'];

        self::assertNull(SubmittedRecord::resolveDocument('0', 'doc-a', 1.0), 'fetched again from the (unknown) repository');
        self::assertSame([], $session->records, 'the former entry is forgotten');
    }

    public function testRecordIsKeyedByRepositoryIdentifierAndVersion(): void
    {
        SubmittedRecord::remember('1', 'doc-a', 1.0, ['record' => '<record>a</record>']);
        SubmittedRecord::remember('1', 'doc-b', 1.0, ['record' => '<record>b</record>']);
        SubmittedRecord::remember('1', 'doc-a', 2.0, ['record' => '<record>a2</record>']);

        self::assertSame('<record>a</record>', SubmittedRecord::resolve('1', 'doc-a', 1.0));
        self::assertSame('<record>b</record>', SubmittedRecord::resolve('1', 'doc-b', 1.0));
        self::assertSame('<record>a2</record>', SubmittedRecord::resolve('1', 'doc-a', 2.0));
    }

    public function testOnlyTheLatestSearchesAreRemembered(): void
    {
        for ($i = 0; $i < 12; $i++) {
            SubmittedRecord::remember('1', 'doc-' . $i, 1.0, ['record' => '<record>' . $i . '</record>']);
        }

        $records = array_column((new Zend_Session_Namespace('submitted_records'))->records, 'record');

        self::assertCount(10, $records);
        self::assertContains('<record>11</record>', $records);
        self::assertNotContains('<record>0</record>', $records);
    }

    public function testNothingIsRememberedForAnotherDocumentOrVersion(): void
    {
        SubmittedRecord::remember('1', 'doc-a', 1.0, ['record' => '<record>a</record>']);

        // A different document, version or repository never gets the remembered record:
        // it is fetched again (the repository 0 does not exist, so nothing comes back)
        self::assertNull(SubmittedRecord::resolve('0', 'doc-a', 1.0));
        self::assertNull(SubmittedRecord::resolve('0', 'doc-b', 1.0));

        $records = array_column((new Zend_Session_Namespace('submitted_records'))->records, 'record');
        self::assertContains('<record>a</record>', $records, 'the remembered record is still there');
    }

    public function testRecordIsNullWhenTheRepositoryGivesNothingBack(): void
    {
        self::assertNull(SubmittedRecord::resolve('0', 'unknown-document', 1.0));
    }

    public function testRememberedRecordIsConsumedAndCannotBeResolvedTwice(): void
    {
        SubmittedRecord::remember('0', 'doc-a', 1.0, ['record' => '<record>a</record>']);

        self::assertSame('<record>a</record>', SubmittedRecord::resolve('0', 'doc-a', 1.0));
        self::assertNull(SubmittedRecord::resolve('0', 'doc-a', 1.0), 'a second submission fetches again');
    }

    public function testRememberedRecordIsForgottenOnceResolved(): void
    {
        SubmittedRecord::remember('1', 'doc-a', 1.0, ['record' => '<record>a</record>']);
        SubmittedRecord::remember('1', 'doc-b', 1.0, ['record' => '<record>b</record>']);

        self::assertSame('<record>a</record>', SubmittedRecord::resolve('1', 'doc-a', 1.0));

        $records = array_column((new Zend_Session_Namespace('submitted_records'))->records, 'record');

        self::assertCount(1, $records);
        self::assertContains('<record>b</record>', $records);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function stylesheetProvider(): array
    {
        return ['full paper' => ['full_paper.xsl'], 'admin paper' => ['admin_paper.xsl']];
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function licenceProvider(): array
    {
        $cc = 'https://creativecommons.org/licenses/by/4.0/';

        return [
            'rights https url' => ['dc', $cc, true],
            'rights http url' => ['dc', 'http://creativecommons.org/licenses/by/4.0/', true],
            'rights javascript' => ['dc', 'javascript:alert(1)//href=', false],
            'rights plain text' => ['dc', 'All rights reserved', false],
            'paper licence https url' => ['paper', $cc, true],
            'paper licence javascript' => ['paper', 'javascript:alert(1)', false],
        ];
    }

    /**
     * @dataProvider stylesheetProvider
     */
    public function testLicenceIsLinkedOnlyForHttpUrls(string $stylesheet): void
    {
        foreach (self::licenceProvider() as $label => [$source, $licence, $linked]) {
            $html = $this->renderLicence($stylesheet, $source, $licence);

                if ($linked) {
                    self::assertStringContainsString('Licence : <a', $html, $label);
                    self::assertStringContainsString('href="' . $licence . '"', $html, $label);
                    self::assertStringContainsString('rel="noopener"', $html, $label);
                    self::assertStringContainsString('target="_blank"', $html, $label);
                } else {
                self::assertDoesNotMatchRegularExpression('/Licence : <a [^>]*href=/', $html, $label);
            }
        }
    }

    private function renderLicence(string $stylesheet, string $source, string $licence): string
    {
        $escaped = htmlspecialchars($licence, ENT_XML1);
        $licenceNode = $source === 'paper' ? '<paperLicence>' . $escaped . '</paperLicence>' : '';
        $rightsNode = $source === 'dc' ? '<dc:rights>' . $escaped . '</dc:rights>' : '';

        $xml = new DOMDocument();
        $xml->loadXML(
            '<record xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/">'
            . '<episciences>' . $licenceNode . '</episciences>'
            . '<metadata><oai_dc:dc>' . $rightsNode . '</oai_dc:dc></metadata></record>'
        );

        $xsl = new DOMDocument();
        $xsl->load(APPLICATION_PATH . '/../public/xsl/' . $stylesheet);

        $processor = new XSLTProcessor();
        $processor->registerPHPFunctions(['Ccsd_Tools::translate', 'Episciences_Tools::isHttpUrl']);
        $processor->importStylesheet($xsl);

        return (string) $processor->transformToXml($xml);
    }
}
