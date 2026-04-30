<?php
declare(strict_types=1);

namespace unit\scripts;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;
use UpdatePapersDocumentCommand;

require_once __DIR__ . '/../../../scripts/UpdatePapersDocumentCommand.php';

/**
 * Unit tests for UpdatePapersDocumentCommand.
 *
 * All tests are pure: no bootstrap, no database, no I/O side-effects.
 */
class UpdatePapersDocumentCommandTest extends TestCase
{
    private UpdatePapersDocumentCommand $command;

    protected function setUp(): void
    {
        $this->command = new UpdatePapersDocumentCommand();
    }

    // -------------------------------------------------------------------------
    // Command metadata
    // -------------------------------------------------------------------------

    public function testCommandName(): void
    {
        $this->assertSame('papers:update-document', $this->command->getName());
    }

    public function testCommandHasDocidOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertInstanceOf(InputDefinition::class, $definition);
        $this->assertTrue($definition->hasOption('docid'));
        $this->assertTrue($definition->getOption('docid')->isValueRequired(), '--docid must require a value');
    }

    public function testCommandHasSqlwhereOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('sqlwhere'));
        $this->assertTrue($definition->getOption('sqlwhere')->isValueRequired(), '--sqlwhere must require a value');
    }

    public function testCommandHasBufferOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('buffer'));
        $this->assertTrue($definition->getOption('buffer')->isValueRequired(), '--buffer must require a value');
    }

    public function testCommandHasUpdateRecordFlag(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('update-record'));
        $this->assertFalse($definition->getOption('update-record')->acceptValue(), '--update-record must be a flag');
    }

    public function testCommandHasNotifyFlag(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('notify'));
        $this->assertFalse($definition->getOption('notify')->acceptValue(), '--notify must be a flag');
    }

    public function testCommandHasJsonFlag(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('json'));
        $this->assertFalse($definition->getOption('json')->acceptValue(), '--json must be a flag');
    }

    public function testCommandHasDryRunFlag(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertFalse($definition->getOption('dry-run')->acceptValue(), '--dry-run must be a flag');
    }

    public function testBufferOptionDefaultIs500(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertSame(
            UpdatePapersDocumentCommand::DEFAULT_BUFFER,
            $definition->getOption('buffer')->getDefault()
        );
    }

    // -------------------------------------------------------------------------
    // buildColumns()
    // -------------------------------------------------------------------------

    public function testBuildColumns_returns19Columns(): void
    {
        // RECORD is always included so toJson() can call getMetadata()
        $this->assertCount(19, $this->command->buildColumns());
    }

    public function testBuildColumns_alwaysIncludesRecord(): void
    {
        $this->assertContains('RECORD', $this->command->buildColumns());
    }

    public function testBuildColumns_alwaysContainsMandatoryFields(): void
    {
        $mandatory = ['DOCID', 'PAPERID', 'RVID', 'IDENTIFIER', 'STATUS', 'VERSION', 'REPOID', 'RECORD'];

        foreach ($mandatory as $col) {
            $this->assertContains($col, $this->command->buildColumns(), "Missing mandatory column: {$col}");
        }
    }

    public function testBuildColumns_returnsListStartingWithDocid(): void
    {
        $this->assertSame('DOCID', $this->command->buildColumns()[0]);
    }

    public function testBuildColumns_recordIsLastColumn(): void
    {
        $cols = $this->command->buildColumns();
        $this->assertSame('RECORD', $cols[array_key_last($cols)]);
    }

    // -------------------------------------------------------------------------
    // validateBuffer()
    // -------------------------------------------------------------------------

    public function testValidateBuffer_positiveValue_returnedAsIs(): void
    {
        $this->assertSame(100, $this->command->validateBuffer(100));
    }

    public function testValidateBuffer_stringInteger_returnedAsInt(): void
    {
        $this->assertSame(250, $this->command->validateBuffer('250'));
    }

    public function testValidateBuffer_zero_returnsDefault(): void
    {
        $this->assertSame(UpdatePapersDocumentCommand::DEFAULT_BUFFER, $this->command->validateBuffer(0));
    }

    public function testValidateBuffer_negative_returnsDefault(): void
    {
        $this->assertSame(UpdatePapersDocumentCommand::DEFAULT_BUFFER, $this->command->validateBuffer(-1));
    }

    public function testValidateBuffer_null_returnsDefault(): void
    {
        $this->assertSame(UpdatePapersDocumentCommand::DEFAULT_BUFFER, $this->command->validateBuffer(null));
    }

    public function testValidateBuffer_nonNumericString_returnsDefault(): void
    {
        $this->assertSame(UpdatePapersDocumentCommand::DEFAULT_BUFFER, $this->command->validateBuffer('abc'));
    }

    public function testValidateBuffer_floatString_returnsDefault(): void
    {
        // FILTER_VALIDATE_INT rejects '3.7' — floats are not valid page sizes
        $this->assertSame(UpdatePapersDocumentCommand::DEFAULT_BUFFER, $this->command->validateBuffer('3.7'));
    }

    public function testValidateBuffer_defaultConstantIs500(): void
    {
        $this->assertSame(500, UpdatePapersDocumentCommand::DEFAULT_BUFFER);
    }

    // -------------------------------------------------------------------------
    // buildUpdateStatement()
    // -------------------------------------------------------------------------

    public function testBuildUpdateStatement_containsTableName(): void
    {
        $sql = $this->command->buildUpdateStatement(42, "'{}'" );
        $this->assertStringContainsString('PAPERS', $sql);
    }

    public function testBuildUpdateStatement_containsDocumentColumn(): void
    {
        $sql = $this->command->buildUpdateStatement(42, "'{}'" );
        $this->assertStringContainsString('DOCUMENT', $sql);
    }

    public function testBuildUpdateStatement_containsDocId(): void
    {
        $sql = $this->command->buildUpdateStatement(99, "'{}'" );
        $this->assertStringContainsString('99', $sql);
    }

    public function testBuildUpdateStatement_containsQuotedJson(): void
    {
        $quoted = "'some-json-string'";
        $sql    = $this->command->buildUpdateStatement(1, $quoted);
        $this->assertStringContainsString($quoted, $sql);
    }

    public function testBuildUpdateStatement_isUpdateStatement(): void
    {
        $sql = $this->command->buildUpdateStatement(1, "'{}'");
        $this->assertStringStartsWith('UPDATE', $sql);
    }

    public function testBuildUpdateStatement_hasWhereClause(): void
    {
        $sql = $this->command->buildUpdateStatement(7, "'{}'");
        $this->assertStringContainsString('WHERE DOCID = 7', $sql);
    }

    public function testBuildUpdateStatement_endsWithSemicolon(): void
    {
        $sql = $this->command->buildUpdateStatement(1, "'{}'");
        $this->assertStringEndsWith(';', $sql);
    }

    public function testBuildUpdateStatement_multipleCallsProduceDistinctDocIds(): void
    {
        $sql1 = $this->command->buildUpdateStatement(10, "'{}'");
        $sql2 = $this->command->buildUpdateStatement(20, "'{}'");

        $this->assertStringContainsString('DOCID = 10', $sql1);
        $this->assertStringContainsString('DOCID = 20', $sql2);
        $this->assertNotSame($sql1, $sql2);
    }

    public function testBuildUpdateStatement_withZeroDocId_producesValidSql(): void
    {
        $sql = $this->command->buildUpdateStatement(0, "'{}'");
        $this->assertStringContainsString('WHERE DOCID = 0', $sql);
        $this->assertStringStartsWith('UPDATE', $sql);
        $this->assertStringEndsWith(';', $sql);
    }
}
