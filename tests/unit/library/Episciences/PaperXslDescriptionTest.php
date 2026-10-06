<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use XSLTProcessor;

/**
 * The paper stylesheets print dc:description without output escaping: the text must go
 * through Episciences_Tools::decodeLatexToSafeHtml() first.
 */
final class PaperXslDescriptionTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function stylesheetProvider(): array
    {
        return [
            'full paper' => ['full_paper.xsl'],
            'admin paper' => ['admin_paper.xsl'],
            'partial paper' => ['partial_paper.xsl'],
        ];
    }

    /**
     * @dataProvider stylesheetProvider
     */
    public function testStylesheetSanitizesDescriptions(string $stylesheet): void
    {
        $source = (string) file_get_contents(APPLICATION_PATH . '/../public/xsl/' . $stylesheet);

        self::assertStringNotContainsString("'Episciences_Tools::decodeLatex', string(.), true()", $source);
        self::assertSame(2, substr_count($source, "php:function('Episciences_Tools::decodeLatexToSafeHtml', string(.))"));
    }

    public function testSanitizedDescriptionIsRenderedAsHtmlThroughXslt(): void
    {
        $xsl = new DOMDocument();
        $xsl->loadXML(<<<'XML'
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:php="http://php.net/xsl">
    <xsl:output method="html"/>
    <xsl:template match="/d">
        <p><xsl:value-of select="php:function('Episciences_Tools::decodeLatexToSafeHtml', string(.))" disable-output-escaping="yes"/></p>
    </xsl:template>
</xsl:stylesheet>
XML);

        $record = new DOMDocument();
        $record->loadXML('<d>Safe &lt;i&gt;text&lt;/i&gt; &lt;img src=x onerror=alert(1)&gt;</d>');

        $processor = new XSLTProcessor();
        $processor->registerPHPFunctions();
        $processor->importStylesheet($xsl);
        $html = (string) $processor->transformToXml($record);

        self::assertStringContainsString('<i>text</i>', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('onerror', $html);
    }
}
