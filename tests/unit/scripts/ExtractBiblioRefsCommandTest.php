<?php

namespace unit\scripts;

use ExtractBiblioRefsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/ExtractBiblioRefsCommand.php';

/**
 * Unit tests for ExtractBiblioRefsCommand.
 *
 * Covers command metadata only — no bootstrap, no DB, no HTTP.
 */
class ExtractBiblioRefsCommandTest extends TestCase
{
    private InputDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = (new ExtractBiblioRefsCommand())->getDefinition();
    }

    public function testCommandName(): void
    {
        $this->assertSame('enrichment:extract-biblio-refs', (new ExtractBiblioRefsCommand())->getName());
    }

    public function testCommandHasDescription(): void
    {
        $description = (new ExtractBiblioRefsCommand())->getDescription();
        $this->assertNotEmpty($description);
    }

    public function testDryRunIsFlag(): void
    {
        $this->assertTrue($this->definition->hasOption('dry-run'));
        $this->assertFalse($this->definition->getOption('dry-run')->acceptValue(), 'dry-run must be a flag');
    }

    public function testSubmittedIsFlag(): void
    {
        $this->assertTrue($this->definition->hasOption('submitted'));
        $this->assertFalse($this->definition->getOption('submitted')->acceptValue(), 'submitted must be a flag');
    }

    public function testOkForReviewingIsFlag(): void
    {
        $this->assertTrue($this->definition->hasOption('ok-for-reviewing'));
        $this->assertFalse($this->definition->getOption('ok-for-reviewing')->acceptValue(), 'ok-for-reviewing must be a flag');
    }

    public function testBeingReviewedIsFlag(): void
    {
        $this->assertTrue($this->definition->hasOption('being-reviewed'));
        $this->assertFalse($this->definition->getOption('being-reviewed')->acceptValue(), 'being-reviewed must be a flag');
    }

    public function testReviewedIsFlag(): void
    {
        $this->assertTrue($this->definition->hasOption('reviewed'));
        $this->assertFalse($this->definition->getOption('reviewed')->acceptValue(), 'reviewed must be a flag');
    }

    public function testReviewersInvitedIsFlag(): void
    {
        $this->assertTrue($this->definition->hasOption('reviewers-invited'));
        $this->assertFalse($this->definition->getOption('reviewers-invited')->acceptValue(), 'reviewers-invited must be a flag');
    }

    public function testPublishedIsFlag(): void
    {
        $this->assertTrue($this->definition->hasOption('published'));
        $this->assertFalse($this->definition->getOption('published')->acceptValue(), 'published must be a flag');
    }

    public function testAcceptedIsFlag(): void
    {
        $this->assertTrue($this->definition->hasOption('accepted'));
        $this->assertFalse($this->definition->getOption('accepted')->acceptValue(), 'accepted must be a flag');
    }

    public function testDocidAcceptsRequiredValue(): void
    {
        $this->assertTrue($this->definition->hasOption('docid'));
        $this->assertTrue($this->definition->getOption('docid')->isValueRequired(), 'docid must require a value');
    }

    public function testRvcodeAcceptsRequiredValue(): void
    {
        $this->assertTrue($this->definition->hasOption('rvcode'));
        $this->assertTrue($this->definition->getOption('rvcode')->isValueRequired(), 'rvcode must require a value');
    }

    public function testApiUrlAcceptsRequiredValue(): void
    {
        $this->assertTrue($this->definition->hasOption('api-url'));
        $this->assertTrue($this->definition->getOption('api-url')->isValueRequired(), 'api-url must require a value');
    }

    public function testSupportedStatusesConstant(): void
    {
        $expected = [
            'submitted'         => 0,
            'ok-for-reviewing'  => 1,
            'being-reviewed'    => 2,
            'reviewed'          => 3,
            'reviewers-invited' => 14,
            'accepted'          => 4,
            'published'         => 16,
        ];
        $this->assertSame($expected, ExtractBiblioRefsCommand::SUPPORTED_STATUSES);
    }

    public function testResolveStatusesDefaultsToAllSupportedStatuses(): void
    {
        $command = new ExtractBiblioRefsCommand();
        $input = new \Symfony\Component\Console\Input\ArrayInput([], $command->getDefinition());

        $statuses = $command->resolveStatuses($input);
        $this->assertSame([0, 1, 2, 3, 14, 4, 16], $statuses);
    }

    public function testResolveStatusesWithSingleStatus(): void
    {
        $command = new ExtractBiblioRefsCommand();
        $input = new \Symfony\Component\Console\Input\ArrayInput(['--being-reviewed' => true], $command->getDefinition());

        $statuses = $command->resolveStatuses($input);
        $this->assertSame([2], $statuses);
    }

    public function testResolveStatusesWithMultipleStatuses(): void
    {
        $command = new ExtractBiblioRefsCommand();
        $input = new \Symfony\Component\Console\Input\ArrayInput([
            '--submitted'      => true,
            '--ok-for-reviewing' => true,
            '--published'      => true,
        ], $command->getDefinition());

        $statuses = $command->resolveStatuses($input);
        $this->assertSame([0, 1, 16], $statuses);
    }
}
