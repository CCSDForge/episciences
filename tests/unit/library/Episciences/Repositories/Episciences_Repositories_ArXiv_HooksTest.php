<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Repositories_ArXiv_Hooks.
 *
 * @covers Episciences_Repositories_ArXiv_Hooks
 */
class Episciences_Repositories_ArXiv_HooksTest extends TestCase
{
    private const RECORD_WITH_MULTIPLE_DESCRIPTIONS = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<episciences xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:description>Primary abstract text for arXiv paper</dc:description>
    <dc:description>to be published in JFP</dc:description>
</episciences>
XML;

    private const RECORD_WITH_SINGLE_DESCRIPTION = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<episciences xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:description>Single abstract text</dc:description>
</episciences>
XML;

    private const RECORD_WITH_SAME_LANG_DESCRIPTIONS = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<episciences xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:description xml:lang="en">Primary abstract text for arXiv paper</dc:description>
    <dc:description>to be published in JFP</dc:description>
    <dc:description xml:lang="en">Updated abstract text</dc:description>
</episciences>
XML;

    public function testHookFilterMetadataFiltersMultipleDescriptions(): void
    {
        $input = [
            'xml' => self::RECORD_WITH_MULTIPLE_DESCRIPTIONS,
            'metadata' => [
                'title' => 'Sample ArXiv Paper Title',
                'description' => [
                    'Primary abstract text for arXiv paper',
                    'to be published in JFP'
                ]
            ]
        ];

        $result = Episciences_Repositories_ArXiv_Hooks::hookFilterMetadata($input);

        self::assertArrayHasKey('metadata', $result);
        self::assertSame(['Primary abstract text for arXiv paper'], $result['metadata']['description']);
        self::assertSame('Sample ArXiv Paper Title', $result['metadata']['title']);
    }

    public function testHookFilterMetadataHandlesSingleDescription(): void
    {
        $input = [
            'xml' => self::RECORD_WITH_SINGLE_DESCRIPTION,
            'metadata' => [
                'description' => ['Single abstract text']
            ]
        ];

        $result = Episciences_Repositories_ArXiv_Hooks::hookFilterMetadata($input);

        self::assertSame(['Single abstract text'], $result['metadata']['description']);
    }

    public function testHookFilterMetadataHandlesEmptyOrMissingDescription(): void
    {
        $input = ['metadata' => []];

        $result = Episciences_Repositories_ArXiv_Hooks::hookFilterMetadata($input);

        self::assertSame([], $result['metadata']);
    }

    public function testHookFilterMetadataKeepsFirstDescriptionOnLanguageCollision(): void
    {
        $input = [
            'xml' => self::RECORD_WITH_SAME_LANG_DESCRIPTIONS,
            'metadata' => [
                'description' => [
                    'en' => 'Updated abstract text',
                    0 => 'to be published in JFP'
                ]
            ]
        ];

        $result = Episciences_Repositories_ArXiv_Hooks::hookFilterMetadata($input);

        self::assertSame(['en' => 'Primary abstract text for arXiv paper'], $result['metadata']['description']);
    }

    // =========================================================================
    // hookCleanXMLRecordInput()
    // =========================================================================

    private const RAW_RECORD_WITH_COMMENT = <<<'XML'
<record>
    <header>
        <identifier>oai:arXiv.org:2603.15855</identifier>
        <datestamp>2026-07-13</datestamp>
    </header>
    <metadata>
        <oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" xmlns:dc="http://purl.org/dc/elements/1.1/">
            <dc:title>Mixing visual and textual code</dc:title>
            <dc:description>Main abstract text</dc:description>
            <dc:description>to be published in JFP</dc:description>
        </oai_dc:dc>
    </metadata>
</record>
XML;

    private const RAW_RECORD_WITH_SINGLE_DESCRIPTION = <<<'XML'
<record>
    <metadata>
        <oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" xmlns:dc="http://purl.org/dc/elements/1.1/">
            <dc:description>Only abstract</dc:description>
        </oai_dc:dc>
    </metadata>
</record>
XML;

    public function testHookCleanXMLRecordInputDiscardsSurplusDescription(): void
    {
        $result = Episciences_Repositories_ArXiv_Hooks::hookCleanXMLRecordInput(['record' => self::RAW_RECORD_WITH_COMMENT]);

        self::assertSame(1, substr_count($result['record'], '<dc:description>'));
        self::assertStringContainsString('Main abstract text', $result['record']);
        self::assertStringNotContainsString('to be published in JFP', $result['record']);
        self::assertStringContainsString('Mixing visual and textual code', $result['record']);
    }

    public function testHookCleanXMLRecordInputLeavesSingleDescriptionUntouched(): void
    {
        $result = Episciences_Repositories_ArXiv_Hooks::hookCleanXMLRecordInput(['record' => self::RAW_RECORD_WITH_SINGLE_DESCRIPTION]);

        self::assertSame(1, substr_count($result['record'], '<dc:description>'));
        self::assertStringContainsString('Only abstract', $result['record']);
    }

    public function testHookCleanXMLRecordInputWithEmptyRecordReturnsInputUnchanged(): void
    {
        $input = ['record' => ''];
        self::assertSame($input, Episciences_Repositories_ArXiv_Hooks::hookCleanXMLRecordInput($input));
    }

    public function testHookCleanXMLRecordInputWithMissingRecordKeyReturnsInputUnchanged(): void
    {
        $input = ['repoId' => 2];
        self::assertSame($input, Episciences_Repositories_ArXiv_Hooks::hookCleanXMLRecordInput($input));
    }

    public function testHookCleanXMLRecordInputWithInvalidXmlReturnsInputUnchanged(): void
    {
        $input = ['record' => '<not-valid-xml'];
        self::assertSame($input, Episciences_Repositories_ArXiv_Hooks::hookCleanXMLRecordInput($input));
    }
}
