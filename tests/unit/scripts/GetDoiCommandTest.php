<?php

namespace unit\scripts;

use Episciences\Api\CrossrefSubmissionApiClient;
use Episciences_Paper;
use Episciences_Review;
use Episciences_Review_DoiSettings;
use GetDoiCommand;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

require_once __DIR__ . '/../../../scripts/GetDoiCommand.php';

/**
 * Unit tests for GetDoiCommand.
 *
 * execute() and bootstrap() are never invoked directly: bootstrap() re-creates a
 * Zend_Application in 'production' mode, which would be unsafe to run from a test
 * process. Instead, the private per-action methods are called directly via
 * Reflection with an unknown rvid/rvcode — same convention as
 * Episciences_ReviewsManagerTest: the real (fixture-less) test DB safely returns
 * an empty result set for an unknown journal, exercising the guard-clause branches
 * without needing fixtures or mocking the static Managers.
 */
class GetDoiCommandTest extends TestCase
{
    private const UNKNOWN_RVID = 999999999;
    private const UNKNOWN_CODE = 'unknown-test-journal';

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @param array<int, mixed> $args */
    private function invokePrivate(object $object, string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($object, $args);
    }

    /** @return array{0: SymfonyStyle, 1: BufferedOutput} */
    private function makeIo(): array
    {
        $output = new BufferedOutput();
        return [new SymfonyStyle(new ArrayInput([]), $output), $output];
    }

    private function makeReview(): Episciences_Review
    {
        $review = new Episciences_Review();
        $review->setRvid(self::UNKNOWN_RVID);
        $review->setCode(self::UNKNOWN_CODE);
        return $review;
    }

