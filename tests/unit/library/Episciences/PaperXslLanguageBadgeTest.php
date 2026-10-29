<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use XSLTProcessor;
use Zend_Registry;
use Zend_Translate;

/**
 * The language of each description (and of each keyword group on the full paper
 * page) is shown as a badge naming the language in the interface language,
 * instead of a bare "[fr]" code.
 */
final class PaperXslLanguageBadgeTest extends TestCase
{
    private const BADGE_XPATH = '//span[@class="badge rounded-pill text-bg-light"]';

    /** @var array<string, mixed> registry entries present before the test */
    private array $previousRegistry = [];

    protected function setUp(): void
    {
        $this->previousRegistry = [];

        foreach (['Zend_Translate', 'lang'] as $key) {
            if (Zend_Registry::isRegistered($key)) {
                $this->previousRegistry[$key] = Zend_Registry::get($key);
            }
        }

        Zend_Registry::set('lang', 'fr');
    }

    protected function tearDown(): void
    {
        foreach (['Zend_Translate', 'lang'] as $key) {
            if (array_key_exists($key, $this->previousRegistry)) {
                Zend_Registry::set($key, $this->previousRegistry[$key]);
            } elseif (Zend_Registry::isRegistered($key)) {
                Zend_Registry::getInstance()->offsetUnset($key);
            }
        }
    }

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
    public function testDescriptionLanguagesAreNamedInFrenchInterface(string $stylesheet): void
    {
        $html = $this->render($stylesheet, 'fr', $this->descriptions());

        self::assertSame(['Français', 'Anglais'], $this->badges($html));
        self::assertStringNotContainsString('[fr]', $html);
        self::assertStringNotContainsString('[en]', $html);
    }

    /**
     * @dataProvider stylesheetProvider
     */
    public function testDescriptionLanguagesAreNamedInEnglishInterface(string $stylesheet): void
    {
        $html = $this->render($stylesheet, 'en', $this->descriptions());

        self::assertSame(['French', 'English'], $this->badges($html));
    }

    /**
     * @dataProvider stylesheetProvider
     */
    public function testSingleDescriptionHasNoBadge(string $stylesheet): void
    {
        $html = $this->render($stylesheet, 'fr', '<dc:description xml:lang="fr">Résumé</dc:description>');

        self::assertSame([], $this->badges($html));
    }

    public function testKeywordGroupsAreNamedOnFullPaper(): void
    {
        $html = $this->render(
            'full_paper.xsl',
            'en',
            '<dc:subject xml:lang="fr">revues</dc:subject><dc:subject xml:lang="en">journals</dc:subject>'
        );

        // Keyword groups are sorted by language code: "en" before "fr".
        self::assertSame(['English', 'French'], $this->badges($html));
    }

    private function descriptions(): string
    {
        return '<dc:description xml:lang="fr">Résumé</dc:description>'
            . '<dc:description xml:lang="eng">Abstract</dc:description>';
    }

    private function render(string $stylesheet, string $locale, string $dcElements): string
    {
        $translate = new Zend_Translate([
            'adapter' => 'array',
            'content' => ['' => ''],
            'locale' => $locale,
            'disableNotices' => true,
        ]);
        Zend_Registry::set('Zend_Translate', $translate);

        $record = new DOMDocument();
        $record->loadXML(
            '<record><episciences><id>42</id></episciences><metadata>'
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
     * @return string[]
     */
    private function badges(string $html): array
    {
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="utf-8"?>' . $html);

        $badges = [];

        foreach ((new DOMXPath($document))->query(self::BADGE_XPATH) as $badge) {
            $badges[] = $badge->textContent;
        }

        return $badges;
    }
}
