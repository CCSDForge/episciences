<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * Volume and section cells shared by the reviewer ratings list and the author paper list.
 */
final class PaperListVolumeSectionPartialTest extends TestCase
{
    private const SCRIPT = 'partials/paper_list_volume_section.phtml';

    /**
     * @param array<int, \Episciences_Volume> $volumes
     * @param array<int, \Episciences_Section> $sections
     */
    private function render(array $volumes, array $sections): string
    {
        $paper = $this->createMock(\Episciences_Paper::class);
        $paper->method('getVid')->willReturn(3);
        $paper->method('getSid')->willReturn(4);

        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');
        $view->addHelperPath(dirname(APPLICATION_PATH) . '/library/Episciences/View/Helper', 'Episciences_View_Helper_');
        $view->paper = $paper;
        $view->volumes = $volumes;
        $view->sections = $sections;

        return $view->render(self::SCRIPT);
    }

    /**
     * @return list<string>
     */
    private function cells(string $html): array
    {
        preg_match_all('#<td class="text-center">(.*?)</td>#s', $html, $matches);

        return $matches[1];
    }

    public function testRendersTheEscapedVolumeAndSectionNames(): void
    {
        $volume = $this->createMock(\Episciences_Volume::class);
        $volume->method('getNameKey')->willReturn('Volume <1>');
        $section = $this->createMock(\Episciences_Section::class);
        $section->method('getNameKey')->willReturn('Section & co');

        $cells = $this->cells($this->render([3 => $volume], [4 => $section]));

        self::assertSame(['Volume &lt;1&gt;', 'Section &amp; co'], $cells);
    }

    public function testRendersADashForAnUnknownVolumeOrSection(): void
    {
        $otherVolume = $this->createMock(\Episciences_Volume::class);
        $otherVolume->method('getNameKey')->willReturn('Other volume');

        $cells = $this->cells($this->render([9 => $otherVolume], []));

        self::assertSame(['-', '-'], $cells);
    }
}
