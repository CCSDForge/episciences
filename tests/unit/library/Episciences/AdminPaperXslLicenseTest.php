<?php

declare(strict_types=1);

namespace unit\library\Episciences;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use XSLTProcessor;

/**
 * Licence block of the paper management page (admin_paper.xsl):
 * the "Modifier" button is on the same line as the licence name.
 */
final class AdminPaperXslLicenseTest extends TestCase
{
    private const CC_BY = 'https://creativecommons.org/licenses/by/4.0/';

    private mixed $previousLang = null;

    protected function setUp(): void
    {
        // French: the source strings are used as is, without logging missing translations
        $this->previousLang = \Zend_Registry::isRegistered('lang') ? \Zend_Registry::get('lang') : null;
        \Zend_Registry::set('lang', 'fr');
    }

    protected function tearDown(): void
    {
        \Zend_Registry::set('lang', $this->previousLang);
    }

    private function renderLicenseBlock(string $paperLicence, array $rights, string $canEditLicense = '1'): DOMElement
    {
        $dcRights = implode('', array_map(static fn(string $right) => '<dc:rights>' . htmlspecialchars($right) . '</dc:rights>', $rights));
        $paperLicence = $paperLicence !== '' ? '<paperLicence>' . htmlspecialchars($paperLicence) . '</paperLicence>' : '';

        $record = new DOMDocument();
        $record->loadXML(
            '<record><episciences><id>42</id>' . $paperLicence . '</episciences><metadata>'
            . '<oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . $dcRights . '</oai_dc:dc></metadata></record>'
        );

        $xsl = new DOMDocument();
        $xsl->load(APPLICATION_PATH . '/../public/xsl/admin_paper.xsl');

        $processor = new XSLTProcessor();
        $processor->registerPHPFunctions();
        $processor->importStylesheet($xsl);
        $processor->setParameter('', 'canEditLicense', $canEditLicense);

        $html = new DOMDocument();
        @$html->loadHTML('<?xml encoding="utf-8"?>' . $processor->transformToXml($record));

        $block = $html->getElementById('paper-license-block');
        self::assertInstanceOf(DOMElement::class, $block);

        return $block;
    }

    /**
     * @return DOMElement[] licence lines (div.small) of the block
     */
    private static function lines(DOMElement $block): array
    {
        $lines = (new DOMXPath($block->ownerDocument))->query('./div', $block);

        return $lines === false ? [] : iterator_to_array($lines);
    }

    private static function buttonsIn(DOMElement $line): int
    {
        return (new DOMXPath($line->ownerDocument))->query('.//button[contains(@class, "edit-license")]', $line)->length;
    }

    private static function assertEditButtonOnClick(DOMElement $line): void
    {
        $button = (new DOMXPath($line->ownerDocument))->query('.//button[contains(@class, "edit-license")]', $line)->item(0);

        self::assertInstanceOf(DOMElement::class, $button);
        self::assertSame('getLicensesForm(this, 42)', $button->getAttribute('onclick'));
    }

    public function testButtonIsOnTheSpdxLicenceLine(): void
    {
        $lines = self::lines($this->renderLicenseBlock(self::CC_BY, []));

        self::assertCount(1, $lines);
        self::assertSame(1, self::buttonsIn($lines[0]));
        self::assertSame(1, $lines[0]->getElementsByTagName('a')->length);
        self::assertEditButtonOnClick($lines[0]);
    }

    /**
     * Bug: with repository rights only, the button was rendered in its own line, below the licences.
     */
    public function testButtonIsOnTheLastRepositoryLicenceLine(): void
    {
        $lines = self::lines($this->renderLicenseBlock('', [
            'info:eu-repo/semantics/openAccess',
            self::CC_BY,
            'http://example.org/other-license',
        ]));

        self::assertCount(2, $lines, 'one line per licence, no extra line for the button');
        self::assertSame(0, self::buttonsIn($lines[0]));
        self::assertSame(1, self::buttonsIn($lines[1]));
        self::assertSame(1, $lines[1]->getElementsByTagName('a')->length);
        self::assertEditButtonOnClick($lines[1]);
    }

    public function testButtonAloneWhenThereIsNoLicence(): void
    {
        $lines = self::lines($this->renderLicenseBlock('', ['info:eu-repo/semantics/openAccess']));

        self::assertCount(1, $lines);
        self::assertSame(0, $lines[0]->getElementsByTagName('a')->length);
        self::assertEditButtonOnClick($lines[0]);
    }

    public function testNoButtonWithoutTheRight(): void
    {
        $block = $this->renderLicenseBlock('', [self::CC_BY], '0');

        self::assertCount(1, self::lines($block));
        self::assertSame(0, self::buttonsIn($block));
    }
}
