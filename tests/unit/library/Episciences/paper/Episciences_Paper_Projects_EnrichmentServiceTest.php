<?php

namespace unit\library\Episciences;

use Episciences_Paper_Projects_EnrichmentService;
use PHPUnit\Framework\TestCase;

/**
 * @covers Episciences_Paper_Projects_EnrichmentService
 */
final class Episciences_Paper_Projects_EnrichmentServiceTest extends TestCase
{
    // -------------------------------------------------------------------------
    // formatEuHalResp()
    // -------------------------------------------------------------------------

    public function testFormatEuHalRespFillsMissingKeysWithUnidentified(): void
    {
        $resp = [
            'response' => [
                'docs' => [
                    [
                        'projectTitle' => 'My EU Project',
                        'acronym'      => 'MEP',
                        // code, callId, projectFinancing are missing → should be UNIDENTIFIED
                        // funderName is missing → should be 'European Commission'
                    ],
                ],
            ],
        ];

        $result = Episciences_Paper_Projects_EnrichmentService::formatEuHalResp($resp);

        self::assertCount(1, $result);
        self::assertSame('My EU Project', $result[0]['projectTitle']);
        self::assertSame('MEP', $result[0]['acronym']);
        self::assertSame('European Commission', $result[0]['funderName']);
        self::assertSame(Episciences_Paper_Projects_EnrichmentService::UNIDENTIFIED, $result[0]['code']);
        self::assertSame(Episciences_Paper_Projects_EnrichmentService::UNIDENTIFIED, $result[0]['callId']);
        self::assertSame(Episciences_Paper_Projects_EnrichmentService::UNIDENTIFIED, $result[0]['projectFinancing']);
        // 6 keys total
        self::assertCount(6, $result[0]);
    }

    public function testFormatEuHalRespReturnsEmptyArrayWhenNoDocs(): void
    {
        $result = Episciences_Paper_Projects_EnrichmentService::formatEuHalResp([
            'response' => ['docs' => []],
        ]);
        self::assertSame([], $result);
    }

