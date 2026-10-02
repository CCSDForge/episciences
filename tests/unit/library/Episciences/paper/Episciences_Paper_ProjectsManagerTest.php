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

    /**
     * @dataProvider sampleHalEuProjects
     * @param array $sampleHalEuProjects
     * @return void
     */

    public function testFormatFundingHalEUForDB(array $sampleHalEuProjects): void {

        $formatEuHal = Episciences_Paper_ProjectsManager::formatEuHalResp($sampleHalEuProjects);

        self::assertCount(6,$formatEuHal[0]);

        self::assertArrayHasKey('projectTitle',$formatEuHal[0]);
        self::assertEquals('NEtwork MOtion',$formatEuHal[0]['projectTitle']);

        self::assertArrayHasKey('acronym',$formatEuHal[0]);
        self::assertEquals('NEMO',$formatEuHal[0]['acronym']);

        self::assertArrayHasKey('code',$formatEuHal[0]);
        self::assertEquals('788851',$formatEuHal[0]['code']);

        self::assertArrayHasKey('callId',$formatEuHal[0]);
        self::assertEquals('ERC-2017-ADG',$formatEuHal[0]['callId']);

        self::assertArrayHasKey('projectFinancing',$formatEuHal[0]);
        self::assertEquals('ERC',$formatEuHal[0]['projectFinancing']);

        self::assertArrayHasKey('funderName',$formatEuHal[0]);
        self::assertEquals('European Commission',$formatEuHal[0]['funderName']);


    }

    /**
     * @dataProvider sampleHalAnrProjects
     * @param array $sampleHalAnrProjects
     * @return void
     */


    public function testFormatFundingHalANRForDB(array $sampleHalAnrProjects): void {
        $formatAnrHal = Episciences_Paper_ProjectsManager::formatAnrHalResp($sampleHalAnrProjects);

        self::assertCount(4,$formatAnrHal[0]);

        self::assertArrayHasKey('projectTitle',$formatAnrHal[0]);
        self::assertEquals('Du territoire au marché : histoire de l\'industrie des sports et loisirs alpins au XXe siècle',$formatAnrHal[0]['projectTitle']);

        self::assertArrayHasKey('acronym',$formatAnrHal[0]);
        self::assertEquals('TIMSA',$formatAnrHal[0]['acronym']);

        self::assertArrayHasKey('code',$formatAnrHal[0]);
        self::assertEquals('ANR-10-BLAN-2008',$formatAnrHal[0]['code']);

        self::assertArrayHasKey('funderName',$formatAnrHal[0]);
        self::assertEquals('French National Research Agency (ANR)',$formatAnrHal[0]['funderName']);

    }

    /**
     * Hal sample European Funding (same thing for ANR)
     * @return array
     */
    public static function sampleHalEuProjects():array {
        return [[json_decode('{
    "response": {
        "numFound": 1,
        "start": 0,
        "numFoundExact": true,
        "docs": [
            {
                "projectTitle":"NEtwork MOtion",
                "acronym":"NEMO",
                "code":"788851",
                "callId":"ERC-2017-ADG",
                "projectFinancing":"ERC"
            }
        ]
    }
}',true,512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]];
    }

    /**
     * Hal sample ANR Funding (same thing for Eu)
     * @return array
     */
    public static function sampleHalAnrProjects():array {
        return [[json_decode('{
    "response": {
        "numFound": 1,
        "start": 0,
        "numFoundExact": true,
        "docs": [
            {
                "projectTitle": "Du territoire au marché : histoire de l\'industrie des sports et loisirs alpins au XXe siècle",
                "acronym": "TIMSA",
                "code": "ANR-10-BLAN-2008"
            }
        ]
    }

}',true,512, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]];
    }


}
