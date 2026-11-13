<?php

declare(strict_types=1);

namespace unit\library\Episciences\Paper\Record;

use Episciences\Paper\Record\TitleNormalizer;
use PHPUnit\Framework\TestCase;

class TitleNormalizerTest extends TestCase
{
    /** Title with a line break, as exposed by HAL */
    private const HAL_TITLE = "Jeux mathématiques et raisonnements combinatoires : une UE de Licence\npour pratiquer une démarche de recherche en mathématiques";
    private const HAL_TITLE_NORMALIZED = 'Jeux mathématiques et raisonnements combinatoires : une UE de Licence pour pratiquer une démarche de recherche en mathématiques';

    private static function oaiDcRecord(string $titles, string $extra = ''): string
    {
        return '<record><metadata>'
            . '<oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . $titles . $extra
            . '</oai_dc:dc></metadata></record>';
    }

    private static function titles(string $record, string $query = '//dc:title'): array
    {
        $dom = new \DOMDocument();
        $dom->loadXML($record);
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('dc', TitleNormalizer::DC_NAMESPACE);
        $xpath->registerNamespace('datacite', 'http://datacite.org/schema/kernel-4');

        $titles = [];
        foreach ($xpath->query($query) as $node) {
            $titles[] = $node->textContent;
        }

        return $titles;
    }

    public function testHalTitleWithLineBreakIsPutOnASingleLine(): void
    {
        $record = self::oaiDcRecord('<dc:title xml:lang="fr">' . self::HAL_TITLE . '</dc:title>');

        $normalized = TitleNormalizer::normalize($record);

        $this->assertSame([self::HAL_TITLE_NORMALIZED], self::titles($normalized));
    }

    public function testCarriageReturnsTabsAndRepeatedSpacesAreCollapsedAndTitleTrimmed(): void
    {
        $record = self::oaiDcRecord("<dc:title>\r\n  A \t title\r\n\r\nsplit   in  parts  \n</dc:title>");

        $this->assertSame(['A title split in parts'], self::titles(TitleNormalizer::normalize($record)));
    }

    public function testUnicodeLineAndParagraphSeparatorsAreCollapsed(): void
    {
        $record = self::oaiDcRecord("<dc:title>First\u{2028}second\u{2029}third\u{0085}fourth</dc:title>");

        $this->assertSame(['First second third fourth'], self::titles(TitleNormalizer::normalize($record)));
    }

    public function testNoBreakSpacesAreKept(): void
    {
        $record = self::oaiDcRecord("<dc:title>Titre\u{00A0}: sous-titre\nsur deux lignes</dc:title>");

        $this->assertSame(["Titre\u{00A0}: sous-titre sur deux lignes"], self::titles(TitleNormalizer::normalize($record)));
    }

    public function testEveryTitleOfTheRecordIsNormalized(): void
    {
        $record = self::oaiDcRecord(
            "<dc:title xml:lang=\"fr\">Un titre\nfrançais</dc:title><dc:title xml:lang=\"en\">An English\ntitle</dc:title>"
        );

        $this->assertSame(['Un titre français', 'An English title'], self::titles(TitleNormalizer::normalize($record)));
    }

    public function testAbstractLineBreaksAreKept(): void
    {
        $abstract = "First paragraph.\n\nSecond paragraph.";
        $record = self::oaiDcRecord("<dc:title>A\ntitle</dc:title>", '<dc:description>' . $abstract . '</dc:description>');

        $normalized = TitleNormalizer::normalize($record);

        $this->assertSame([$abstract], self::titles($normalized, '//dc:description'));
        $this->assertSame(['A title'], self::titles($normalized));
    }

    public function testRecordWithoutTitleToNormalizeIsReturnedUnchanged(): void
    {
        // Formatting outside titles (indentation, attribute quotes) would be lost by a new serialization
        $record = "<record>\n  <metadata>\n    <oai_dc:dc xmlns:oai_dc='http://www.openarchives.org/OAI/2.0/oai_dc/' xmlns:dc='http://purl.org/dc/elements/1.1/'>\n"
            . "      <dc:title>A clean title</dc:title>\n      <dc:description>Line\nbreak</dc:description>\n    </oai_dc:dc>\n  </metadata>\n</record>";

        $this->assertSame($record, TitleNormalizer::normalize($record));
    }

    public function testUnparsableRecordIsReturnedUnchanged(): void
    {
        $record = "<record><dc:title>Not\nwell-formed";

        $this->assertSame($record, TitleNormalizer::normalize($record));
    }

    public function testEmptyRecordIsReturnedUnchanged(): void
    {
        $this->assertSame('', TitleNormalizer::normalize(''));
        $this->assertSame("  \n", TitleNormalizer::normalize("  \n"));
    }

    public function testPrologIsKeptOnlyWhenTheInputHasOne(): void
    {
        $withoutProlog = self::oaiDcRecord("<dc:title>A\ntitle</dc:title>");
        $withProlog = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $withoutProlog;

        $this->assertStringStartsWith('<record>', TitleNormalizer::normalize($withoutProlog));
        $this->assertStringStartsWith('<?xml', TitleNormalizer::normalize($withProlog));
    }

    public function testDataCiteTitlesAreNormalized(): void
    {
        $record = '<resource xmlns="http://datacite.org/schema/kernel-4"><titles>'
            . "<title>A DataCite\ntitle</title><title titleType=\"Subtitle\">And its\r\nsubtitle</title>"
            . '</titles></resource>';

        $this->assertSame(
            ['A DataCite title', 'And its subtitle'],
            self::titles(TitleNormalizer::normalize($record), '//datacite:title')
        );
    }

    public function testTitleWithoutNamespaceIsNormalized(): void
    {
        // Default namespace already stripped (Episciences_PapersManager::cleanRecord())
        $record = "<resource><titles><title>Stripped\nnamespace</title></titles></resource>";

        $this->assertSame(['Stripped namespace'], self::titles(TitleNormalizer::normalize($record), '//title'));
    }

    public function testTitleOfAnotherVocabularyIsLeftAlone(): void
    {
        $record = "<record xmlns:other=\"https://example.org/other\"><other:title>Not\na DC title</other:title></record>";

        $this->assertSame($record, TitleNormalizer::normalize($record));
    }

    public function testInlineMarkupInTitleIsKept(): void
    {
        $record = self::oaiDcRecord("<dc:title>\n On <i>Bourbaki</i>\nand  others \n</dc:title>");

        $normalized = TitleNormalizer::normalize($record);

        $this->assertStringContainsString('<dc:title>On <i>Bourbaki</i> and others</dc:title>', $normalized);
    }

    public function testEscapedCharactersAreKept(): void
    {
        $record = self::oaiDcRecord("<dc:title>Fish &amp; chips\n&lt;3</dc:title>");

        $normalized = TitleNormalizer::normalize($record);

        $this->assertSame(['Fish & chips <3'], self::titles($normalized));
        $this->assertStringContainsString('Fish &amp; chips &lt;3', $normalized);
    }

    public function testNormalizationIsIdempotent(): void
    {
        $record = self::oaiDcRecord('<dc:title>' . self::HAL_TITLE . '</dc:title>');
        $once = TitleNormalizer::normalize($record);

        $this->assertSame($once, TitleNormalizer::normalize($once));
    }
}
