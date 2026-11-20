<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use Episciences_Paper;
use Episciences_Paper_Dataset;
use Episciences_Repositories;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zend_Registry;
use Zend_View;

/**
 * Linked data of a paper (datasets and publications): every stored value is escaped.
 */
final class PaperLinkedDataViewsTest extends TestCase
{
    private const VALUE = 'a"b<i>c</i>';

    protected function setUp(): void
    {
        if (!Zend_Registry::isRegistered('metadataSources')) {
            Zend_Registry::set('metadataSources', []);
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function viewProvider(): array
    {
        return [
            'datasets' => ['paper/paper_datasets_datas.phtml', 'datasets'],
            'publications' => ['paper/paper_datasets_publications.phtml', 'publications'],
        ];
    }

    private function render(string $script, string $variable, string $link): string
    {
        $linkedData = (new Episciences_Paper_Dataset())
            ->setId(7)
            ->setCode('dataset" data-x="1')
            ->setName('url')
            ->setValue(self::VALUE)
            ->setLink($link)
            ->setRelationship('references" data-y="1');
        $linkedData->setSourceId((int)Episciences_Repositories::EPI_USER_ID);

        // the owner of an unpublished paper manages its linked data
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('isPublished')->willReturn(false);
        $paper->method('isOwner')->willReturn(true);

        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');
        $view->paper = $paper;
        $view->listSources = [];
        $view->$variable = ['references' => [Episciences_Repositories::EPI_USER_ID => [$linkedData]]];

        return $view->render($script);
    }

    #[DataProvider('viewProvider')]
    public function testTheStoredValuesAreEscaped(string $script, string $variable): void
    {
        $html = $this->render($script, $variable, 'https://example.org/?a=1&b=2');

        self::assertStringNotContainsString(self::VALUE, $html);
        self::assertStringNotContainsString('data-x="1"', $html);
        self::assertStringNotContainsString('data-y="1"', $html);
        self::assertStringContainsString('data-ldval="a&quot;b&lt;i&gt;c&lt;/i&gt;"', $html);
        self::assertStringContainsString('href="https://example.org/?a=1&amp;b=2"', $html);
    }

    #[DataProvider('viewProvider')]
    public function testALinkThatIsNotHttpIsNotRendered(string $script, string $variable): void
    {
        $html = $this->render($script, $variable, 'javascript:alert(1)');

        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringContainsString('a&quot;b&lt;i&gt;c&lt;/i&gt;', $html);
    }
}
