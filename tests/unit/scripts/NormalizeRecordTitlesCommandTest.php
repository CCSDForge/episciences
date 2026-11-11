<?php

declare(strict_types=1);

namespace unit\scripts;

use NormalizeRecordTitlesCommand;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../scripts/Command/Bootstrapper.php';
require_once __DIR__ . '/../../../scripts/Command/AbstractCommand.php';
require_once __DIR__ . '/../../../scripts/NormalizeRecordTitlesCommand.php';

class NormalizeRecordTitlesCommandTest extends TestCase
{
    private NormalizeRecordTitlesCommand $command;

    protected function setUp(): void
    {
        $this->command = new NormalizeRecordTitlesCommand();
    }

    private static function record(string $title): string
    {
        return '<record><oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:title>' . $title . '</dc:title></oai_dc:dc></record>';
    }

    public function testCommandDefinition(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertSame('papers:normalize-titles', $this->command->getName());
        $this->assertTrue($definition->getOption('docid')->isValueRequired());
        $this->assertSame(NormalizeRecordTitlesCommand::DEFAULT_BUFFER, $definition->getOption('buffer')->getDefault());
        $this->assertFalse($definition->getOption('dry-run')->acceptValue());
    }

    public function testComputeUpdatesRewritesOnlyRecordsWithTitlesToNormalize(): void
    {
        $result = $this->command->computeUpdates([
            ['DOCID' => '10', 'RECORD' => self::record("Jeux mathématiques : une UE de Licence\npour pratiquer")],
            ['DOCID' => '11', 'RECORD' => self::record('Already on a single line')],
            ['DOCID' => '12', 'RECORD' => "<record><dc:title>not\nparsable"],
            ['DOCID' => '13', 'RECORD' => null],
        ]);

        $this->assertSame([10], array_keys($result['updates']));
        $this->assertStringContainsString(
            '<dc:title>Jeux mathématiques : une UE de Licence pour pratiquer</dc:title>',
            $result['updates'][10]
        );
        $this->assertSame([11, 12, 13], $result['untouched']);
    }

    /**
     * @dataProvider bufferProvider
     */
    public function testValidateBuffer(mixed $value, int $expected): void
    {
        $this->assertSame($expected, $this->command->validateBuffer($value));
    }

    /**
     * @return array<string, array{mixed, int}>
     */
    public static function bufferProvider(): array
    {
        return [
            'positive' => [100, 100],
            'numeric string' => ['250', 250],
            'zero' => [0, NormalizeRecordTitlesCommand::DEFAULT_BUFFER],
            'negative' => [-5, NormalizeRecordTitlesCommand::DEFAULT_BUFFER],
            'not an integer' => ['abc', NormalizeRecordTitlesCommand::DEFAULT_BUFFER],
            'missing' => [null, NormalizeRecordTitlesCommand::DEFAULT_BUFFER],
        ];
    }
}
