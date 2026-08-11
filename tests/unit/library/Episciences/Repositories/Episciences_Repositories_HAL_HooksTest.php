<?php

namespace unit\library\Episciences\Repositories;

use Episciences_Repositories_HAL_Hooks;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Episciences_Repositories_HAL_Hooks.
 *
 * All tests are DB-free: the hook is a pure XML transformation.
 *
 * The hook strips HAL's non-abstract <dc:description> markers from the raw OAI record
 * before it reaches PAPERS.RECORD, so neither Paper::getMetadata() nor
 * Paper::getXslt() -> public/xsl/*.xsl has to filter them downstream.
 *
 * @covers Episciences_Repositories_HAL_Hooks
 */
final class Episciences_Repositories_HAL_HooksTest extends TestCase
{
    private const DC_NS = 'http://purl.org/dc/elements/1.1/';

    /**
     * Build an oai_dc record whose <dc:description> children are $descriptions.
     * Each entry is either a plain string or [text, xmlLang].
     *
     * @param array<int, string|array{0: string, 1: string}> $descriptions
     */
    private function record(array $descriptions, bool $withProlog = true): string
    {
        $nodes = '';

        foreach ($descriptions as $description) {
            [$text, $lang] = is_array($description) ? $description : [$description, null];
            $langAttr = $lang !== null ? sprintf(' xml:lang="%s"', $lang) : '';
            $nodes .= sprintf("\n        <dc:description%s>%s</dc:description>", $langAttr, $text);
        }

        return ($withProlog ? '<?xml version="1.0" encoding="UTF-8"?>' . "\n" : '') . <<<XML
<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/">
  <GetRecord>
    <record>
      <metadata>
        <oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" xmlns:dc="{$this->dcNs()}">
        <dc:title>A real title</dc:title>{$nodes}
        <dc:type>info:eu-repo/semantics/article</dc:type>
        </oai_dc:dc>
      </metadata>
    </record>
  </GetRecord>
</OAI-PMH>
XML;
    }

    private function dcNs(): string
    {
        return self::DC_NS;
    }

    /**
     * @return list<string> the text of every remaining dc:description node, in document order
     */
    private function descriptionsOf(string $record): array
    {
        $dom = new \DOMDocument();
        self::assertTrue($dom->loadXML($record), 'the hook returned a non well-formed record');

        $texts = [];
        foreach ($dom->getElementsByTagNameNS(self::DC_NS, 'description') as $node) {
            $texts[] = $node->textContent;
        }

        return $texts;
    }

    private function clean(string $record): string
    {
        $result = Episciences_Repositories_HAL_Hooks::hookCleanXMLRecordInput(['record' => $record]);

        self::assertArrayHasKey('record', $result);
        self::assertIsString($result['record']);

        return $result['record'];
    }

    // =========================================================================
    // Markers are removed
    // =========================================================================

    public function testRemovesInternationalAudienceMarker(): void
    {
        $cleaned = $this->clean($this->record(['International audience', 'The real abstract.']));

        self::assertSame(['The real abstract.'], $this->descriptionsOf($cleaned));
    }

    public function testRemovesEpisciencesSubmissionMarker(): void
    {
        $cleaned = $this->clean($this->record(['soumission à Episciences', 'The real abstract.']));

        self::assertSame(['The real abstract.'], $this->descriptionsOf($cleaned));
    }

    public function testRemovesBothMarkersAtOnce(): void
    {
        $cleaned = $this->clean($this->record([
            'International audience',
            'The real abstract.',
            'soumission à Episciences',
        ]));

        self::assertSame(['The real abstract.'], $this->descriptionsOf($cleaned));
    }

    public function testRemovesEveryOccurrenceOfTheSameMarker(): void
    {
        $cleaned = $this->clean($this->record([
            'International audience',
            'International audience',
            'The real abstract.',
        ]));

        self::assertSame(['The real abstract.'], $this->descriptionsOf($cleaned));
    }

    /**
     * The marker is the only description: the paper legitimately ends up with none,
     * rather than keeping boilerplate as its abstract.
     */
    public function testRemovesMarkerEvenWhenItIsTheOnlyDescription(): void
    {
        $cleaned = $this->clean($this->record(['International audience']));

        self::assertSame([], $this->descriptionsOf($cleaned));
    }

    // =========================================================================
    // Matching rules: normalized whitespace, case-insensitive
    // =========================================================================

    public function testMatchIgnoresCase(): void
    {
        $cleaned = $this->clean($this->record(['INTERNATIONAL AUDIENCE', 'The real abstract.']));

        self::assertSame(['The real abstract.'], $this->descriptionsOf($cleaned));
    }

    public function testMatchIgnoresSurroundingAndInnerWhitespace(): void
    {
        $cleaned = $this->clean($this->record(["\n   International    audience\n  ", 'The real abstract.']));

        self::assertSame(['The real abstract.'], $this->descriptionsOf($cleaned));
    }

    // =========================================================================
    // Real content is never touched
    // =========================================================================

    public function testKeepsAbstractContainingTheMarkerAsSubstring(): void
    {
        $abstract = 'This paper targets an International audience of researchers.';
        $cleaned  = $this->clean($this->record([$abstract]));

        self::assertSame([$abstract], $this->descriptionsOf($cleaned));
    }

