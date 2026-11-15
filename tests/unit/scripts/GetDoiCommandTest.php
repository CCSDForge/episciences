<?php

namespace unit\scripts;

use DateTimeImmutable;
use Episciences\Api\CrossrefSubmissionApiClient;
use Episciences\Volume\CrossrefVolumeExporter;
use Episciences_Paper;
use Episciences_Review;
use Episciences_Review_DoiSettings;
use Episciences_Volume;
use Episciences\Volume\DoiQueue;
use GuzzleHttp\Middleware;
use GetDoiCommand;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Monolog\Handler\TestHandler;
use Psr\Http\Message\RequestInterface;
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

    /** @var list<RequestInterface> requests sent to the mocked Crossref client */
    private array $requests = [];

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
            'request', 'check', 'update', 'fetch-journals', 'dry-run', 'volumes',
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

    // -------------------------------------------------------------------------
    // --volumes: option combinations (checked before bootstrap)
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $options
     */
    private function validateVolumesOption(array $options): ?string
    {
        $command = new GetDoiCommand();
        $input   = new ArrayInput($options, $command->getDefinition());

        return $this->invokePrivate($command, 'validateVolumesOption', [$input]);
    }

    public function testVolumesOption_WithRequestOrCheck_IsAccepted(): void
    {
        $this->assertNull($this->validateVolumesOption(['--volumes' => true, '--request' => true]));
        $this->assertNull($this->validateVolumesOption(['--volumes' => true, '--check' => true, '--dry-run' => true]));
        $this->assertNull($this->validateVolumesOption(['--update' => true]), 'Without --volumes nothing changes');
    }

    /**
     * @dataProvider invalidVolumesOptionsProvider
     * @param array<string, mixed> $options
     */
    public function testVolumesOption_InvalidCombination_IsRefused(array $options, string $expected): void
    {
        $this->assertStringContainsString($expected, (string) $this->validateVolumesOption(['--volumes' => true] + $options));
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidVolumesOptionsProvider(): array
    {
        return [
            'alone'            => [[], 'requires either --request or --check'],
            'request and check' => [['--request' => true, '--check' => true], 'requires either --request or --check'],
            'assign-accepted'  => [['--request' => true, '--assign-accepted' => true], '--assign-accepted'],
            'assign-published' => [['--request' => true, '--assign-published' => true], '--assign-published'],
            'update'           => [['--update' => true], '--update'],
        ];
    }

    // -------------------------------------------------------------------------
    // requestVolumeDois() / checkVolumeDois() — queue and Crossref mocked
    // -------------------------------------------------------------------------

    /**
     * @return array{volume: Episciences_Volume, doiq: DoiQueue}
     */
    private function makeVolumeQueueEntry(int $vid, string $doi, string $status): array
    {
        $volume = new Episciences_Volume();
        $volume->setVid($vid);
        $volume->setRvid(self::UNKNOWN_RVID);
        $volume->setTitles(['en' => 'Proceedings']);
        $volume->setSettings([
            Episciences_Volume::VOLUME_IS_PROCEEDING => '1',
            Episciences_Volume::VOLUME_CONFERENCE_NAME => 'Conference',
            Episciences_Volume::VOLUME_CONFERENCE_DOI => $doi,
        ]);
        $volume->setEditors([]);
        $volume->markEditorsLoaded();

        return [
            'volume' => $volume,
            'doiq'   => DoiQueue::fromRow(['id' => $vid, 'vid' => $vid, 'doi_status' => $status]),
        ];
    }

    /**
     * Command whose queue, status writes and exporter are in memory.
     *
     * @param array<int, array{volume: Episciences_Volume, doiq: DoiQueue}> $queue
     */
    private function makeVolumeCommand(array $queue): InMemoryVolumeDoiCommand
    {
        return new InMemoryVolumeDoiCommand($queue);
    }

    /**
     * @param array<int, Response|\Throwable> $responses
     */
    private function makeMockedCrossrefClient(array $responses, ?Logger $logger = null): CrossrefSubmissionApiClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::mapRequest(function (RequestInterface $request): RequestInterface {
            $this->requests[] = $request;
            return $request;
        }));

        return new CrossrefSubmissionApiClient(
            new Client(['handler' => $stack]),
            $logger ?? new Logger('test'),
            'https://deposit.example',
            'https://deposit-test.example',
            'https://query.example',
            'https://query-test.example',
            'login',
            'password'
        );
    }

    private function makeLogger(TestHandler $handler): Logger
    {
        return new Logger('test', [$handler]);
    }

    public function testRequestVolumeDois_PostsTheVolumeXmlAndMarksItRequested(): void
    {
        $command = $this->makeVolumeCommand([
            $this->makeVolumeQueueEntry(1134, '10.46298/mbj.proceedings.51', DoiQueue::STATUS_ASSIGNED),
        ]);
        [$io, $output] = $this->makeIo();

        $result = $this->invokePrivate($command, 'requestVolumeDois', [
            $io, $this->makeReview(), false,
            $this->makeMockedCrossrefClient([new Response(200, [], 'queued')]),
            new Logger('test'),
        ]);

        $this->assertSame(Command::SUCCESS, $result);
        $this->assertSame([DoiQueue::STATUS_ASSIGNED], $command->requestedStatuses);
        $this->assertCount(1, $this->requests);
        $request = $this->requests[0];
        $this->assertSame('https://deposit.example', (string) $request->getUri());
        $body = (string) $request->getBody();
        $this->assertStringContainsString('filename="' . self::UNKNOWN_CODE . '-volume-1134.xml"', $body);
        $this->assertStringContainsString('<doi>10.46298/mbj.proceedings.51</doi>', $body);
        $this->assertSame([['1134', DoiQueue::STATUS_REQUESTED]], $command->saved);
        $this->assertStringContainsString('Volume DOI submission completed', $output->fetch());
    }

    public function testRequestVolumeDois_DryRunUsesTheTestApiAndKeepsTheStatus(): void
    {
        $command = $this->makeVolumeCommand([
            $this->makeVolumeQueueEntry(1134, '10.46298/mbj.proceedings.51', DoiQueue::STATUS_ASSIGNED),
        ]);
        [$io] = $this->makeIo();

        $this->invokePrivate($command, 'requestVolumeDois', [
            $io, $this->makeReview(), true,
            $this->makeMockedCrossrefClient([new Response(200, [], 'queued')]),
            new Logger('test'),
        ]);

        $this->assertSame('https://deposit-test.example', (string) $this->requests[0]->getUri());
        $this->assertSame([], $command->saved);
    }

    public function testRequestVolumeDois_VolumeWithoutDoiIsSkippedOthersAreSent(): void
    {
        $logs    = new TestHandler();
        $command = $this->makeVolumeCommand([
            $this->makeVolumeQueueEntry(1, '', DoiQueue::STATUS_ASSIGNED),
            $this->makeVolumeQueueEntry(2, '10.46298/mbj.proceedings.52', DoiQueue::STATUS_ASSIGNED),
        ]);
        [$io] = $this->makeIo();

        $this->invokePrivate($command, 'requestVolumeDois', [
            $io, $this->makeReview(), false,
            $this->makeMockedCrossrefClient([new Response(200, [], 'queued')]),
            $this->makeLogger($logs),
        ]);

        $this->assertCount(1, $this->requests);
        $this->assertSame(
            [['1', DoiQueue::STATUS_REQUESTED], ['1', DoiQueue::STATUS_ASSIGNED], ['2', DoiQueue::STATUS_REQUESTED]],
            $command->saved,
            'The DOI that cannot be exported is given back'
        );
        $this->assertTrue($logs->hasWarningThatContains('Volume #1 skipped'));
    }

    public function testRequestVolumeDois_CrossrefFailureGivesTheDoiBack(): void
    {
        $logs    = new TestHandler();
        $command = $this->makeVolumeCommand([
            $this->makeVolumeQueueEntry(1134, '10.46298/mbj.proceedings.51', DoiQueue::STATUS_ASSIGNED),
        ]);
        [$io] = $this->makeIo();

        $this->invokePrivate($command, 'requestVolumeDois', [
            $io, $this->makeReview(), false,
            $this->makeMockedCrossrefClient([new ConnectException('down', new Request('POST', 'https://deposit.example'))]),
            $this->makeLogger($logs),
        ]);

        $this->assertSame([['1134', DoiQueue::STATUS_REQUESTED], ['1134', DoiQueue::STATUS_ASSIGNED]], $command->saved);
        $this->assertTrue($logs->hasErrorThatContains('Crossref submission failed for volume #1134'));
    }

    public function testRequestVolumeDois_UnclaimedDoiIsNotSent(): void
    {
        $logs    = new TestHandler();
        $command = $this->makeVolumeCommand([
            $this->makeVolumeQueueEntry(1134, '10.46298/mbj.proceedings.51', DoiQueue::STATUS_ASSIGNED),
        ]);
        $command->claimable = false;
        [$io] = $this->makeIo();

        $this->invokePrivate($command, 'requestVolumeDois', [
            $io, $this->makeReview(), false, $this->makeMockedCrossrefClient([]), $this->makeLogger($logs),
        ]);

        $this->assertSame([], $this->requests, 'A DOI being edited is not deposited');
        $this->assertSame([], $command->saved);
        $this->assertTrue($logs->hasWarningThatContains('Volume #1134 skipped: its DOI is being edited'));
    }

    public function testCheckVolumeDois_StatusNotSaved_DoesNotReportPublic(): void
    {
        $logs    = new TestHandler();
        $command = $this->makeVolumeCommand([
            $this->makeVolumeQueueEntry(1134, '10.46298/mbj.proceedings.51', DoiQueue::STATUS_REQUESTED),
        ]);
        $command->saveSucceeds = false;
        [$io] = $this->makeIo();

        $this->invokePrivate($command, 'checkVolumeDois', [
            $io, $this->makeReview(), false,
            $this->makeMockedCrossrefClient([new Response(200, [], $this->diagnostic('completed', '10.46298/mbj.proceedings.51', 'Success'))]),
            $this->makeLogger($logs),
        ]);

        $this->assertTrue($logs->hasErrorThatContains('its status could not be saved'));
        $this->assertFalse($logs->hasInfoThatContains('is now public'));
    }

    public function testRequestVolumeDois_EmptyQueue_ReturnsSuccess(): void
    {
        [$io, $output] = $this->makeIo();

        $result = $this->invokePrivate($this->makeVolumeCommand([]), 'requestVolumeDois', [
            $io, $this->makeReview(), false, $this->makeMockedCrossrefClient([]), new Logger('test'),
        ]);

        $this->assertSame(Command::SUCCESS, $result);
        $this->assertSame([], $this->requests);
        $this->assertStringContainsString('No volume DOIs to submit', $output->fetch());
    }

    private function diagnostic(string $batchStatus, string $doi, string $recordStatus): string
    {
        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <doi_batch_diagnostic status="{$batchStatus}">
              <record_diagnostic status="{$recordStatus}"><doi>{$doi}</doi><msg>done</msg></record_diagnostic>
              <batch_data><success_count>1</success_count><failure_count>0</failure_count><warning_count>0</warning_count></batch_data>
            </doi_batch_diagnostic>
            XML;
    }

    public function testCheckVolumeDois_SuccessMarksTheDoiPublic(): void
    {
        $command = $this->makeVolumeCommand([
            $this->makeVolumeQueueEntry(1134, '10.46298/mbj.proceedings.51', DoiQueue::STATUS_REQUESTED),
        ]);
        [$io, $output] = $this->makeIo();

        $result = $this->invokePrivate($command, 'checkVolumeDois', [
            $io, $this->makeReview(), false,
            $this->makeMockedCrossrefClient([new Response(200, [], $this->diagnostic('completed', '10.46298/MBJ.proceedings.51', 'Success'))]),
            new Logger('test'),
        ]);

        $this->assertSame(Command::SUCCESS, $result);
        $this->assertSame([DoiQueue::STATUS_REQUESTED], $command->requestedStatuses);
        parse_str($this->requests[0]->getUri()->getQuery(), $query);
        $this->assertSame(self::UNKNOWN_CODE . '-volume-1134.xml', $query['file_name']);
        $this->assertSame([['1134', DoiQueue::STATUS_PUBLIC]], $command->saved);
        $this->assertStringContainsString('Volume DOI status check completed', $output->fetch());
    }

    /**
     * @dataProvider unconfirmedDiagnosticProvider
     */
    public function testCheckVolumeDois_UnconfirmedOrDryRun_KeepsTheStatus(string $batchStatus, string $recordStatus, bool $dryRun): void
    {
        $command = $this->makeVolumeCommand([
            $this->makeVolumeQueueEntry(1134, '10.46298/mbj.proceedings.51', DoiQueue::STATUS_REQUESTED),
        ]);
        [$io] = $this->makeIo();

        $this->invokePrivate($command, 'checkVolumeDois', [
            $io, $this->makeReview(), $dryRun,
            $this->makeMockedCrossrefClient([new Response(200, [], $this->diagnostic($batchStatus, '10.46298/mbj.proceedings.51', $recordStatus))]),
            new Logger('test'),
        ]);

        $this->assertSame($dryRun ? 'https://query-test.example' : 'https://query.example', $this->requests[0]->getUri()->getScheme() . '://' . $this->requests[0]->getUri()->getHost());
        $this->assertSame([], $command->saved);
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function unconfirmedDiagnosticProvider(): array
    {
        return [
            'batch in progress'  => ['in_process', 'Success', false],
            'dry-run success'    => ['completed', 'Success', true],
            'dry-run failure'    => ['completed', 'Failure', true],
        ];
    }

    public function testCheckVolumeDois_RejectedDeposit_GoesBackToAssigned(): void
    {
        $command = $this->makeVolumeCommand([
            $this->makeVolumeQueueEntry(1134, '10.46298/mbj.proceedings.51', DoiQueue::STATUS_REQUESTED),
        ]);
        [$io] = $this->makeIo();

        $this->invokePrivate($command, 'checkVolumeDois', [
            $io, $this->makeReview(), false,
            $this->makeMockedCrossrefClient([new Response(200, [], $this->diagnostic('completed', '10.46298/mbj.proceedings.51', 'Failure'))]),
            new Logger('test'),
        ]);

        $this->assertSame([['1134', DoiQueue::STATUS_ASSIGNED]], $command->saved, 'Unlocked so that the volume can be fixed and deposited again');
    }
}

