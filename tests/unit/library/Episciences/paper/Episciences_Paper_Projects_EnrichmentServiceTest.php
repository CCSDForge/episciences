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

    public function testFormatFundingOAForDBv3KeepsOpenAireIdAndGrantDoi(): void
    {
        $fileFound = [
            [
                'id'      => 'corda__h2020::2f32bdaa3c76066bd8267f3ac90ba898',
                'code'    => '101017452',
                'acronym' => 'OpenAIRE Nexus',
                'title'   => 'OpenAIRE-Nexus Scholarly Communication Services for EOSC users',
                'funder'  => 'European Commission',
                'pids'    => [['scheme' => 'ark', 'value' => 'x'], ['scheme' => 'doi', 'value' => '10.3030/101017452']],
            ],
        ];

        $result = Episciences_Paper_Projects_EnrichmentService::formatFundingOAForDB($fileFound, []);

        self::assertSame('corda__h2020::2f32bdaa3c76066bd8267f3ac90ba898', $result[0]['openaireId']);
        self::assertSame('10.3030/101017452', $result[0]['grantDoi']);
    }

    public function testFormatFundingOAForDBv3WithoutPidsHasNoGrantDoi(): void
    {
        $fileFound = [['id' => 'anr_________::abc', 'title' => 'T', 'pids' => null]];

        $result = Episciences_Paper_Projects_EnrichmentService::formatFundingOAForDB($fileFound, []);

        self::assertArrayNotHasKey('grantDoi', $result[0]);
        self::assertSame('anr_________::abc', $result[0]['openaireId']);
    }
}