    public function testFormatEuHalRespReturnsEmptyArrayOnEmptyInput(): void
    {
        $result = Episciences_Paper_Projects_EnrichmentService::formatEuHalResp([]);
        self::assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // formatAnrHalResp()
    // -------------------------------------------------------------------------

    public function testFormatAnrHalRespFillsMissingKeysWithUnidentified(): void
    {
        $resp = [
            'response' => [
                'docs' => [
                    [
                        'projectTitle' => 'ANR Research Project',
                        // acronym, code, funderName missing
                    ],
                ],
            ],
        ];

        $result = Episciences_Paper_Projects_EnrichmentService::formatAnrHalResp($resp);

        self::assertCount(1, $result);
        self::assertSame('ANR Research Project', $result[0]['projectTitle']);
        self::assertSame('French National Research Agency (ANR)', $result[0]['funderName']);
        self::assertSame(Episciences_Paper_Projects_EnrichmentService::UNIDENTIFIED, $result[0]['acronym']);
        self::assertSame(Episciences_Paper_Projects_EnrichmentService::UNIDENTIFIED, $result[0]['code']);
        // 4 keys total
        self::assertCount(4, $result[0]);
    }

    public function testFormatAnrHalRespReturnsEmptyArrayWhenNoDocs(): void
    {
        $result = Episciences_Paper_Projects_EnrichmentService::formatAnrHalResp([
            'response' => ['docs' => []],
        ]);
        self::assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // formatFundingOAForDB() — OpenAire Graph v3 direct project entries
    // -------------------------------------------------------------------------

    public function testFormatFundingOAForDBv3MapsDirectScalarFields(): void
    {
        $fileFound = [
            [
                'id'      => 'corda_______::824087',
                'code'    => '824087',
                'acronym' => 'EOSC-Pillar',
                'title'   => 'European Open Science Cloud Pillar',
                'funder'  => 'European Commission',
                'pids'    => null,
            ],
        ];

        $result = Episciences_Paper_Projects_EnrichmentService::formatFundingOAForDB($fileFound, []);

        self::assertCount(1, $result);
        self::assertSame('European Open Science Cloud Pillar', $result[0]['projectTitle']);
        self::assertSame('824087', $result[0]['code']);
        self::assertSame('EOSC-Pillar', $result[0]['acronym']);
        self::assertSame('European Commission', $result[0]['funderName']);
    }

    public function testFormatFundingOAForDBv3NullAcronymOmitted(): void
    {
        $fileFound = [
            [
                'id'      => 'nih_________::01713ce3da46fe56c2b11399f029007c',
                'code'    => '5T15LM007059-20',
                'acronym' => null,
                'title'   => 'Pittsburgh Biomedical Informatics Training Program',
                'funder'  => 'National Institutes of Health',
                'pids'    => null,
            ],
        ];

        $result = Episciences_Paper_Projects_EnrichmentService::formatFundingOAForDB($fileFound, []);

        self::assertCount(1, $result);
        self::assertArrayNotHasKey('acronym', $result[0]);
    }

    public function testFormatFundingOAForDBv3FunderAsDataModelObject(): void
    {
        $fileFound = [
            [
                'code'    => '824087',
                'acronym' => null,
                'title'   => 'European Open Science Cloud Pillar',
                'funder'  => ['name' => 'European Commission', 'shortName' => 'EC'],
                'pids'    => null,
            ],
        ];

        $result = Episciences_Paper_Projects_EnrichmentService::formatFundingOAForDB($fileFound, []);

        self::assertSame('European Commission', $result[0]['funderName']);
    }

    public function testFormatFundingOAForDBv3MultipleProjectsMapped(): void
    {
        $fileFound = [
            [
                'code'    => '824087',
                'acronym' => 'EOSC-Pillar',
                'title'   => 'European Open Science Cloud Pillar',
                'funder'  => 'European Commission',
                'pids'    => null,
            ],
            [
                'code'    => 'ANR-11-LABX-0010',
                'acronym' => 'DRIIHM / IRDHEI',
                'title'   => 'Dispositif de recherche interdisciplinaire sur les Interactions Hommes-Milieux',
                'funder'  => 'French National Research Agency (ANR)',
                'pids'    => null,
            ],
        ];

        $result = Episciences_Paper_Projects_EnrichmentService::formatFundingOAForDB($fileFound, []);

        self::assertCount(2, $result);
        self::assertSame('EOSC-Pillar', $result[0]['acronym']);
        self::assertSame('DRIIHM / IRDHEI', $result[1]['acronym']);
    }

    public function testFormatFundingOAForDBv3IgnoresEntriesWithoutScalarTitle(): void
    {
        $fileFound = [
            ['code' => 'CODE-001', 'title' => ['$' => 'legacy-shaped title, must be ignored']],
            ['code' => 'CODE-002'], // no 'title' key at all
        ];

        $result = Episciences_Paper_Projects_EnrichmentService::formatFundingOAForDB($fileFound, []);

        self::assertSame([], $result);
    }

    public function testFormatFundingOAForDBv3PreservesExistingGlobalArray(): void
    {
        $existing = [['projectTitle' => 'Existing Project', 'funderName' => 'Existing Funder', 'code' => 'EX-001']];
        $fileFound = [
            [
                'code'    => 'NEW-001',
                'acronym' => null,
                'title'   => 'New Project',
                'funder'  => 'New Funder',
                'pids'    => null,
            ],
        ];

        $result = Episciences_Paper_Projects_EnrichmentService::formatFundingOAForDB($fileFound, $existing);

        self::assertCount(2, $result);
        self::assertSame('Existing Project', $result[0]['projectTitle']);
        self::assertSame('New Project', $result[1]['projectTitle']);
    }
}
