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
    // formatFundingOAForDB()
    // -------------------------------------------------------------------------

    public function testFormatFundingOAForDBExtractsProjectTypeRelationsOnly(): void
    {
        $fileFound = [
            [
                'to'      => ['@type' => 'project'],
                'title'   => ['$' => 'Test Project'],
                'code'    => ['$' => 'CODE-001'],
                'funding' => ['funder' => ['@name' => 'Test Funder']],
            ],
            [
                // type is 'organization' → must be ignored
                'to' => ['@type' => 'organization'],
                'title' => ['$' => 'Should be ignored'],
                'funding' => ['funder' => ['@name' => 'Should be ignored']],
            ],
        ];

        $result = Episciences_Paper_Projects_EnrichmentService::formatFundingOAForDB($fileFound, [], []);

        self::assertCount(1, $result);
        self::assertSame('Test Project', $result[0]['projectTitle']);
        self::assertSame('Test Funder', $result[0]['funderName']);
        self::assertSame('CODE-001', $result[0]['code']);
    }

    public function testFormatFundingOAForDBIgnoresNonProjectRelations(): void
    {
        $fileFound = [
            [
                'to'      => ['@type' => 'publication'],
                'title'   => ['$' => 'A paper'],
                'funding' => [],
            ],
        ];

        $result = Episciences_Paper_Projects_EnrichmentService::formatFundingOAForDB($fileFound, [], []);

        self::assertSame([], $result);
    }

    public function testFormatFundingOAForDBPreservesExistingGlobalArray(): void
    {
        $existing = [['projectTitle' => 'Existing Project', 'funderName' => 'Existing Funder', 'code' => 'EX-001']];
        $fileFound = [
            [
                'to'      => ['@type' => 'project'],
                'title'   => ['$' => 'New Project'],
                'code'    => ['$' => 'NEW-001'],
                'funding' => ['funder' => ['@name' => 'New Funder']],
            ],
        ];

        $result = Episciences_Paper_Projects_EnrichmentService::formatFundingOAForDB($fileFound, [], $existing);

        self::assertCount(2, $result);
        self::assertSame('Existing Project', $result[0]['projectTitle']);
        self::assertSame('New Project', $result[1]['projectTitle']);
    }
}
