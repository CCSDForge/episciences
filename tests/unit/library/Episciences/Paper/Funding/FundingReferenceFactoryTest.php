<?php

declare(strict_types=1);

namespace unit\library\Episciences\Paper\Funding;

use Episciences\Paper\Funding\FundingReference;
use Episciences\Paper\Funding\FundingReferenceFactory;
use PHPUnit\Framework\TestCase;

final class FundingReferenceFactoryTest extends TestCase
{
    public function testOpenAireEntryGetsIdentifiersStreamAndGrantDoi(): void
    {
        $references = FundingReferenceFactory::fromStoredList([[
            'projectTitle' => 'OpenAIRE-Nexus Scholarly Communication Services for EOSC users',
            'acronym' => 'OpenAIRE Nexus',
            'code' => '101017452',
            'funderName' => 'European Commission',
            'openaireId' => 'corda__h2020::2f32bdaa3c76066bd8267f3ac90ba898',
            'grantDoi' => '10.3030/101017452',
        ]]);

        self::assertCount(1, $references);
        $reference = $references[0];
        self::assertSame('European Commission', $reference->funderName);
        self::assertSame('https://doi.org/10.13039/501100000780', $reference->funderCrossrefId);
        self::assertSame('https://ror.org/00k4n6c32', $reference->funderRor);
        self::assertSame('Horizon 2020 Framework Programme', $reference->fundingStream);
        self::assertSame('101017452', $reference->awardNumber);
        self::assertSame('OpenAIRE-Nexus Scholarly Communication Services for EOSC users', $reference->awardTitle);
        self::assertSame('OpenAIRE Nexus', $reference->awardAcronym);
        self::assertSame('10.3030/101017452', $reference->grantDoi);
        self::assertSame('https://doi.org/10.3030/101017452', $reference->awardUri);
    }

    public function testHalEuropeanEntryGetsStreamFromFinancingAndCordisUri(): void
    {
        $references = FundingReferenceFactory::fromStoredList([[
            'projectTitle' => 'Multisensory-Based Spatial Sound',
            'acronym' => 'MULTISSOUND',
            'funderName' => 'European Commission',
            'code' => '659114',
            'callId' => 'H2020-MSCA-IF-2014',
            'projectFinancing' => 'H2020',
        ]]);

        self::assertSame('Horizon 2020 Framework Programme', $references[0]->fundingStream);
        self::assertSame('H2020-MSCA-IF-2014', $references[0]->callId);
        self::assertSame('https://cordis.europa.eu/project/id/659114', $references[0]->awardUri);
    }

