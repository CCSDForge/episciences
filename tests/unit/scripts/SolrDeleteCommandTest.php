<?php

namespace unit\scripts;

use PHPUnit\Framework\TestCase;
use SolrDeleteCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/../../../scripts/SolrDeleteCommand.php';

/**
 * Unit tests for SolrDeleteCommand. The --docid/--query mutual-exclusivity
 * check runs before bootstrap(), so it's testable without a DB.
 */
class SolrDeleteCommandTest extends TestCase
{
    private Command $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $application = new Application();
        $application->add(new SolrDeleteCommand());
        $this->command = $application->find('solr:delete');
        $this->commandTester = new CommandTester($this->command);
    }

    public function testCommandName(): void
    {
        self::assertSame('solr:delete', $this->command->getName());
    }

    public function testHasExpectedOptions(): void
    {
        $definition = $this->command->getDefinition();
        self::assertInstanceOf(InputDefinition::class, $definition);

        foreach (['docid', 'query', 'sync'] as $option) {
            self::assertTrue($definition->hasOption($option), "Missing option --{$option}");
        }
    }

    public function testFailsWhenNeitherDocIdNorQueryProvided(): void
    {
        $this->commandTester->execute([]);

        self::assertSame(1, $this->commandTester->getStatusCode());
        self::assertStringContainsString('Exactly one of --docid or --query', $this->commandTester->getDisplay());
    }

    public function testFailsWhenBothDocIdAndQueryProvided(): void
    {
        $this->commandTester->execute(['--docid' => '42', '--query' => 'docid:42']);

        self::assertSame(1, $this->commandTester->getStatusCode());
        self::assertStringContainsString('Exactly one of --docid or --query', $this->commandTester->getDisplay());
    }

    /**
     * @dataProvider destructiveQueryProvider
     */
    public function testWildcardDeletePatternCatchesDestructiveQueries(string $query): void
    {
        self::assertSame(1, preg_match($this->wildcardDeletePattern(), trim($query)), sprintf('Expected "%s" to be flagged as destructive.', $query));
    }

    /**
     * @dataProvider harmlessQueryProvider
     */
    public function testWildcardDeletePatternLeavesTargetedQueriesAlone(string $query): void
    {
        self::assertSame(0, preg_match($this->wildcardDeletePattern(), trim($query)), sprintf('Expected "%s" NOT to be flagged as destructive.', $query));
    }

    /**
     * @return list<list<string>>
     */
    public static function destructiveQueryProvider(): array
    {
        return [
            'exact wildcard' => ['*:*'],
            'exact field wildcard' => ['docid:*'],
            'surrounding whitespace' => ['  *:*  '],
            'leading whitespace only' => [' *:*'],
            'wrapped in parentheses' => ['(*:*)'],
            'open range' => ['docid:[* TO *]'],
            'combined with OR' => ['*:* OR docid:1'],
            'combined with AND, field wildcard first' => ['docid:* AND status:1'],
        ];
    }

    /**
     * @return list<list<string>>
     */
    public static function harmlessQueryProvider(): array
    {
        return [
            'single docid' => ['docid:19'],
            'field value containing a star mid-token' => ['title:foo*bar'],
            'prefix wildcard, not a full-field wildcard' => ['title:foo*'],
        ];
    }

    private function wildcardDeletePattern(): string
    {
        return (new \ReflectionClass(SolrDeleteCommand::class))->getConstant('WILDCARD_DELETE_PATTERN');
    }
}