    public function testKeepsMultilingualAbstractsAndTheirLanguages(): void
    {
        $cleaned = $this->clean($this->record([
            'International audience',
            ['The English abstract.', 'en'],
            ['Le résumé français.', 'fr'],
        ]));

        self::assertSame(['The English abstract.', 'Le résumé français.'], $this->descriptionsOf($cleaned));

        $dom = new \DOMDocument();
        $dom->loadXML($cleaned);
        $langs = [];
        foreach ($dom->getElementsByTagNameNS(self::DC_NS, 'description') as $node) {
            $langs[] = $node->getAttribute('xml:lang');
        }

        self::assertSame(['en', 'fr'], $langs);
    }

    public function testKeepsOtherDublinCoreFields(): void
    {
        $cleaned = $this->clean($this->record(['International audience', 'The real abstract.']));

        self::assertStringContainsString('<dc:title>A real title</dc:title>', $cleaned);
        self::assertStringContainsString('info:eu-repo/semantics/article', $cleaned);
    }

    /**
     * A same-named node from another vocabulary must stay out of reach: the hook matches
     * on the Dublin Core namespace URI, not on the local name alone.
     */
    public function testDoesNotTouchDescriptionFromAnotherNamespace(): void
    {
        $record = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<root xmlns:datacite="http://datacite.org/schema/kernel-4">
  <datacite:description>International audience</datacite:description>
</root>
XML;

        self::assertSame($record, $this->clean($record));
    }

    // =========================================================================
    // Records left untouched, byte for byte
    // =========================================================================

    public function testReturnsRecordUnchangedWhenNoMarkerIsPresent(): void
    {
        $record = $this->record(['The real abstract.']);

        self::assertSame($record, $this->clean($record));
    }

    public function testReturnsRecordUnchangedWhenXmlIsNotWellFormed(): void
    {
        $record = '<oai_dc:dc><dc:description>International audience</dc:description>';

        self::assertSame($record, $this->clean($record));
    }

    public function testReturnsInputUnchangedWhenRecordKeyIsMissing(): void
    {
        $input = ['repoId' => 1];

        self::assertSame($input, Episciences_Repositories_HAL_Hooks::hookCleanXMLRecordInput($input));
    }

    public function testReturnsInputUnchangedWhenRecordIsEmpty(): void
    {
        $input = ['record' => '', 'repoId' => 1];

        self::assertSame($input, Episciences_Repositories_HAL_Hooks::hookCleanXMLRecordInput($input));
    }

    public function testReturnsInputUnchangedWhenRecordIsNotAString(): void
    {
        $input = ['record' => ['unexpected'], 'repoId' => 1];

        self::assertSame($input, Episciences_Repositories_HAL_Hooks::hookCleanXMLRecordInput($input));
    }

    public function testPreservesOtherInputKeys(): void
    {
        $result = Episciences_Repositories_HAL_Hooks::hookCleanXMLRecordInput([
            'record' => $this->record(['International audience', 'The real abstract.']),
            'repoId' => 1,
            'extra'  => 'kept',
        ]);

        self::assertSame(1, $result['repoId']);
        self::assertSame('kept', $result['extra']);
    }

    // =========================================================================
    // Serialization details
    // =========================================================================

    public function testKeepsTheXmlPrologWhenTheInputHadOne(): void
    {
        $cleaned = $this->clean($this->record(['International audience', 'The real abstract.'], true));

        self::assertStringStartsWith('<?xml', $cleaned);
    }

    /**
     * Episciences_PapersManager::cleanRecord() and SubmitController::prepareRecord() both
     * run preg_replace('#xmlns="(.*)"#', ...) before the hook, so the record can arrive
     * without its prolog. The hook must not invent one.
     */
    public function testDoesNotAddAPrologWhenTheInputHadNone(): void
    {
        $cleaned = $this->clean($this->record(['International audience', 'The real abstract.'], false));

        self::assertStringStartsNotWith('<?xml', $cleaned);
        self::assertSame(['The real abstract.'], $this->descriptionsOf($cleaned));
    }

    // =========================================================================
    // Marker list
    // =========================================================================

    public function testMarkerListIsTheSingleSourceOfTruth(): void
    {
        self::assertSame(
            ['International audience', 'National audience', 'soumission à Episciences'],
            Episciences_Repositories_HAL_Hooks::NON_ABSTRACT_DESCRIPTIONS
        );
    }

    public function testRemovesNationalAudienceMarker(): void
    {
        $cleaned = $this->clean($this->record(['National audience', 'The real abstract.']));

        self::assertSame(['The real abstract.'], $this->descriptionsOf($cleaned));
    }

    // =========================================================================
    // Hooks deliberately left at their defaults
    // =========================================================================

    public function testUnimplementedHooksReturnEmptyArrayToKeepDefaults(): void
    {
        self::assertSame([], Episciences_Repositories_HAL_Hooks::hookApiRecords(['identifier' => 'hal-01234567']));
        self::assertSame([], Episciences_Repositories_HAL_Hooks::hookIsRequiredVersion());
        self::assertSame([], Episciences_Repositories_HAL_Hooks::hookIsIdentifierCommonToAllVersions());
    }
}