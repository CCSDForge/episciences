<?php

declare(strict_types=1);

namespace unit\modules\journal\views;

use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * Titles, abstracts, author, volume and section names shown on the public listing pages
 * come from repository records indexed in Solr: the rendered pages must escape them.
 */
final class PublicViewsOutputEscapingTest extends TestCase
{
    private const SCRIPTS = '/modules/journal/views/scripts/';

    private const PAYLOAD = '<img src=x onerror=alert(1)>';

    private const ESCAPED_PAYLOAD = '&lt;img src=x onerror=alert(1)&gt;';

    protected function setUp(): void
    {
        if (!defined('ABSTRACT_MAX_LENGTH')) {
            define('ABSTRACT_MAX_LENGTH', 300);
        }

        $translate = new \Zend_Translate(['adapter' => 'array', 'content' => ['x' => 'x'], 'locale' => 'fr']);
        \Zend_Registry::set('Zend_Translate', $translate);
    }

    private function view(): Zend_View
    {
        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . self::SCRIPTS);

        return $view;
    }

    /**
     * @return array<string, mixed>
     */
    private function indexedDoc(): array
    {
        $locale = \Episciences_Tools::getLocale();

        return [
            'docid' => 12,
            'language_s' => 'en',
            'paper_title_t' => [self::PAYLOAD . ' title'],
            'abstract_t' => [self::PAYLOAD . ' abstract'],
            'author_fullname_s' => ['Doe, Jane ' . self::PAYLOAD . '_FacetSep_Doe'],
            $locale . '_section_title_t' => self::PAYLOAD . ' section',
            $locale . '_volume_title_t' => self::PAYLOAD . ' volume',
            'volume_id_i' => 3,
            'section_id_i' => 4,
        ];
    }

    private function assertEscaped(string $html): void
    {
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString(self::ESCAPED_PAYLOAD, $html);
    }

    public function testLatestPublicationsEscapesEveryIndexedValue(): void
    {
        $view = $this->view();
        $view->articles = ['response' => ['docs' => [$this->indexedDoc()]]];

        $html = $view->render('browse/latest.phtml');

        $this->assertEscaped($html);
        self::assertStringContainsString(self::ESCAPED_PAYLOAD . ' volume', $html);
        self::assertStringContainsString(self::ESCAPED_PAYLOAD . ' section', $html);
        self::assertStringContainsString(self::ESCAPED_PAYLOAD . ' abstract', $html);
    }

    public function testLatestPublicationsToleratesMultivaluedVolumeAndSection(): void
    {
        $locale = \Episciences_Tools::getLocale();
        $doc = $this->indexedDoc();
        $doc[$locale . '_volume_title_t'] = ['Volume A', 'Volume B'];
        $doc[$locale . '_section_title_t'] = ['Section A'];

        $view = $this->view();
        $view->articles = ['response' => ['docs' => [$doc]]];

        $html = $view->render('browse/latest.phtml');

        self::assertStringContainsString('Volume A', $html);
        self::assertStringContainsString('Section A', $html);
    }

    public function testSearchResultsEscapesEveryIndexedValue(): void
    {
        $locale = \Episciences_Tools::getLocale();
        $doc = new \ArrayObject($this->indexedDoc(), \ArrayObject::ARRAY_AS_PROPS);
        $doc->{$locale . '_volume_title_t'} = self::PAYLOAD . ' volume';

        $view = $this->view();
        $view->results = [$doc];
        $view->numFound = 1;
        $view->parsedSearchParams = [];
        $view->paginatordefaultNumberOfResults = 10;

        $html = $view->render('partials/search_results.phtml');

        $this->assertEscaped($html);
        self::assertStringContainsString(self::ESCAPED_PAYLOAD . ' volume', $html);
        self::assertStringContainsString(self::ESCAPED_PAYLOAD . ' section', $html);
    }

    public function testVolumePageEscapesEveryIndexedValue(): void
    {
        $view = $this->view();
        $view->indexedPapers = [$this->indexedDoc()];

        $html = $view->render('volume/volume-indexed-papers.phtml');

        $this->assertEscaped($html);
        self::assertStringContainsString(self::ESCAPED_PAYLOAD . ' section', $html);
        self::assertStringContainsString(self::ESCAPED_PAYLOAD . ' title', $html);
    }

    public function testSectionPageEscapesEveryIndexedValue(): void
    {
        $section = new class ($this->indexedDoc()) {
            /** @param array<string, mixed> $doc */
            public function __construct(private readonly array $doc)
            {
            }

            public function getNameKey(): string
            {
                return 'section_1_title';
            }

            public function getDescriptionKey(): string
            {
                return 'section_1_description';
            }

            /** @return array<int, mixed> */
            public function getIndexedPapers(): array
            {
                return [$this->doc];
            }

            /** @return array<int, mixed> */
            public function getEditors(): array
            {
                return [];
            }
        };

        $view = $this->view();
        $view->section = $section;

        $html = $view->render('section/view.phtml');

        $this->assertEscaped($html);
        self::assertStringContainsString(self::ESCAPED_PAYLOAD . ' title', $html);
        self::assertStringContainsString(self::ESCAPED_PAYLOAD . ' abstract', $html);
    }

    public function testTruncationHappensBeforeEscapingAndNeverCutsAnEntity(): void
    {
        $doc = $this->indexedDoc();
        $max = (int) ABSTRACT_MAX_LENGTH;
        $doc['abstract_t'] = [str_repeat('&', $max + 100)];

        $view = $this->view();
        $view->indexedPapers = [$doc];

        $html = $view->render('volume/volume-indexed-papers.phtml');

        self::assertStringContainsString(str_repeat('&amp;', $max), $html);
        self::assertStringNotContainsString(str_repeat('&amp;', $max + 1), $html);
        self::assertStringNotContainsString('&amp;amp;', $html);
    }
}
