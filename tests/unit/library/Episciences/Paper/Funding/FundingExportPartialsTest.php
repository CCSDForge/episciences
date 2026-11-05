<?php

declare(strict_types=1);

namespace unit\library\Episciences\Paper\Funding;

use DOMDocument;
use DOMXPath;
use Episciences\Paper\Funding\FundingReference;
use Episciences\Paper\Funding\FundingReferenceFactory;
use PHPUnit\Framework\TestCase;
use Zend_View;

/**
 * Renders the funding partials of the Crossref and OpenAIRE exports.
 */
final class FundingExportPartialsTest extends TestCase
{
    private const OAIRE_NS = 'http://namespace.openaire.eu/schema/oaire/';
    private const FUNDREF_NS = 'http://www.crossref.org/fundref.xsd';

    /**
     * @param list<FundingReference> $references
     */
    private function render(string $script, array $references): string
    {
        $view = new Zend_View();
        $view->addScriptPath(APPLICATION_PATH . '/modules/journal/views/scripts/export/');

        return $view->partial($script, ['fundingReferences' => $references]);
    }

    /**
     * @return list<FundingReference>
     */
    private function sampleReferences(): array
    {
        return FundingReferenceFactory::fromStoredList([
            [
                'projectTitle' => 'Modélisation ENERgétique & homéorhésie',
                'acronym' => 'ENERGENCE',
                'funderName' => 'French National Research Agency (ANR)',
                'code' => 'ANR-22-CE45-0024',
            ],
            [
                'projectTitle' => 'OpenAIRE-Nexus',
                'funderName' => 'European Commission',
                'code' => '101017452',
                'openaireId' => 'corda__h2020::2f32bdaa3c76066bd8267f3ac90ba898',
                'grantDoi' => '10.3030/101017452',
            ],
            ['funderName' => 'Unknown Regional Funder'],
            ['fundingStatement' => 'Défi Clé CeBBOc program of Occitanie Region'],
            ['projectTitle' => 'Project without funder', 'code' => 'X-1'],
        ]);
    }

    private function xpath(string $xml, string $prefix, string $namespace, string $wrapper): DOMXPath
    {
        $dom = new DOMDocument();
        self::assertTrue($dom->loadXML($wrapper === '' ? $xml : sprintf($wrapper, $xml)), 'well-formed XML');
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace($prefix, $namespace);

        return $xpath;
    }

    public function testCrossrefFundrefHasOneFundgroupPerFunderWithNestedIdentifier(): void
    {
        $xml = $this->render('fundref.phtml', $this->sampleReferences());
        $xpath = $this->xpath($xml, 'fr', self::FUNDREF_NS, '');

        self::assertSame(1, $xpath->query('/fr:program[@name="fundref"]')->length);
        self::assertSame(3, $xpath->query('//fr:assertion[@name="fundgroup"]')->length, 'statement-only and funder-less fundings are left out');

        $anrName = $xpath->query('//fr:assertion[@name="fundgroup"][1]/fr:assertion[@name="funder_name"]')->item(0);
        self::assertNotNull($anrName);
        self::assertSame('French National Research Agency (ANR)', trim($anrName->firstChild->nodeValue));
        self::assertSame(
            'https://doi.org/10.13039/501100001665',
            $xpath->evaluate('string(//fr:assertion[@name="fundgroup"][1]/fr:assertion[@name="funder_name"]/fr:assertion[@name="funder_identifier"])')
        );
        self::assertSame(
            'ANR-22-CE45-0024',
            $xpath->evaluate('string(//fr:assertion[@name="fundgroup"][1]/fr:assertion[@name="award_number"])')
        );

        // unknown funder: name only, no identifier, no award
        self::assertSame(0, $xpath->query('//fr:assertion[@name="fundgroup"][3]//fr:assertion[@name="funder_identifier"]')->length);
        self::assertSame(0, $xpath->query('//fr:assertion[@name="fundgroup"][3]/fr:assertion[@name="award_number"]')->length);

        // only the names allowed by the 5.3.1 fundref schema
        foreach ($xpath->query('//fr:assertion/@name') as $name) {
            self::assertContains($name->nodeValue, ['fundgroup', 'funder_name', 'funder_identifier', 'award_number']);
        }
    }

    public function testCrossrefFundrefIsEmptyWithoutAnyFunder(): void
    {
        $references = FundingReferenceFactory::fromStoredList([
            ['fundingStatement' => 'Some regional programme'],
        ]);

        self::assertSame('', trim($this->render('fundref.phtml', $references)));
    }

    public function testOpenAireHasASingleFundingReferencesWithAllFields(): void
    {
        $xml = $this->render('openaire-funding-references.phtml', $this->sampleReferences());
        $xpath = $this->xpath($xml, 'oaire', self::OAIRE_NS, '<root xmlns:oaire="' . self::OAIRE_NS . '">%s</root>');

        self::assertSame(1, $xpath->query('//oaire:fundingReferences')->length);
        self::assertSame(3, $xpath->query('//oaire:fundingReference')->length);
        self::assertSame(3, $xpath->query('//oaire:fundingReference/oaire:funderName')->length, 'funderName is mandatory');

        $ec = '//oaire:fundingReference[2]';
        self::assertSame('Crossref Funder ID', $xpath->evaluate("string($ec/oaire:funderIdentifier/@funderIdentifierType)"));
        self::assertSame('https://doi.org/10.13039/501100000780', $xpath->evaluate("string($ec/oaire:funderIdentifier)"));
        self::assertSame('Horizon 2020 Framework Programme', $xpath->evaluate("string($ec/oaire:fundingStream)"));
        self::assertSame('101017452', $xpath->evaluate("string($ec/oaire:awardNumber)"));
        self::assertSame('https://doi.org/10.3030/101017452', $xpath->evaluate("string($ec/oaire:awardNumber/@awardURI)"));
        self::assertSame('OpenAIRE-Nexus', $xpath->evaluate("string($ec/oaire:awardTitle)"));

        self::assertSame('Modélisation ENERgétique & homéorhésie', $xpath->evaluate('string(//oaire:fundingReference[1]/oaire:awardTitle)'), 'escaped and decoded back');
        self::assertSame(0, $xpath->query('//oaire:fundingReference[3]/oaire:funderIdentifier')->length);
    }

    public function testOpenAireIsEmptyWithoutAnyFunder(): void
    {
        self::assertSame('', trim($this->render('openaire-funding-references.phtml', [])));
    }

    public function testOpenAireNeverUsesTheRorFunderIdentifierType(): void
    {
        // funderIdentifierType of OpenAIRE Literature v4: ISNI, GRID, Crossref Funder ID, Other
        $references = [new FundingReference(funderName: 'ROR-only funder', funderRor: 'https://ror.org/0000000')];

        $xml = $this->render('openaire-funding-references.phtml', $references);
        $xpath = $this->xpath($xml, 'oaire', self::OAIRE_NS, '<root xmlns:oaire="' . self::OAIRE_NS . '">%s</root>');

        self::assertSame(1, $xpath->query('//oaire:fundingReference')->length);
        self::assertSame(0, $xpath->query('//oaire:funderIdentifier')->length);
    }
}
