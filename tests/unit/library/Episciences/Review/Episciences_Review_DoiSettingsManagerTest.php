<?php

declare(strict_types=1);

namespace unit\library\Episciences\Review;

use Episciences_Review_DoiSettings;
use Episciences_Review_DoiSettingsManager;
use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * DOI settings form of the journal (doi/settings).
 *
 * @covers Episciences_Review_DoiSettingsManager
 */
final class Episciences_Review_DoiSettingsManagerTest extends TestCase
{
    /**
     * @dataProvider prefixProvider
     */
    public function testPrefixFormat(string $prefix, bool $valid): void
    {
        $element = Episciences_Review_DoiSettingsManager::getSettingsForm()
            ->getElement(Episciences_Review_DoiSettings::SETTING_DOI_PREFIX);

        self::assertSame($valid, $element->isValid($prefix));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function prefixProvider(): array
    {
        return [
            'empty: no DOI' => ['', true],
            'five digits' => ['10.46298', true],
            'four digits' => ['10.5281', true],
            'subdivided registrant' => ['10.1000.10', true],
            'surrounding spaces' => [' 10.46298 ', true],
            'with a suffix' => ['10.46298/jtcam', false],
            'URL' => ['https://doi.org/10.46298', false],
            'not starting with 10.' => ['11.46298', false],
            'too short' => ['10.12', false],
        ];
    }

    public function testFormatIsRequired(): void
    {
        $element = Episciences_Review_DoiSettingsManager::getSettingsForm()
            ->getElement(Episciences_Review_DoiSettings::SETTING_DOI_FORMAT);

        self::assertFalse($element->isValid(''));
        self::assertTrue($element->isValid('%R%-%P%'));
    }

    private function renderFormat(string $format): string
    {
        $form = Episciences_Review_DoiSettingsManager::getSettingsForm();
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');
        $form->setView($view);
        $element = $form->getElement(Episciences_Review_DoiSettings::SETTING_DOI_FORMAT);
        $element->setValue($format);

        return $element->render($view);
    }

    public function testTheSavedFormatIsEscaped(): void
    {
        $html = $this->renderFormat('%R%"><b>x</b>');

        self::assertStringContainsString('value="%R%&quot;&gt;&lt;b&gt;x&lt;/b&gt;"', $html);
        self::assertStringNotContainsString('<b>x</b>', $html);
    }

    public function testWithoutFormatTheDefaultOneIsShown(): void
    {
        self::assertStringContainsString(
            'value="' . Episciences_Review_DoiSettings::SETTING_DOI_DEFAULT_DOI_FORMAT . '"',
            $this->renderFormat('')
        );
    }

    public function testEveryTagIsListed(): void
    {
        $html = $this->renderFormat('%R%-%P%');

        foreach ([
            Episciences_Review_DoiSettings::DOI_FORMAT_REVIEW_CODE,
            Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_VOLUME,
            Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_VOLUME_INT,
            Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_VOLUME_INT_REPLACEMENT_CHAR,
            Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_VOLUME_BIB_REF,
            Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_VOLUME_ORDER,
            Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_SECTION,
            Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_SECTION_INT,
            Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_SECTION_INT_REPLACEMENT_CHAR,
            Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_ID,
            Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_YEAR,
            Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_YEAR_SHORT,
            Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_MONTH,
            Episciences_Review_DoiSettings::DOI_FORMAT_PAPER_AUTOINCREMENT,
        ] as $tag) {
            self::assertStringContainsString('<code>' . $tag . '</code>', $html);
        }
    }
}
