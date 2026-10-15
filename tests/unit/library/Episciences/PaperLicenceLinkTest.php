<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use DOMDocument;
use DOMXPath;
use Episciences_Paper;
use PHPUnit\Framework\TestCase;
use XSLTProcessor;
use Zend_Registry;
use Zend_Translate;
use Zend_View;

/**
 * A licence comes from a repository (or from the stored paper licence) and is
 * printed as a link: it becomes an href only when it is an http(s) URL, on
 * every page that shows it.
 */
final class PaperLicenceLinkTest extends TestCase
{
    private mixed $previousTranslate = null;
    private mixed $previousLang = null;

    protected function setUp(): void
    {
        $this->previousTranslate = Zend_Registry::isRegistered('Zend_Translate') ? Zend_Registry::get('Zend_Translate') : null;
        $this->previousLang = Zend_Registry::isRegistered('lang') ? Zend_Registry::get('lang') : null;
        Zend_Registry::set('lang', 'fr');
        Zend_Registry::set('Zend_Translate', new Zend_Translate([
            'adapter' => 'array',
            'content' => ['' => ''],
            'locale' => 'fr',
            'disableNotices' => true,
        ]));
    }

    protected function tearDown(): void
    {
        Zend_Registry::set('Zend_Translate', $this->previousTranslate);
        Zend_Registry::set('lang', $this->previousLang);
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function stylesheetLicenceProvider(): array
    {
        $cases = [];

        foreach (['full_paper.xsl', 'admin_paper.xsl', 'partial_paper.xsl'] as $stylesheet) {
            $cases[$stylesheet . ' https licence'] = [$stylesheet, 'https://creativecommons.org/licenses/by/4.0', true];
            $cases[$stylesheet . ' javascript licence'] = [$stylesheet, 'javascript:alert(1)', false];
            $cases[$stylesheet . ' data licence'] = [$stylesheet, 'data:text/html,x', false];
        }

        return $cases;
    }

    /**
     * @dataProvider stylesheetLicenceProvider
     */
    public function testStoredPaperLicenceIsLinkedOnlyWhenHttp(string $stylesheet, string $licence, bool $linked): void
    {
        $html = $this->renderStylesheet(
            $stylesheet,
            '<paperLicence>' . htmlspecialchars($licence) . '</paperLicence>',
            ''
        );

        self::assertSame($linked ? [$licence] : [], $this->hrefs($html, $licence));
    }

    /**
     * @dataProvider stylesheetLicenceProvider
     */
    public function testRecordRightsAreLinkedOnlyWhenHttp(string $stylesheet, string $licence, bool $linked): void
    {
        $html = $this->renderStylesheet(
            $stylesheet,
            '',
            '<dc:rights>' . htmlspecialchars($licence) . '</dc:rights>'
        );

        self::assertSame($linked ? [$licence] : [], $this->hrefs($html, $licence));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function viewLicenceProvider(): array
    {
        return [
            'https licence' => ['https://creativecommons.org/licenses/by/4.0', true],
            'javascript licence' => ['javascript:alert(1)', false],
        ];
    }

    /**
     * @dataProvider viewLicenceProvider
     */
    public function testLicenceLinkViewIsLinkedOnlyWhenHttp(string $licence, bool $linked): void
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getLicence')->willReturn($licence);
        $paper->method('getDocid')->willReturn(42);

        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts');
        $html = $view->partial('paper/license-link.phtml', ['paper' => $paper]);

        $document = $this->load($html);
        $link = (new DOMXPath($document))->query('//a[@id="paper-license-link"]')->item(0);

        self::assertNotNull($link);
        self::assertSame($linked ? $licence : '', $link->getAttribute('href'));
    }

    private function renderStylesheet(string $stylesheet, string $episciences, string $dcElements): string
    {
        $record = new DOMDocument();
        $record->loadXML(
            '<record><episciences><id>42</id>' . $episciences . '</episciences><metadata>'
            . '<oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:title xml:lang="fr">Titre</dc:title>' . $dcElements
            . '</oai_dc:dc></metadata></record>'
        );

        $xsl = new DOMDocument();
        $xsl->load(APPLICATION_PATH . '/../public/xsl/' . $stylesheet);

        $processor = new XSLTProcessor();
        $processor->registerPHPFunctions();
        $processor->importStylesheet($xsl);

        return (string)$processor->transformToXml($record);
    }

    /**
     * @return string[] hrefs equal to the licence value
     */
    private function hrefs(string $html, string $licence): array
    {
        $hrefs = [];

        foreach ((new DOMXPath($this->load($html)))->query('//a[@href]') as $link) {
            if ($link->getAttribute('href') === $licence) {
                $hrefs[] = $link->getAttribute('href');
            }
        }

        return $hrefs;
    }

    private function load(string $html): DOMDocument
    {
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="utf-8"?>' . $html);

        return $document;
    }
}