    public function testNoCordisUriWhenTheFrameworkProgrammeIsUnknown(): void
    {
        // a source may file a non-CORDIS award under the European Commission
        $references = FundingReferenceFactory::fromStoredList([[
            'funderName' => 'European Commission',
            'code' => '10066667',
        ]]);

        self::assertNull($references[0]->fundingStream);
        self::assertNull($references[0]->awardUri);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function ercFinancingProvider(): array
    {
        return [
            'FP7 call' => ['ERC-2010-StG_20091028', 'Seventh Framework Programme'],
            'H2020 call' => ['ERC-2014-STG', 'Horizon 2020 Framework Programme'],
            'last H2020 call' => ['ERC-2020-COG', 'Horizon 2020 Framework Programme'],
            'Horizon Europe call' => ['ERC-2021-STG', 'Horizon Europe Framework Programme'],
            'FP7 ERC specific programme' => ['EC:FP7:ERC', 'Seventh Framework Programme'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ercFinancingProvider')]
    public function testHalErcGrantIsAttributedToTheErc(string $financing, string $expectedStream): void
    {
        // as read from the HAL TEI: <idno type="program">ERC-2014-STG</idno>, filed under the European Commission
        $references = FundingReferenceFactory::fromStoredList([[
            'projectTitle' => 'High precision isotopic measurements',
            'acronym' => 'PRISTINE',
            'funderName' => 'European Commission',
            'code' => '637503',
            'callId' => $financing,
            'projectFinancing' => $financing,
        ]]);

        $reference = $references[0];
        self::assertSame('European Research Council', $reference->funderName);
        self::assertSame('https://doi.org/10.13039/501100000781', $reference->funderCrossrefId);
        self::assertSame('https://ror.org/0472cxd90', $reference->funderRor);
        self::assertSame($expectedStream, $reference->fundingStream);
        self::assertSame('https://cordis.europa.eu/project/id/637503', $reference->awardUri);
    }

    public function testSameErcGrantFromOpenAireAndHalIsMergedUnderTheErc(): void
    {
        $references = FundingReferenceFactory::fromStoredList([
            // OpenAIRE names the European Commission for ERC grants
            [
                'projectTitle' => 'PRISTINE project',
                'funderName' => 'European Commission',
                'code' => '637503',
                'openaireId' => 'corda__h2020::abc',
                'grantDoi' => '10.3030/637503',
            ],
            // HAL TEI
            [
                'acronym' => 'PRISTINE',
                'funderName' => 'European Commission',
                'code' => '637503',
                'projectFinancing' => 'ERC-2014-STG',
            ],
        ]);

        self::assertCount(1, $references);
        self::assertSame('European Research Council', $references[0]->funderName, 'the more specific funder wins');
        self::assertSame('https://doi.org/10.13039/501100000781', $references[0]->funderCrossrefId);
        self::assertSame('10.3030/637503', $references[0]->grantDoi);
        self::assertSame('PRISTINE', $references[0]->awardAcronym);
        self::assertSame('Horizon 2020 Framework Programme', $references[0]->fundingStream);
    }

    public function testEarlyFrameworkProgrammes(): void
    {
        $references = FundingReferenceFactory::fromStoredList([[
            'funderName' => 'European Commission',
            'code' => '12345',
            'projectFinancing' => 'FP3-HCM',
        ]]);

        self::assertSame('Third Framework Programme', $references[0]->fundingStream);
        self::assertSame('European Commission', $references[0]->funderName, 'not an ERC grant');
    }

    public function testFinancingIsNotAStreamForAnotherFunder(): void
    {
        $references = FundingReferenceFactory::fromStoredList([[
            'funderName' => 'Some Funder',
            'code' => '42',
            'projectFinancing' => 'H2020',
        ]]);

        self::assertNull($references[0]->fundingStream);
        self::assertNull($references[0]->awardUri);
        self::assertNull($references[0]->funderCrossrefId);
    }

    public function testUnidentifiedPlaceholdersBecomeNull(): void
    {
        $references = FundingReferenceFactory::fromStoredList([[
            'projectTitle' => 'unidentified',
            'acronym' => 'unidentified',
            'funderName' => 'French National Research Agency (ANR)',
            'code' => 'ANR-22-CE45-0024',
            'projectFinancing' => 'unidentified',
        ]]);

        $reference = $references[0];
        self::assertNull($reference->awardTitle);
        self::assertNull($reference->awardAcronym);
        self::assertNull($reference->fundingStream);
        self::assertSame('https://doi.org/10.13039/501100001665', $reference->funderCrossrefId);
    }

    public function testEntryWithNothingKnownIsDropped(): void
    {
        $references = FundingReferenceFactory::fromStoredList([
            ['projectTitle' => 'unidentified', 'funderName' => 'unidentified', 'code' => 'unidentified'],
            'not an array',
        ]);

        self::assertSame([], $references);
    }

    public function testAnrCodeWithoutFunderNameIsAttributedToAnr(): void
    {
        $references = FundingReferenceFactory::fromStoredList([[
            'funderName' => 'unidentified',
            'code' => 'ANR-11-IDEX-0004-02',
        ]]);

        self::assertSame('French National Research Agency (ANR)', $references[0]->funderName);
        self::assertSame('https://ror.org/00rbzpz17', $references[0]->funderRor);
    }

    public function testFunderNameFromOpenAirePrefixWhenMissing(): void
    {
        $references = FundingReferenceFactory::fromStoredList([[
            'projectTitle' => 'A project',
            'openaireId' => 'anr_________::0001f07df68762a26a20dbcf2019af4c',
        ]]);

        self::assertSame('French National Research Agency (ANR)', $references[0]->funderName);
    }

    public function testSameAwardFromTwoSourcesIsMergedAndCompleted(): void
    {
        $references = FundingReferenceFactory::fromStoredList([
            // HAL
            [
                'projectTitle' => 'Modélisation ENERgétique',
                'acronym' => 'ENERGENCE',
                'funderName' => 'French National Research Agency (ANR)',
                'code' => 'ANR-22-CE45-0024',
            ],
            // OpenAIRE, other funder label for the same funder
            [
                'projectTitle' => 'Energetic modelling',
                'funderName' => 'Agence Nationale de la Recherche',
                'code' => 'ANR-22-CE45-0024',
                'openaireId' => 'anr_________::abc',
            ],
        ]);

        self::assertCount(1, $references);
        self::assertSame('Modélisation ENERgétique', $references[0]->awardTitle, 'first source wins');
        self::assertSame('ENERGENCE', $references[0]->awardAcronym);
        self::assertSame('anr_________::abc', $references[0]->openaireProjectId, 'missing values are filled');
    }

    public function testFreeTextStatementWithAnrCodeOnly(): void
    {
        $references = FundingReferenceFactory::fromStoredList([['fundingStatement' => 'ANR-22-CPJ1-0090-0']]);

        self::assertCount(1, $references);
        self::assertSame('ANR-22-CPJ1-0090-0', $references[0]->awardNumber);
        self::assertSame('French National Research Agency (ANR)', $references[0]->funderName);
        self::assertNull($references[0]->statement, 'the statement is nothing more than the code');
    }

    public function testFreeTextStatementWithoutCodeIsKeptAsStatement(): void
    {
        $references = FundingReferenceFactory::fromStoredList([
            ['fundingStatement' => 'Défi Clé CeBBOc program of Occitanie Region'],
        ]);

        self::assertCount(1, $references);
        self::assertTrue($references[0]->isStatementOnly());
        self::assertFalse($references[0]->hasFunder());
        self::assertSame('Défi Clé CeBBOc program of Occitanie Region', $references[0]->statement);
    }

    public function testFreeTextStatementQuotingAnrCodesGivesAwardsAndStatement(): void
    {
        $references = FundingReferenceFactory::fromStoredList([
            ['fundingStatement' => 'Funded by ANR-19-CE23-0001 and anr-20-ce40-0002.'],
        ]);

        self::assertCount(3, $references);
        self::assertSame('ANR-19-CE23-0001', $references[0]->awardNumber);
        self::assertSame('ANR-20-CE40-0002', $references[1]->awardNumber);
        self::assertTrue($references[2]->isStatementOnly());
    }

    public function testStatementCodeMergesWithStructuredAward(): void
    {
        $references = FundingReferenceFactory::fromStoredList([
            ['funderName' => 'French National Research Agency (ANR)', 'code' => 'ANR-22-CE45-0024', 'projectTitle' => 'ENERGENCE'],
            ['fundingStatement' => 'ANR-22-CE45-0024'],
        ]);

        self::assertCount(1, $references);
        self::assertSame('ENERGENCE', $references[0]->awardTitle);
    }

    public function testDataverseUrlIsTheAwardUri(): void
    {
        $references = FundingReferenceFactory::fromStoredList([[
            'projectTitle' => 'Dataverse project',
            'funderName' => 'unidentified',
            'code' => 'unidentified',
            'url' => 'https://example.org/project',
        ]]);

        self::assertSame('https://example.org/project', $references[0]->awardUri);
    }

    public function testNonHttpUrlIsIgnored(): void
    {
        $references = FundingReferenceFactory::fromStoredList([[
            'projectTitle' => 'Dataverse project',
            'url' => 'javascript:alert(1)',
        ]]);

        self::assertNull($references[0]->awardUri);
    }

    public function testGrantDoiUrlIsNormalized(): void
    {
        $references = FundingReferenceFactory::fromStoredList([[
            'funderName' => 'European Commission',
            'code' => '101079608',
            'grantDoi' => 'https://doi.org/10.3030/101079608',
        ]]);

        self::assertSame('10.3030/101079608', $references[0]->grantDoi);
    }

    public function testWithFunderKeepsOnlyFundingsNamingTheirFunder(): void
    {
        $references = FundingReferenceFactory::fromStoredList([
            ['funderName' => 'European Commission', 'code' => '1'],
            ['projectTitle' => 'Project without funder'],
            ['fundingStatement' => 'Some regional programme'],
            ['funderName' => 'Unknown Regional Funder'],
        ]);

        $withFunder = FundingReferenceFactory::withFunder($references);

        self::assertSame(['European Commission', 'Unknown Regional Funder'], array_map(
            static fn(FundingReference $reference): ?string => $reference->funderName,
            $withFunder
        ));
        self::assertSame([0, 1], array_keys($withFunder), 'reindexed list');
    }

    public function testCitationFunderSource(): void
    {
        self::assertSame(
            'citation_funder=European Commission; citation_grant_number=101017452;',
            (new FundingReference(funderName: 'European Commission', awardNumber: '101017452'))->toCitationFunderSource()
        );
        self::assertSame(
            'citation_funder=Unknown Regional Funder;',
            (new FundingReference(funderName: 'Unknown Regional Funder'))->toCitationFunderSource()
        );
        self::assertNull((new FundingReference(awardNumber: 'X-1'))->toCitationFunderSource());
    }

    public function testDataverseUrlMustBeASafeHttpUrl(): void
    {
        $references = FundingReferenceFactory::fromStoredList([[
            'projectTitle' => 'Dataverse project',
            'url' => 'https://example.org/a"onmouseover="x',
        ]]);

        self::assertNull($references[0]->awardUri);
    }

    public function testToArrayShape(): void
    {
        $reference = new FundingReference(funderName: 'European Commission', awardNumber: '1');

        self::assertSame([
            'funder' => ['name' => 'European Commission', 'crossref_funder_id' => null, 'ror' => null],
            'funding_stream' => null,
            'award' => [
                'number' => '1',
                'title' => null,
                'acronym' => null,
                'uri' => null,
                'grant_doi' => null,
                'call_id' => null,
                'openaire_id' => null,
            ],
            'statement' => null,
        ], $reference->toArray());
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function halErcShapesProvider(): array
    {
        // shapes of the HAL European projects referential for ERC grants
        return [
            'H2020 financing, ERC call' => [['projectFinancing' => 'H2020', 'callId' => 'ERC-2016-COG'], 'Horizon 2020 Framework Programme'],
            'plain ERC financing, ERC call' => [['projectFinancing' => 'ERC', 'callId' => 'ERC-2021-STG'], 'Horizon Europe Framework Programme'],
            'no financing, ERC call' => [['callId' => 'ERC-2008-AdG'], 'Seventh Framework Programme'],
            '"NULL" financing, ERC call' => [['projectFinancing' => 'NULL', 'callId' => 'ERC-2019-STG'], 'Horizon 2020 Framework Programme'],
            'H2020 ERC financing' => [['projectFinancing' => 'H2020 ERC', 'callId' => 'ERC-2015-AdG'], 'Horizon 2020 Framework Programme'],
            'long ERC financing name' => [['projectFinancing' => 'EXCELLENT SCIENCE - European Research Council (ERC)', 'callId' => 'ERC-2022-ADG'], 'Horizon Europe Framework Programme'],
        ];
    }

    /**
     * @param array<string, string> $halValues
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('halErcShapesProvider')]
    public function testHalErcGrantIsRecognizedFromTheCallIdentifier(array $halValues, string $expectedStream): void
    {
        $references = FundingReferenceFactory::fromStoredList([[
            'funderName' => 'European Commission',
            'code' => '682291',
        ] + $halValues]);

        self::assertSame('European Research Council', $references[0]->funderName);
        self::assertSame('https://doi.org/10.13039/501100000781', $references[0]->funderCrossrefId);
        self::assertSame($expectedStream, $references[0]->fundingStream);
        self::assertSame('https://cordis.europa.eu/project/id/682291', $references[0]->awardUri);
    }

    public function testStreamFromTheCallIdentifierWhenTheFinancingIsMissing(): void
    {
        $references = FundingReferenceFactory::fromStoredList([[
            'funderName' => 'European Commission',
            'code' => '659114',
            'callId' => 'H2020-MSCA-IF-2014',
        ]]);

        self::assertSame('European Commission', $references[0]->funderName, 'not an ERC grant');
        self::assertSame('Horizon 2020 Framework Programme', $references[0]->fundingStream);
    }

    public function testSameAwardNumberWithoutFunderIsNotMerged(): void
    {
        $references = FundingReferenceFactory::fromStoredList([
            ['projectTitle' => 'First project', 'funderName' => 'unidentified', 'code' => '1'],
            ['projectTitle' => 'Second project', 'funderName' => 'unidentified', 'code' => '1'],
        ]);

        self::assertCount(2, $references);
        self::assertSame(['First project', 'Second project'], array_map(static fn($reference) => $reference->awardTitle, $references));
    }

    public function testSameAwardNumberOfTheSameFunderIsMerged(): void
    {
        $references = FundingReferenceFactory::fromStoredList([
            ['projectTitle' => 'Project', 'funderName' => 'Some Funder', 'code' => '1'],
            ['acronym' => 'PRJ', 'funderName' => 'Some Funder', 'code' => '1'],
        ]);

        self::assertCount(1, $references);
        self::assertSame('PRJ', $references[0]->awardAcronym);
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function grantDoiProvider(): array
    {
        return [
            'bare' => ['10.3030/637503', '10.3030/637503'],
            'doi.org URL' => ['https://doi.org/10.3030/637503', '10.3030/637503'],
            'dx.doi.org http URL' => ['http://dx.doi.org/10.3030/637503', '10.3030/637503'],
            'doi: prefix' => ['doi:10.3030/637503', '10.3030/637503'],
            'not a DOI' => ['https://example.org/grant', null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('grantDoiProvider')]
    public function testGrantDoiIsNormalized(string $stored, ?string $expected): void
    {
        $references = FundingReferenceFactory::fromStoredList([[
            'funderName' => 'European Commission',
            'code' => '637503',
            'grantDoi' => $stored,
        ]]);

        self::assertSame($expected, $references[0]->grantDoi);
    }
}
