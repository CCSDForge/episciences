<?php

namespace unit\scripts;

use GetClassificationJelCommand;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionMethod;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/GetClassificationJelCommand.php';

/**
 * Unit tests for GetClassificationJelCommand.
 *
 * Focuses on pure logic (no bootstrap, no DB) via reflection.
 */
class GetClassificationJelCommandTest extends TestCase
{
    private GetClassificationJelCommand $command;

    protected function setUp(): void
    {
        $this->command = new GetClassificationJelCommand();
    }

    // -------------------------------------------------------------------------
    // Command metadata
    // -------------------------------------------------------------------------

    public function testCommandName(): void
    {
        $this->assertSame('enrichment:classifications-jel', $this->command->getName());
    }

    public function testCommandHasDryRunOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertFalse($definition->getOption('dry-run')->acceptValue(), 'dry-run must be a flag');
    }

    // -------------------------------------------------------------------------
    // buildClassifications() — tested via reflection
    // -------------------------------------------------------------------------

    /** @return array<\Episciences_Paper_Classifications> */
    private function buildClassifications(
        array $codes,
        int $docId,
        array $allCodes
    ): array {
        $method = new ReflectionMethod(GetClassificationJelCommand::class, 'buildClassifications');
        $method->setAccessible(true);
        return $method->invoke($this->command, $codes, $docId, $allCodes, new \Monolog\Logger('test'));
    }

    public function testBuildClassifications_EmptyCodes_ReturnsEmptyArray(): void
    {
        $result = $this->buildClassifications([], 42, ['A10', 'B01']);
        $this->assertSame([], $result);
    }

    public function testBuildClassifications_ValidCode_ReturnsOneClassification(): void
    {
        $result = $this->buildClassifications(['A10'], 99, ['A10', 'B01']);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(\Episciences_Paper_Classifications::class, $result[0]);
        $this->assertSame('A10', $result[0]->getClassificationCode());
        $this->assertSame(99, $result[0]->getDocid());
        $this->assertSame(\Episciences\Classification\jel::$classificationName, $result[0]->getClassificationName());
        $this->assertSame((int) \Episciences_Repositories::GRAPH_OPENAIRE_ID, $result[0]->getSourceId());
    }

    public function testBuildClassifications_InvalidCode_IsSkipped(): void
    {
        // 'ZZ99' is not in the allCodes list
        $result = $this->buildClassifications(['ZZ99'], 1, ['A10', 'B01']);
        $this->assertSame([], $result);
    }

    public function testBuildClassifications_MixedCodes_OnlyValidOnesReturned(): void
    {
        $result = $this->buildClassifications(['A10', 'NOPE', 'B01'], 7, ['A10', 'B01', 'C12']);
        $this->assertCount(2, $result);
        $codes = array_map(fn($c) => $c->getClassificationCode(), $result);
        $this->assertContains('A10', $codes);
        $this->assertContains('B01', $codes);
        $this->assertNotContains('NOPE', $codes);
    }

    public function testBuildClassifications_EmptyAllCodes_AllSkipped(): void
    {
        $result = $this->buildClassifications(['A10', 'B01'], 5, []);
        $this->assertSame([], $result);
    }

    public function testBuildClassifications_SourceIdIsOpenAire(): void
    {
        $result = $this->buildClassifications(['A10'], 1, ['A10']);
        $this->assertSame((int) \Episciences_Repositories::GRAPH_OPENAIRE_ID, $result[0]->getSourceId());
    }
}
