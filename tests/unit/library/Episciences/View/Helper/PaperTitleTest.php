<?php

declare(strict_types=1);

namespace unit\library\Episciences\View\Helper;

use Episciences\View\Helper\PaperTitle;
use Episciences_Paper;
use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * @covers \Episciences\View\Helper\PaperTitle
 */
final class PaperTitleTest extends TestCase
{
    private function render(string $title, ?int $length = null): string
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getTitle')->willReturn($title);

        $view = new Zend_View();
        $view->addHelperPath(dirname(APPLICATION_PATH) . '/library/Episciences/View/Helper', 'Episciences\\View\\Helper\\');

        return $length === null ? $view->paperTitle($paper) : $view->paperTitle($paper, $length);
    }

    /**
     * The helper path is registered as in the application bootstrap, after the legacy one.
     */
    public function testTheHelperIsFoundByTheView(): void
    {
        $view = new Zend_View();
        $view->addHelperPath('Episciences/View/Helper', 'Episciences_View_Helper');
        $view->addHelperPath('Episciences/View/Helper', 'Episciences\\View\\Helper\\');

        self::assertInstanceOf(PaperTitle::class, $view->getHelper('paperTitle'));
    }

    public function testEscapesTheTitle(): void
    {
        self::assertSame('&lt;img src=x onerror=alert(1)&gt;', $this->render('<img src=x onerror=alert(1)>'));
    }

    public function testShortTitleIsKeptAsItIs(): void
    {
        self::assertSame('A short title', $this->render('A short title'));
    }

    public function testCutsLongTitlesToTheDefaultLength(): void
    {
        $title = str_repeat('a', PaperTitle::DEFAULT_LENGTH + 10);

        self::assertSame(str_repeat('a', PaperTitle::DEFAULT_LENGTH) . '...', $this->render($title));
    }

    public function testCutsLongTitlesToTheGivenLength(): void
    {
        self::assertSame('abc...', $this->render('abcdef', 3));
    }

    public function testCutsTheTitleBeforeEscapingItSoThatNoEntityIsCut(): void
    {
        self::assertSame('ab&amp;...', $this->render('ab&cd', 3));
    }

    public function testCountsMultibyteCharactersAsOneCharacter(): void
    {
        self::assertSame('éèà...', $this->render('éèàù', 3));
    }
}
