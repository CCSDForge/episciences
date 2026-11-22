<?php

namespace unit\library\Episciences\paper;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Episciences_Paper;
use Episciences_Review;
use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * Renders export/crossref.phtml and checks that the output is well-formed XML
 * with values encoded exactly once.
 */
final class Episciences_Paper_CrossrefExportTemplateTest extends TestCase
{
    private const CROSSREF_NS = 'http://www.crossref.org/schema/5.3.1';
    private const JATS_NS = 'http://www.ncbi.nlm.nih.gov/JATS1';
    private const RELATIONS_NS = 'http://www.crossref.org/relations.xsd';

    public function testResourceUrlsContainingAmpersandProduceWellFormedXml(): void
    {
        $pdfUrl = 'https://zenodo.org/records/1/files/Author-&-Author-2026.pdf?download=1&x=<y>';

        $xpath = $this->render($pdfUrl, 'https://journal.example.org?a=1&b=2', []);

        $resources = $xpath->query('//c:doi_data//c:resource');
        self::assertSame(3, $resources->length);
        self::assertSame('https://journal.example.org?a=1&b=2/42', $resources->item(0)->textContent);
        self::assertSame($pdfUrl, $resources->item(1)->textContent);
        self::assertSame($pdfUrl, $resources->item(2)->textContent);
    }

    public function testAbstractIsEncodedOnlyOnce(): void
    {
        $abstract = 'Graphs & trees: a <b>bound</b> on "x"';

        $xpath = $this->render('https://example.org/paper.pdf', 'https://journal.example.org', [
            ['en' => '  ' . $abstract . '  '],
        ]);

        $paragraphs = $xpath->query('//jats:abstract/jats:p');
        self::assertSame(1, $paragraphs->length);
        $paragraph = $paragraphs->item(0);
        self::assertInstanceOf(DOMElement::class, $paragraph);
        self::assertSame($abstract, $paragraph->textContent);
        self::assertSame('en', $paragraph->getAttribute('xml:lang'));
    }

    /**
     * Legacy rows stored with a DataCite relation type are exported in the Crossref vocabulary
     */
    public function testLinkedDataRelationsAreExportedInTheCrossrefVocabulary(): void
    {
        $relationships = [
            '10.1/review' => 'IsReviewedBy',
            '10.1/source' => 'IsSourceOf',
            '10.1/cites' => 'Cites',
            '10.1/version' => 'IsNewVersionOf',
            '10.1/related' => 'IsRelatedTo',
            '10.1/stored' => 'isSupplementedBy',
            '10.1/none' => null,
            '10.1/unknown' => 'IsWhatever',
        ];
        $linkedData = [];
        foreach ($relationships as $doi => $relationship) {
            $linkedData[] = ['value' => $doi, 'link' => 'doi', 'relationship' => $relationship];
        }

        $xpath = $this->render('https://example.org/paper.pdf', 'https://journal.example.org', [], $linkedData);

        $exported = [];
        foreach ($xpath->query('//r:related_item/*') ?: [] as $relation) {
            if ($relation instanceof DOMElement) {
                $exported[trim($relation->textContent)] = [$relation->localName, $relation->getAttribute('relationship-type')];
            }
        }

        self::assertSame([
            '10.1/review' => ['inter_work_relation', 'hasReview'],
            '10.1/source' => ['inter_work_relation', 'hasDerivation'],
            '10.1/cites' => ['inter_work_relation', 'references'],
            '10.1/version' => ['intra_work_relation', 'isVersionOf'],
            '10.1/related' => ['inter_work_relation', 'isRelatedMaterial'],
            '10.1/stored' => ['inter_work_relation', 'isSupplementedBy'],
            '10.1/none' => ['inter_work_relation', 'references'],
            '10.1/unknown' => ['inter_work_relation', 'references'],
        ], $exported);
    }

    /**
     * @param array<int, array<string, string>> $abstracts
     * @param list<array<string, string|null>> $linkedData
     */
    private function render(string $pdfUrl, string $journalUrl, array $abstracts, array $linkedData = []): DOMXPath
    {
        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getMainPaperUrl')->willReturn($pdfUrl);
        $paper->method('getLinkedData')->willReturn($linkedData);
        $paper->method('getPaperid')->willReturn(42);
        $paper->method('getMetadata')->willReturn([]);

        $journal = $this->createMock(Episciences_Review::class);
        $journal->method('getUrl')->willReturn($journalUrl);

        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts');
        // Same setup as Episciences_Paper_XmlExportManager::xmlExport(): partials are rendered by bare name
        $view->addScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/export');
        $view->addHelperPath('Episciences/View/Helper', 'Episciences_View_Helper');
        $view->paper = $paper;
        $view->journal = $journal;
        $view->paperLanguage = 'en';
        $view->titles = [];
        $view->abstracts = $abstracts;
        $view->volume = '';
        $view->proceedingInfo = '';
        $view->section = '';
        $view->doi = '10.46298/test.42';
        $view->previousVersionsUrl = [];

        $output = $view->render('export/crossref.phtml');

        $dom = new DOMDocument();
        self::assertTrue(@$dom->loadXML($output), 'The Crossref export must be well-formed XML');

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('c', self::CROSSREF_NS);
        $xpath->registerNamespace('jats', self::JATS_NS);
        $xpath->registerNamespace('r', self::RELATIONS_NS);

        return $xpath;
    }
}
