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
 * Linked data of a paper (datasets, publications and software): every stored value is escaped.
 */
final class PaperLinkedDataViewsTest extends TestCase
{
    private const VALUE = 'a"b<i>c</i>';
    private const EXTERNAL_SOURCE_ID = 999;

    private mixed $previousMetadataSources = null;
    private bool $hadMetadataSources = false;

    protected function setUp(): void
    {
        $this->hadMetadataSources = Zend_Registry::isRegistered('metadataSources');
        if ($this->hadMetadataSources) {
            $this->previousMetadataSources = Zend_Registry::get('metadataSources');
        }
        Zend_Registry::set('metadataSources', [
            self::EXTERNAL_SOURCE_ID => ['name' => 'Src<b>1</b>'],
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->hadMetadataSources) {
            Zend_Registry::set('metadataSources', $this->previousMetadataSources);
        } else {
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

    /**
     * @return array<string, array{string, string}>
     */
    public static function allViewsProvider(): array
    {
        return [
            'datasets' => ['paper/paper_datasets_datas.phtml', 'datasets'],
            'publications' => ['paper/paper_datasets_publications.phtml', 'publications'],
            'software' => ['paper/paper_datasets_software.phtml', 'softwareCollection'],
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
        self::assertStringNotContainsString('id="link-ld"', $html);
        self::assertStringContainsString('a&quot;b&lt;i&gt;c&lt;/i&gt;', $html);
    }

    #[DataProvider('allViewsProvider')]
    public function testCiteProcCallbacksAndSourceLabelsAreEscaped(string $script, string $variable): void
    {
        $cslJson = json_encode([
            'id' => 'item-1',
            'type' => 'article-journal',
            'title' => 'Sample work',
            'DOI' => '10.1234/a" onclick="alert(1)',
            'author' => [
                [
                    'family' => 'Doe',
                    'given' => 'Jane',
                    'ORCID' => 'http://orcid.org/0000-0001-2345-6789?x=1&y=2',
                ],
                [
                    'family' => 'Evil',
                    'given' => 'Mallory',
                    'ORCID' => 'javascript:alert(1)',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $linkedData = (new Episciences_Paper_Dataset())
            ->setId(8)
            ->setCode('dataset')
            ->setName('doi')
            ->setValue('10.1234/sample')
            ->setLink('https://doi.org/10.1234/sample')
            ->setRelationship('references');
        $linkedData->setMetatext($cslJson);
        $linkedData->setSourceId(self::EXTERNAL_SOURCE_ID);

        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('isPublished')->willReturn(true);
        $paper->method('isOwner')->willReturn(false);

        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/');
        $view->paper = $paper;
        $view->listSources = ['Src<b>1</b>' => '1<i>x</i>'];
        $view->$variable = ['references' => [self::EXTERNAL_SOURCE_ID => [$linkedData]]];

        $html = $view->render($script);

        self::assertStringContainsString('href="https://orcid.org/0000-0001-2345-6789?x=1&amp;y=2"', $html);
        self::assertStringContainsString('data-original-title="0000-0001-2345-6789?x=1&amp;y=2"', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringNotContainsString('onclick="alert(1)', $html);
        self::assertStringContainsString('href="https://doi.org/10.1234/a&quot; onclick=&quot;alert(1)"', $html);
        self::assertStringNotContainsString('Src<b>1</b>', $html);
        self::assertStringContainsString('Src&lt;b&gt;1&lt;/b&gt;', $html);
        self::assertStringNotContainsString('1<i>x</i>', $html);
        self::assertStringContainsString('1&lt;i&gt;x&lt;/i&gt;', $html);
    }
}
