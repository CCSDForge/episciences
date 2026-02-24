<?php

namespace unit\scripts;

use CreateDoajVolumeExportsCommand;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/CreateDoajVolumeExportsCommand.php';

/**
 * Unit tests for CreateDoajVolumeExportsCommand.
 *
 * Focuses on pure static logic and XML-stripping (no bootstrap, no DB, no HTTP).
 */
class CreateDoajVolumeExportsCommandTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Command metadata
    // -------------------------------------------------------------------------

    public function testCommandName(): void
    {
        $command = new CreateDoajVolumeExportsCommand();
        $this->assertSame('doaj:export-volumes', $command->getName());
    }

    public function testCommandHasRvcodeOption(): void
    {
        $definition = (new CreateDoajVolumeExportsCommand())->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasOption('rvcode'));
        $this->assertTrue($definition->getOption('rvcode')->isValueRequired(), 'rvcode must require a value');
    }

    public function testCommandHasDryRunOption(): void
    {
        $definition = (new CreateDoajVolumeExportsCommand())->getDefinition();
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertFalse($definition->getOption('dry-run')->acceptValue(), 'dry-run must be a flag');
    }

    public function testCommandHasIgnoreCacheOption(): void
    {
        $definition = (new CreateDoajVolumeExportsCommand())->getDefinition();
        $this->assertTrue($definition->hasOption('ignore-cache'));
        $this->assertFalse($definition->getOption('ignore-cache')->acceptValue(), 'ignore-cache must be a flag');
    }

    public function testCommandHasRemoveCacheOption(): void
    {
        $definition = (new CreateDoajVolumeExportsCommand())->getDefinition();
        $this->assertTrue($definition->hasOption('remove-cache'));
        $this->assertFalse($definition->getOption('remove-cache')->acceptValue(), 'remove-cache must be a flag');
    }

    // -------------------------------------------------------------------------
    // getPaperIdCollection() — public static, pure
    // -------------------------------------------------------------------------

    public function testGetPaperIdCollection_EmptyArray_ReturnsEmpty(): void
    {
        $this->assertSame([], CreateDoajVolumeExportsCommand::getPaperIdCollection([]));
    }

    public function testGetPaperIdCollection_ExtractsPaperIds(): void
    {
        $data = [
            ['paperid' => 10, 'title' => 'A'],
            ['paperid' => 20, 'title' => 'B'],
            ['paperid' => 30, 'title' => 'C'],
        ];
        $this->assertSame([10, 20, 30], CreateDoajVolumeExportsCommand::getPaperIdCollection($data));
    }

    public function testGetPaperIdCollection_MissingPaperid_RowSkipped(): void
    {
        // array_column() silently skips rows where the key is absent
        $data = [
            ['title' => 'A'],          // no 'paperid' -> skipped
            ['paperid' => 5, 'title' => 'B'],
        ];
        $result = CreateDoajVolumeExportsCommand::getPaperIdCollection($data);
        $this->assertCount(1, $result);
        $this->assertContains(5, $result);
    }

    // -------------------------------------------------------------------------
    // fetchDoajExport() — XML stripping logic via partial mock
    // -------------------------------------------------------------------------

    /**
     * Build a partial mock with $logger injected via reflection.
     *
     * @param string $rawXml Response returned by downloadDoajExport()
     */
    private function makeCommandWithLogger(string $rawXml): CreateDoajVolumeExportsCommand
    {
        $command = $this->getMockBuilder(CreateDoajVolumeExportsCommand::class)
            ->onlyMethods(['downloadDoajExport'])
            ->getMock();

        $command->method('downloadDoajExport')->willReturn($rawXml);

        // Inject a null-handler logger so $this->logger->info() does not throw
        $logger = new Logger('test');
        $prop   = new \ReflectionProperty(CreateDoajVolumeExportsCommand::class, 'logger');
        $prop->setAccessible(true);
        $prop->setValue($command, $logger);

        return $command;
    }

    public function testFetchDoajExport_StripsXmlDeclarationAndRecordsTags(): void
    {
        $rawXml  = '<?xml version="1.0"?>' . PHP_EOL
                 . '<records>' . PHP_EOL
                 . '<record><title>Test</title></record>' . PHP_EOL
                 . '</records>';
        $command = $this->makeCommandWithLogger($rawXml);

        $result = $command->fetchDoajExport(42);

        $this->assertStringNotContainsString('<?xml version="1.0"?>', $result);
        $this->assertStringNotContainsString('<records>', $result);
        $this->assertStringNotContainsString('</records>', $result);
        $this->assertStringContainsString('<record><title>Test</title></record>', $result);
    }

    public function testFetchDoajExport_EmptyResponse_ReturnsEmpty(): void
    {
        $command = $this->makeCommandWithLogger('');
        $this->assertSame('', $command->fetchDoajExport('doc123'));
    }

    public function testFetchDoajExport_MultipleRecords_PreservesInnerXml(): void
    {
        $rawXml  = '<?xml version="1.0"?><records><record><id>1</id></record><record><id>2</id></record></records>';
        $command = $this->makeCommandWithLogger($rawXml);

        $result = $command->fetchDoajExport(1);

        $this->assertStringContainsString('<record><id>1</id></record>', $result);
        $this->assertStringContainsString('<record><id>2</id></record>', $result);
    }

    // -------------------------------------------------------------------------
    // APICALLVOL constant
    // -------------------------------------------------------------------------

    public function testApiCallVolConstant(): void
    {
        $this->assertStringStartsWith('volumes', CreateDoajVolumeExportsCommand::APICALLVOL);
        $this->assertStringContainsString('rvcode=', CreateDoajVolumeExportsCommand::APICALLVOL);
    }
}
