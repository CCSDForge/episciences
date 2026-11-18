<?php

declare(strict_types=1);

namespace unit\library\Episciences\View\Helper;

use Episciences\Reviewer\RequestType;
use Episciences\Reviewer\RequestTypeRepository;
use Episciences\View\Helper\RequestTypeLabel;
use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * @covers \Episciences\View\Helper\RequestTypeLabel
 */
final class RequestTypeLabelTest extends TestCase
{
    private const RVID = 3;

    private function helper(RequestTypeRepository $repository): RequestTypeLabel
    {
        $helper = new RequestTypeLabel($repository);
        $helper->setView(new Zend_View());

        return $helper;
    }

    public function testTheLabelIsEscapedAndInTheRequestedLanguage(): void
    {
        $repository = $this->createMock(RequestTypeRepository::class);
        $repository->expects(self::once())->method('findAllForJournal')->with(self::RVID)->willReturn([
            new RequestType(11, self::RVID, null, ['fr' => 'Phase <2>', 'en' => 'Phase "two"']),
        ]);
        $helper = $this->helper($repository);

        self::assertSame('Phase &lt;2&gt;', $helper->requestTypeLabel(11, self::RVID, 'fr'));
        // the types are read once
        self::assertSame('Phase &quot;two&quot;', $helper->requestTypeLabel('11', self::RVID, 'en'));
    }

    public function testNoTypeOrAnUnknownTypeGivesNoLabel(): void
    {
        $repository = $this->createMock(RequestTypeRepository::class);
        $repository->method('findAllForJournal')->willReturn([]);
        $helper = $this->helper($repository);

        self::assertSame('', $helper->requestTypeLabel(null, self::RVID, 'en'));
        self::assertSame('', $helper->requestTypeLabel(99, self::RVID, 'en'));
    }

    /**
     * The helper path is registered as in the application bootstrap
     */
    public function testTheHelperIsFoundByTheView(): void
    {
        $view = new Zend_View();
        $view->addHelperPath('Episciences/View/Helper', 'Episciences\\View\\Helper\\');

        self::assertInstanceOf(RequestTypeLabel::class, $view->getHelper('requestTypeLabel'));
    }
}
