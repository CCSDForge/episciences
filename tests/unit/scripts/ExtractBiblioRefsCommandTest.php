<?php

namespace unit\scripts;

use Episciences_Paper;
use ExtractBiblioRefsCommand;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Log\NullLogger;
use ReflectionMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;

require_once __DIR__ . '/../../../scripts/ExtractBiblioRefsCommand.php';

/**
 * Unit tests for ExtractBiblioRefsCommand.
 *
 * No bootstrap, no DB: Biblioref API responses are simulated with a Guzzle MockHandler.
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

    public function testEverySupportedStatusIsAFlagOption(): void
    {
        foreach (array_keys(ExtractBiblioRefsCommand::SUPPORTED_STATUSES) as $optionName) {
            $this->assertTrue($this->definition->hasOption($optionName), "missing option --{$optionName}");
            $this->assertFalse($this->definition->getOption($optionName)->acceptValue(), "{$optionName} must be a flag");
        }
    }

    public function testSupportedStatusesAreDocidCreationStatuses(): void
    {
        $expected = [
            'submitted'              => Episciences_Paper::STATUS_SUBMITTED,
            'ok-for-reviewing'       => Episciences_Paper::STATUS_OK_FOR_REVIEWING,
            'being-reviewed'         => Episciences_Paper::STATUS_BEING_REVIEWED,
            'accepted'               => Episciences_Paper::STATUS_ACCEPTED,
            'accepted-final-version' => Episciences_Paper::STATUS_ACCEPTED_FINAL_VERSION_SUBMITTED_WAITING_FOR_COPY_EDITORS_FORMATTING,
            'ce-ready-to-publish'    => Episciences_Paper::STATUS_CE_READY_TO_PUBLISH,
            'approved-by-author'     => Episciences_Paper::STATUS_APPROVED_BY_AUTHOR_WAITING_FOR_FINAL_PUBLICATION,
        ];
        $this->assertSame($expected, ExtractBiblioRefsCommand::SUPPORTED_STATUSES);
    }

    public function testPublishedIsAnOptInFlag(): void
    {
        $this->assertSame(['published' => Episciences_Paper::STATUS_PUBLISHED], ExtractBiblioRefsCommand::OPT_IN_STATUSES);
        $this->assertTrue($this->definition->hasOption('published'));
        $this->assertFalse($this->definition->getOption('published')->acceptValue(), 'published must be a flag');
    }

    public function testStatusesThatKeepTheSameDocidHaveNoOption(): void
    {
        foreach (['reviewed', 'reviewers-invited'] as $optionName) {
            $this->assertFalse($this->definition->hasOption($optionName), "--{$optionName} must not exist");
        }
    }

    public function testResolveStatusesDefaultsToAllSupportedStatuses(): void
    {
        $command = new ExtractBiblioRefsCommand();
        $input = new ArrayInput([], $command->getDefinition());

        $this->assertSame([0, 1, 2, 4, 28, 23, 33], $command->resolveStatuses($input));
    }

    public function testResolveStatusesWithSingleStatus(): void
    {
        $command = new ExtractBiblioRefsCommand();
        $input = new ArrayInput(['--being-reviewed' => true], $command->getDefinition());

        $this->assertSame([2], $command->resolveStatuses($input));
    }

    public function testResolveStatusesWithMultipleStatuses(): void
    {
        $command = new ExtractBiblioRefsCommand();
        $input = new ArrayInput([
            '--submitted'              => true,
            '--ok-for-reviewing'       => true,
            '--accepted-final-version' => true,
        ], $command->getDefinition());

        $this->assertSame([0, 1, 28], $command->resolveStatuses($input));
    }

    public function testStatusLabelUsesOptionName(): void
    {
        $command = new ExtractBiblioRefsCommand();

        $this->assertSame('ce-ready-to-publish (23)', $command->getStatusLabel(Episciences_Paper::STATUS_CE_READY_TO_PUBLISH));
    }

    public function testResolveStatusesWithPublishedOnly(): void
    {
        $command = new ExtractBiblioRefsCommand();
        $input = new ArrayInput(['--published' => true], $command->getDefinition());

        $this->assertSame([16], $command->resolveStatuses($input));
    }

    public function testResolveStatusesCombinesDefaultAndOptInStatuses(): void
    {
        $command = new ExtractBiblioRefsCommand();
        $input = new ArrayInput(['--submitted' => true, '--published' => true], $command->getDefinition());

        $this->assertSame([0, 16], $command->resolveStatuses($input));
    }

    public function testStatusLabelForOptInStatus(): void
    {
        $command = new ExtractBiblioRefsCommand();

        $this->assertSame('published (16)', $command->getStatusLabel(Episciences_Paper::STATUS_PUBLISHED));
    }

    public function testStatusLabelFallsBackToCodeForUnsupportedStatus(): void
    {
        $command = new ExtractBiblioRefsCommand();

        $this->assertSame('3', $command->getStatusLabel(Episciences_Paper::STATUS_REVIEWED));
    }

    public function testJournalPdfUrlOnNewFront(): void
    {
        $command = new ExtractBiblioRefsCommand();

        $this->assertSame(
            SERVER_PROTOCOL . '://jtest.' . DOMAIN . '/articles/123/download',
            $command->getJournalPdfUrl('jtest', 123, true)
        );
    }

    public function testJournalPdfUrlOnLegacyFront(): void
    {
        $command = new ExtractBiblioRefsCommand();

        $this->assertSame(
            SERVER_PROTOCOL . '://jtest.' . DOMAIN . '/123/pdf',
            $command->getJournalPdfUrl('jtest', 123, false)
        );
    }

    /**
     * @return array{alreadyExtracted: bool, referenceCount: ?int, skipReason: ?string}|null
     */
    private function callExtractApi(Response $response): ?array
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([$response]))]);
        $method = new ReflectionMethod(ExtractBiblioRefsCommand::class, 'callExtractApi');
        $method->setAccessible(true);

        /** @var array{alreadyExtracted: bool, referenceCount: ?int, skipReason: ?string}|null $result */
        $result = $method->invoke(
            new ExtractBiblioRefsCommand(),
            $client,
            'https://biblioref.test/api/extract?docid=123',
            'token',
            true,
            'https://example.org/paper.pdf',
            5,
            new NullLogger(),
            123
        );

        return $result;
    }

    public function testHostNotAllowedIsSkipped(): void
    {
        $result = $this->callExtractApi(new Response(400, [], (string) json_encode([
            'success' => false,
            'error'   => 'Invalid URL: only http(s) URLs on an allowed Episciences host are accepted',
        ])));

        $this->assertSame('host not allowed', $result['skipReason'] ?? null);
    }

    public function testPdfWithoutReferencesIsSkipped(): void
    {
        $result = $this->callExtractApi(new Response(200, [], (string) json_encode([
            'success' => false,
            'docId'   => 123,
            'error'   => 'No references found in the PDF',
        ])));

        $this->assertSame('no references', $result['skipReason'] ?? null);
    }

    public function testOtherClientErrorFails(): void
    {
        $result = $this->callExtractApi(new Response(400, [], (string) json_encode([
            'success' => false,
            'error'   => 'Missing required parameter: url',
        ])));

        $this->assertNull($result);
    }

    public function testServerErrorFails(): void
    {
        $this->assertNull($this->callExtractApi(new Response(502, [], 'Bad Gateway')));
    }

    public function testOtherExtractionErrorFails(): void
    {
        $result = $this->callExtractApi(new Response(200, [], (string) json_encode([
            'success' => false,
            'error'   => 'unexpected failure',
        ])));

        $this->assertNull($result);
    }

    public function testAlreadyExtractedResult(): void
    {
        $result = $this->callExtractApi(new Response(200, [], (string) json_encode([
            'success'          => true,
            'docId'            => 123,
            'alreadyExtracted' => true,
            'referenceCount'   => 42,
        ])));

        $this->assertSame(['alreadyExtracted' => true, 'referenceCount' => 42, 'skipReason' => null], $result);
    }

    public function testNewExtractionResult(): void
    {
        $result = $this->callExtractApi(new Response(200, [], (string) json_encode([
            'success'          => true,
            'docId'            => 123,
            'alreadyExtracted' => false,
        ])));

        $this->assertSame(['alreadyExtracted' => false, 'referenceCount' => null, 'skipReason' => null], $result);
    }
}
