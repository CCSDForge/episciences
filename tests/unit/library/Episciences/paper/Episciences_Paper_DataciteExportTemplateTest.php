<?php

declare(strict_types=1);

namespace unit\library\Episciences\paper;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Episciences_Paper;
use Episciences_Review;
use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * Renders export/datacite.phtml (OpenAIRE Literature 4.0) and checks the relationType of the linked data:
 * DataCite 4.1 values in PascalCase, nothing for the relations OpenAIRE does not know.
 */
final class Episciences_Paper_DataciteExportTemplateTest extends TestCase
{
    private const string DATACITE_NS = 'http://datacite.org/schema/kernel-4';

    public function testLinkedDataAreExportedWithTheirDataCiteRelationType(): void
    {
        $relationTypes = $this->renderRelationTypes([
            '10.1/supplement' => 'isSupplementedBy',
            '10.1/reference' => 'references',
            '10.1/review' => 'hasReview',
            '10.1/reviewed' => 'isReviewOf',
            '10.1/derivation' => 'hasDerivation',
            '10.1/based-on' => 'isBasedOn',
            '10.1/legacy' => 'IsSupplementTo',
        ]);

        self::assertSame([
            '10.1/supplement' => 'IsSupplementedBy',
            '10.1/reference' => 'References',
            '10.1/review' => 'IsReviewedBy',
            '10.1/reviewed' => 'Reviews',
            '10.1/derivation' => 'IsSourceOf',
            '10.1/based-on' => 'IsDerivedFrom',
            '10.1/legacy' => 'IsSupplementTo',
        ], $relationTypes);
    }

    public function testRelationsUnknownToOpenAireAreLeftOut(): void
    {
        $relationTypes = $this->renderRelationTypes([
            '10.1/kept' => 'isPartOf',
            '10.1/translation' => 'isTranslationOf',
            '10.1/funding' => 'isFinancedBy',
            '10.1/related' => 'isRelatedMaterial',
            '10.1/replaced' => 'isReplacedBy',
        ]);

        self::assertSame(['10.1/kept' => 'IsPartOf'], $relationTypes);
    }

    /**
     * @param array<string, string> $relationships DOI => stored relationship
     * @return array<string, string> DOI => exported relationType of the linked data
     */
    private function renderRelationTypes(array $relationships): array
    {
        $linkedData = [];
        foreach ($relationships as $doi => $relationship) {
            $linkedData[] = ['value' => $doi, 'link' => 'doi', 'relationship' => $relationship];
        }

        $paper = $this->createMock(Episciences_Paper::class);
        $paper->method('getPaperid')->willReturn(42);
        $paper->method('getLinkedData')->willReturn($linkedData);
        $paper->method('getMetadata')->willReturn([]);

        $journal = $this->createMock(Episciences_Review::class);
        $journal->method('getUrl')->willReturn('https://journal.example.org');

        $view = new Zend_View();
        $view->setScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts');
        $view->addScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/export');
        $view->addHelperPath('Episciences/View/Helper', 'Episciences_View_Helper');
        $view->paper = $paper;
        $view->journal = $journal;
        $view->paperLanguage = 'en';
        $view->volume = '';
        $view->section = '';
        $view->doi = '10.46298/test.42';

        $dom = new DOMDocument();
        self::assertTrue(@$dom->loadXML($view->render('export/datacite.phtml')), 'The OpenAIRE export must be well-formed XML');

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('datacite', self::DATACITE_NS);

        $relationTypes = [];
        foreach ($xpath->query('//datacite:relatedIdentifier[@relatedIdentifierType="DOI"]') ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $relationTypes[trim($node->textContent)] = $node->getAttribute('relationType');
            }
        }

        return $relationTypes;
    }
}