/**
 * doi:manage with the volume DOI queue, the status writes and the exporter in memory.
 */
final class InMemoryVolumeDoiCommand extends GetDoiCommand
{
    /** @var list<array{string, string}> vid and status of each saved entry */
    public array $saved = [];

    /** @var list<string> statuses looked up in the queue */
    public array $requestedStatuses = [];

    /** Whether the DOI can be claimed (not being edited, still assigned) */
    public bool $claimable = true;

    public bool $saveSucceeds = true;

    /**
     * @param array<int, array{volume: Episciences_Volume, doiq: DoiQueue}> $queue
     */
    public function __construct(private readonly array $queue)
    {
        parent::__construct();
    }

    /**
     * @return array<int, array{volume: Episciences_Volume, doiq: DoiQueue}>
     */
    protected function findVolumeDois(int $rvid, string $status): array
    {
        $this->requestedStatuses[] = $status;

        return array_values(array_filter($this->queue, static fn(array $entry): bool => $entry['doiq']->getStatus() === $status));
    }

    protected function saveVolumeDoiQueue(DoiQueue $doiQueue): bool
    {
        $this->saved[] = [(string) $doiQueue->getVid(), $doiQueue->getStatus()];

        return $this->saveSucceeds;
    }

    protected function claimVolumeDoi(Episciences_Volume $volume, DoiQueue $doiQueue): bool
    {
        if (!$this->claimable) {
            return false;
        }

        $doiQueue->setStatus(DoiQueue::STATUS_REQUESTED);
        $this->saved[] = [(string) $doiQueue->getVid(), $doiQueue->getStatus()];

        return true;
    }

    protected function createVolumeExporter(): CrossrefVolumeExporter
    {
        return new CrossrefVolumeExporter(
            static fn(int $rvid): bool => true,
            static fn(): DateTimeImmutable => new DateTimeImmutable('2026-11-15 10:00:00')
        );
    }
}
