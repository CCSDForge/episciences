<?php

namespace unit\scripts;

use GetClassificationMscCommand;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/GetClassificationMscCommand.php';

/**
 * Unit tests for GetClassificationMscCommand.
 *
 * Focuses on pure logic (no bootstrap, no DB) via reflection.
 */
class GetClassificationMscCommandTest extends TestCase
{
    private GetClassificationMscCommand $command;

    protected function setUp(): void
    {
        $this->command = new GetClassificationMscCommand();
    }

    // -------------------------------------------------------------------------
    // Command metadata
    // -------------------------------------------------------------------------

    public function testCommandName(): void
    {
        $this->assertSame('enrichment:classifications-msc', $this->command->getName());
    }

    public function testCommandHasDryRunOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertFalse($definition->getOption('dry-run')->acceptValue(), 'dry-run must be a flag');
    }

    // -------------------------------------------------------------------------
    // extractMscCodes() — tested via reflection
    // -------------------------------------------------------------------------

    /** @return array<string> */
    private function extractMscCodes(array $apiResponse): array
    {
        $method = new ReflectionMethod(GetClassificationMscCommand::class, 'extractMscCodes');
        $method->setAccessible(true);
        return $method->invoke($this->command, $apiResponse);
    }

    public function testExtractMscCodes_EmptyResult_ReturnsEmpty(): void
    {
        $this->assertSame([], $this->extractMscCodes(['result' => []]));
    }

    public function testExtractMscCodes_ResultKeyAbsent_ReturnsEmpty(): void
    {
        $this->assertSame([], $this->extractMscCodes([]));
    }

    public function testExtractMscCodes_Msc2020Scheme_ExtractsCode(): void
    {
        $response = ['result' => [[
            'msc' => [
                ['scheme' => 'msc2020', 'code' => '11A41'],
            ],
        ]]];
        $this->assertSame(['11A41'], $this->extractMscCodes($response));
    }

    public function testExtractMscCodes_OtherSchemeIgnored(): void
    {
        $response = ['result' => [[
            'msc' => [
                ['scheme' => 'msc2010', 'code' => '11A41'],
                ['scheme' => 'msc2020', 'code' => '65F10'],
            ],
        ]]];
        $this->assertSame(['65F10'], $this->extractMscCodes($response));
    }

    public function testExtractMscCodes_MissingCodeField_Skipped(): void
    {
        $response = ['result' => [[
            'msc' => [
                ['scheme' => 'msc2020'],  // no 'code'
                ['scheme' => 'msc2020', 'code' => '65F10'],
            ],
        ]]];
        $this->assertSame(['65F10'], $this->extractMscCodes($response));
    }

    public function testExtractMscCodes_MultipleResults_AllCodesCollected(): void
    {
        $response = ['result' => [
            ['msc' => [['scheme' => 'msc2020', 'code' => '11A41']]],
            ['msc' => [['scheme' => 'msc2020', 'code' => '65F10']]],
        ]];
        $codes = $this->extractMscCodes($response);
        $this->assertContains('11A41', $codes);
        $this->assertContains('65F10', $codes);
    }

    public function testExtractMscCodes_NoMscKey_ReturnsEmpty(): void
    {
        $response = ['result' => [['title' => 'Something']]];
        $this->assertSame([], $this->extractMscCodes($response));
    }

    // -------------------------------------------------------------------------
    // buildClassifications() — tested via reflection
    // -------------------------------------------------------------------------

    /** @return array<\Episciences_Paper_Classifications> */
    private function buildClassifications(array $codes, int $docId, array $allCodes): array
    {
        $method = new ReflectionMethod(GetClassificationMscCommand::class, 'buildClassifications');
        $method->setAccessible(true);
        return $method->invoke($this->command, $codes, $docId, $allCodes, new \Monolog\Logger('test'));
    }

    public function testBuildClassifications_ValidCode_CorrectEntity(): void
    {
        $result = $this->buildClassifications(['11A41'], 5, ['11A41']);

        $this->assertCount(1, $result);
        $this->assertSame('11A41', $result[0]->getClassificationCode());
        $this->assertSame(5, $result[0]->getDocid());
        $this->assertSame(\Episciences\Classification\msc2020::$classificationName, $result[0]->getClassificationName());
        $this->assertSame((int) \Episciences_Repositories::ZBMATH_OPEN, $result[0]->getSourceId());
    }

    public function testBuildClassifications_InvalidCode_IsSkipped(): void
    {
        $result = $this->buildClassifications(['NOTVALID'], 1, ['11A41']);
        $this->assertSame([], $result);
    }
}
