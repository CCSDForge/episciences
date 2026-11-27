<?php

namespace unit\library\Episciences;

use Episciences_Paper_ProjectsManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers Episciences_Paper_ProjectsManager
 * @covers Episciences_Paper_Projects_EnrichmentService
 */
final class Episciences_Paper_ProjectsManagerTest extends TestCase {

    /**
     * OpenAire Graph v3 direct project entries (results[0].projects), the current API format.
     *
     * @dataProvider sampleOaProjectsV3
     * @param array $sampleOaProjectsV3
     * @return void
     */
    public function testFormatFundingOAForDBv3(array $sampleOaProjectsV3): void {
        $result = Episciences_Paper_ProjectsManager::formatFundingOAForDB($sampleOaProjectsV3, []);

        self::assertCount(2, $result);

        self::assertSame('European Open Science Cloud Pillar', $result[0]['projectTitle']);
        self::assertSame('EOSC-Pillar', $result[0]['acronym']);
        self::assertSame('824087', $result[0]['code']);
        self::assertSame('European Commission', $result[0]['funderName']);

        self::assertSame('Dispositif de recherche interdisciplinaire sur les Interactions Hommes-Milieux', $result[1]['projectTitle']);
        self::assertSame('DRIIHM / IRDHEI', $result[1]['acronym']);
        self::assertSame('ANR-11-LABX-0010', $result[1]['code']);
        self::assertSame('French National Research Agency (ANR)', $result[1]['funderName']);
    }

    /**
     * A v3 project with a null acronym must not carry a stale acronym over from a
     * previously processed entry (regression guard for the v1 "no reset" quirk).
     */
    public function testFormatFundingOAForDBv3NullAcronymNotLeakedFromPreviousEntry(): void
    {
        $entries = [
            [
                'id' => 'corda_______::824087',
                'code' => '824087',
                'acronym' => 'EOSC-Pillar',
                'title' => 'European Open Science Cloud Pillar',
                'funder' => 'European Commission',
                'pids' => null,
            ],
            [
                'id' => 'nih_________::01713ce3da46fe56c2b11399f029007c',
                'code' => '5T15LM007059-20',
                'acronym' => null,
                'title' => 'Pittsburgh Biomedical Informatics Training Program',
                'funder' => 'National Institutes of Health',
                'pids' => null,
            ],
        ];

        $result = Episciences_Paper_ProjectsManager::formatFundingOAForDB($entries, []);

        self::assertArrayHasKey('acronym', $result[0]);
        self::assertArrayNotHasKey('acronym', $result[1]);
    }

    /**
     * @return array<string, array<int, array<int, array<string, mixed>>>>
     */
    public static function sampleOaProjectsV3(): array
    {
        return [[[
            [
                'id' => 'corda_______::824087',
                'code' => '824087',
                'acronym' => 'EOSC-Pillar',
                'title' => 'European Open Science Cloud Pillar',
                'funder' => 'European Commission',
                'pids' => null,
            ],
            [
                'id' => 'anr_________::9fb7cc85ed5f1c9819dd26f41e8528a7',
                'code' => 'ANR-11-LABX-0010',
                'acronym' => 'DRIIHM / IRDHEI',
                'title' => 'Dispositif de recherche interdisciplinaire sur les Interactions Hommes-Milieux',
                'funder' => 'French National Research Agency (ANR)',
                'pids' => null,
            ],
        ]]];
    }
}