    /** Never actually invoked in the guard-clause branches under test. */
    private function makeCrossrefClient(): CrossrefSubmissionApiClient
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([]))]);

        return new CrossrefSubmissionApiClient(
            $client,
            new Logger('test'),
            'https://deposit.example',
            'https://deposit-test.example',
            'https://query.example',
            'https://query-test.example',
            'login',
            'password'
        );
    }

    // -------------------------------------------------------------------------
    // Command metadata
    // -------------------------------------------------------------------------

    public function testCommandName(): void
    {
        $this->assertSame('doi:manage', (new GetDoiCommand())->getName());
    }

    public function testCommandHasExpectedOptions(): void
    {
        $definition = (new GetDoiCommand())->getDefinition();

        foreach ([
            'rvcode', 'rvid', 'paperid', 'assign-accepted', 'assign-published',
            'request', 'check', 'update', 'fetch-journals', 'dry-run',
        ] as $option) {
            $this->assertTrue($definition->hasOption($option), "Missing option: --{$option}");
        }
    }

    public function testRvcodeOptionRequiresValue(): void
    {
        $definition = (new GetDoiCommand())->getDefinition();
        $this->assertTrue($definition->getOption('rvcode')->isValueRequired());
    }

    public function testDryRunOptionIsAFlag(): void
    {
        $definition = (new GetDoiCommand())->getDefinition();
        $this->assertFalse($definition->getOption('dry-run')->acceptValue());
    }

    // -------------------------------------------------------------------------
    // requestDois() — empty submission queue guard
    // -------------------------------------------------------------------------

    public function testRequestDois_NoAssignedDois_ReturnsSuccessWithoutCallingCrossref(): void
    {
        [$io, $output] = $this->makeIo();

        $result = $this->invokePrivate(new GetDoiCommand(), 'requestDois', [
            $io,
            $this->makeReview(),
            false,
            new Client(),
            $this->makeCrossrefClient(),
            new Logger('test'),
        ]);

        $this->assertSame(Command::SUCCESS, $result);
        $this->assertStringContainsString('No DOIs to submit', $output->fetch());
    }

    // -------------------------------------------------------------------------
    // updateDois() — empty registered-DOI queue guard
    // -------------------------------------------------------------------------

    public function testUpdateDois_NoRegisteredDois_ReturnsSuccessWithoutCallingCrossref(): void
    {
        [$io, $output] = $this->makeIo();

        $result = $this->invokePrivate(new GetDoiCommand(), 'updateDois', [
            $io,
            $this->makeReview(),
            false,
            new Client(),
            $this->makeCrossrefClient(),
            new Logger('test'),
            null,
        ]);

        $this->assertSame(Command::SUCCESS, $result);
        $this->assertStringContainsString('No registered DOIs found to update', $output->fetch());
    }

    // -------------------------------------------------------------------------
    // checkDois() — empty collection, status query never issued
    // -------------------------------------------------------------------------

    public function testCheckDois_NoPendingDois_ReturnsSuccessWithoutFetchingStatus(): void
    {
        [$io, $output] = $this->makeIo();

        $result = $this->invokePrivate(new GetDoiCommand(), 'checkDois', [
            $io,
            $this->makeReview(),
            false,
            $this->makeCrossrefClient(),
            new Logger('test'),
        ]);

        $this->assertSame(Command::SUCCESS, $result);
        $this->assertStringContainsString('DOI status check completed', $output->fetch());
    }

    // -------------------------------------------------------------------------
    // assignDois() — empty paper list, loop never entered
    // -------------------------------------------------------------------------

    public function testAssignDois_NoEligiblePapers_ReturnsSuccessWithZeroAssigned(): void
    {
        [$io, $output] = $this->makeIo();

        $result = $this->invokePrivate(new GetDoiCommand(), 'assignDois', [
            Episciences_Paper::STATUS_PUBLISHED,
            $io,
            $this->makeReview(),
            new Episciences_Review_DoiSettings(),
            new Logger('test'),
        ]);

        $this->assertSame(Command::SUCCESS, $result);
        $this->assertStringContainsString('Assigned 0 DOI(s)', $output->fetch());
    }

    // -------------------------------------------------------------------------
    // fetchJournals() — HTTP mocked, no DB involved
    // -------------------------------------------------------------------------

    public function testFetchJournals_Success_WritesJournalsFileAndReturnsSuccess(): void
    {
        $targetFile = CACHE_PATH_METADATA . 'journals.json';
        $previous   = is_file($targetFile) ? file_get_contents($targetFile) : null;

        try {
            $http = new Client([
                'handler' => HandlerStack::create(new MockHandler([
                    new Response(200, [], '[{"rvcode":"epiga"}]'),
                ])),
            ]);
            [$io, $output] = $this->makeIo();

            $result = $this->invokePrivate(new GetDoiCommand(), 'fetchJournals', [$io, $http, new Logger('test')]);

            $this->assertSame(Command::SUCCESS, $result);
            $this->assertStringContainsString('Journals list saved to', $output->fetch());
            $this->assertSame('[{"rvcode":"epiga"}]', file_get_contents($targetFile));
        } finally {
            if ($previous === null) {
                @unlink($targetFile);
            } else {
                file_put_contents($targetFile, $previous);
            }
        }
    }

    public function testFetchJournals_HttpFailure_ReturnsFailure(): void
    {
        $http = new Client([
            'handler' => HandlerStack::create(new MockHandler([
                new ConnectException('Connection refused', new Request('GET', 'https://example.test')),
            ])),
        ]);
        [$io, $output] = $this->makeIo();

        $result = $this->invokePrivate(new GetDoiCommand(), 'fetchJournals', [$io, $http, new Logger('test')]);

        $this->assertSame(Command::FAILURE, $result);
        $this->assertStringContainsString('API request failed', $output->fetch());
    }

    // -------------------------------------------------------------------------
    // setJournalConstants() — filesystem only, idempotent once RVCODE/CACHE_PATH
    // are already defined (as they are under the test bootstrap, RVCODE=dev)
    // -------------------------------------------------------------------------

    public function testSetJournalConstants_ConstantsAlreadyDefined_DoesNotWarn(): void
    {
        // The test bootstrap already defines RVCODE/CACHE_PATH for RVCODE=dev,
        // so setJournalConstants()'s own define() calls are all no-ops here.
        [$io, $output] = $this->makeIo();

        $this->invokePrivate(new GetDoiCommand(), 'setJournalConstants', ['some-other-code', $io]);

        $this->assertStringNotContainsString('Could not create cache directory', $output->fetch());
    }
}
