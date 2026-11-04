<?php

declare(strict_types=1);

namespace unit\library\Episciences\Paper\Funding;

use Episciences\Paper\Funding\FundingReferenceFactory;
use Episciences\Paper\Funding\HalTeiFundingParser;
use PHPUnit\Framework\TestCase;

/**
 * Fixtures are excerpts of HAL search API TEI (wt=xml-tei), same structure as the real documents.
 */
final class HalTeiFundingParserTest extends TestCase
{
    private static function tei(string $funders, string $projects): string
    {
        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<TEI xmlns="http://www.tei-c.org/ns/1.0" xmlns:hal="http://hal.archives-ouvertes.fr/">
  <text>
    <body>
      <listBibl>
        <biblFull>
          <titleStmt>
            <title xml:lang="en">A paper</title>
            {$funders}
          </titleStmt>
        </biblFull>
      </listBibl>
    </body>
    <back>
      <listOrg type="structures">
        <org type="laboratory" xml:id="struct-1612949" status="VALID">
          <idno type="ROR">00qhm9960</idno>
          <orgName>Geroscience and rejuvenation research center</orgName>
        </org>
      </listOrg>
      {$projects}
    </back>
  </text>
</TEI>
XML;
    }

    public function testAnrProjectAndFreeTextFundings(): void
    {
        $tei = self::tei(
            '<funder ref="#projanr-72306"/>
             <funder>Défi Clé CeBBOc program of Occitanie Region</funder>
             <funder>ANR-22-CPJ1-0090-0</funder>',
            '<listOrg type="projects">
               <org type="anrProject" xml:id="projanr-72306" status="VALID">
                 <idno type="anr">ANR-22-CE45-0024</idno>
                 <orgName>ENERGENCE</orgName>
                 <desc>Modélisation ENERgétique de l\'émerGENCE et de l\'homéorhésie de l\'architecture tissulaire</desc>
                 <date type="start">2022</date>
               </org>
             </listOrg>'
        );

        self::assertSame([
            [
                'projectTitle' => "Modélisation ENERgétique de l'émerGENCE et de l'homéorhésie de l'architecture tissulaire",
                'acronym' => 'ENERGENCE',
                'funderName' => 'French National Research Agency (ANR)',
                'code' => 'ANR-22-CE45-0024',
            ],
            ['fundingStatement' => 'Défi Clé CeBBOc program of Occitanie Region'],
            ['fundingStatement' => 'ANR-22-CPJ1-0090-0'],
        ], HalTeiFundingParser::parse($tei));
    }

    public function testEuropeanProject(): void
    {
        $tei = self::tei(
            '<funder ref="#projeurop-132777"/>',
            '<listOrg type="projects">
               <org type="europeanProject" xml:id="projeurop-132777" status="VALID">
                 <idno type="number">637503</idno>
                 <idno type="program">ERC-2014-STG</idno>
                 <idno type="call">ERC-2014-STG</idno>
                 <orgName>PRISTINE</orgName>
                 <desc>High precision isotopic measurements of heavy elements
                   in extra-terrestrial materials</desc>
                 <date type="start">2015-04-01</date>
                 <date type="end">2021-09-30</date>
               </org>
             </listOrg>'
        );

        self::assertSame([[
            'projectTitle' => 'High precision isotopic measurements of heavy elements in extra-terrestrial materials',
            'acronym' => 'PRISTINE',
            'funderName' => 'European Commission',
            'code' => '637503',
            'callId' => 'ERC-2014-STG',
            'projectFinancing' => 'ERC-2014-STG',
        ]], HalTeiFundingParser::parse($tei));
    }

    public function testOrderFollowsTheFunderElementsAndUnknownReferencesAreSkipped(): void
    {
        $tei = self::tei(
            '<funder ref="#projeurop-2"/><funder ref="#projanr-404"/><funder ref="#projanr-1"/>',
            '<listOrg type="projects">
               <org type="anrProject" xml:id="projanr-1"><idno type="anr">ANR-10-LABX-0023</idno><orgName>UnivEarthS</orgName></org>
               <org type="europeanProject" xml:id="projeurop-2"><idno type="number">101017452</idno></org>
             </listOrg>'
        );

        $codes = array_column(HalTeiFundingParser::parse($tei), 'code');

        self::assertSame(['101017452', 'ANR-10-LABX-0023'], $codes);
    }

    public function testMissingValuesAreLeftOut(): void
    {
        $tei = self::tei(
            '<funder ref="#projanr-1"/><funder>   </funder>',
            '<listOrg type="projects"><org type="anrProject" xml:id="projanr-1"><idno type="anr">ANR-10-LABX-0023</idno></org></listOrg>'
        );

        self::assertSame(
            [['funderName' => 'French National Research Agency (ANR)', 'code' => 'ANR-10-LABX-0023']],
            HalTeiFundingParser::parse($tei)
        );
    }

    public function testNoFundingOrUnreadableTei(): void
    {
        self::assertSame([], HalTeiFundingParser::parse(self::tei('', '')));
        self::assertSame([], HalTeiFundingParser::parse(''));
        self::assertSame([], HalTeiFundingParser::parse('<TEI><not closed'));
    }

    public function testParsedFundingsGoThroughTheFactory(): void
    {
        $tei = self::tei(
            '<funder ref="#projanr-72306"/><funder>ANR-22-CPJ1-0090-0</funder>',
            '<listOrg type="projects"><org type="anrProject" xml:id="projanr-72306"><idno type="anr">ANR-22-CE45-0024</idno><orgName>ENERGENCE</orgName></org></listOrg>'
        );

        $references = FundingReferenceFactory::fromStoredList(HalTeiFundingParser::parse($tei));

        self::assertSame(['ANR-22-CE45-0024', 'ANR-22-CPJ1-0090-0'], array_map(
            static fn($reference) => $reference->awardNumber,
            $references
        ));
        self::assertSame('https://doi.org/10.13039/501100001665', $references[1]->funderCrossrefId);
    }
}
