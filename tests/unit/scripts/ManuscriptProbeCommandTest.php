<?php

declare(strict_types=1);

namespace unit\scripts;

use Episciences\Tca\Contract\ManuscriptFile;
use Episciences\Tca\Contract\ManuscriptSourceInterface;
use Episciences\Tca\Contract\ManuscriptTemporarilyUnavailableException;
use GuzzleHttp\Psr7\PumpStream;
use GuzzleHttp\Psr7\Utils;
use ManuscriptProbeCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/../../../scripts/ManuscriptProbeCommand.php';

/**
 * @covers \ManuscriptProbeCommand
 */
final class ManuscriptProbeCommandTest extends TestCase
{
    private ?string $savePath = null;

    protected function tearDown(): void
    {
        if ($this->savePath !== null && is_file($this->savePath)) {
            unlink($this->savePath);
        }
    }

    public function testPrintsTheManuscriptDescription(): void
    {
        $tester = $this->makeTester(static fn(): ManuscriptFile => new ManuscriptFile(Utils::streamFor('%PDF-1.4'), 'Étude.pdf', 8, 'application/pdf'));

        self::assertSame(Command::SUCCESS, $tester->execute(['docid' => '42']));
        self::assertStringContainsString('Étude.pdf', $tester->getDisplay());
        self::assertStringContainsString('8 bytes', $tester->getDisplay());
        self::assertStringContainsString('application/pdf', $tester->getDisplay());
    }

    public function testSavesTheDownloadedFile(): void
    {
        $this->savePath = sys_get_temp_dir() . '/manuscript_probe_' . bin2hex(random_bytes(6)) . '.pdf';
        $tester = $this->makeTester(static fn(): ManuscriptFile => new ManuscriptFile(Utils::streamFor('%PDF-1.4'), 'paper.pdf', 8, 'application/pdf'));

        self::assertSame(Command::SUCCESS, $tester->execute(['docid' => '42', '--save' => $this->savePath]));
        self::assertSame('%PDF-1.4', file_get_contents($this->savePath));
    }

    public function testRemovesThePartialFileWhenTheCopyFails(): void
    {
        $this->savePath = sys_get_temp_dir() . '/manuscript_probe_' . bin2hex(random_bytes(6)) . '.pdf';
        $failingStream = new PumpStream(static fn(): never => throw new \RuntimeException('I/O error'));
        $tester = $this->makeTester(static fn(): ManuscriptFile => new ManuscriptFile($failingStream, 'paper.pdf', 8, 'application/pdf'));

        self::assertSame(Command::FAILURE, $tester->execute(['docid' => '42', '--save' => $this->savePath]));
        self::assertStringContainsString('I/O error', $tester->getDisplay());
        self::assertFileDoesNotExist($this->savePath);
    }

    public function testRemovesAnIncompleteCopy(): void
    {
        $this->savePath = sys_get_temp_dir() . '/manuscript_probe_' . bin2hex(random_bytes(6)) . '.pdf';
        // The stream holds fewer bytes than the announced size
        $tester = $this->makeTester(static fn(): ManuscriptFile => new ManuscriptFile(Utils::streamFor('%PDF'), 'paper.pdf', 8, 'application/pdf'));

        self::assertSame(Command::FAILURE, $tester->execute(['docid' => '42', '--save' => $this->savePath]));
        self::assertStringContainsString('4 of 8 bytes written', $tester->getDisplay());
        self::assertFileDoesNotExist($this->savePath);
    }

    public function testRefusesToOverwriteAnExistingFile(): void
    {
        $this->savePath = (string) tempnam(sys_get_temp_dir(), 'manuscript_probe_');
        $tester = $this->makeTester(static fn(): never => self::fail('The source must not be called'));

        self::assertSame(Command::INVALID, $tester->execute(['docid' => '42', '--save' => $this->savePath]));
    }

    public function testRefusesASavePathInAMissingDirectory(): void
    {
        $tester = $this->makeTester(static fn(): never => self::fail('The source must not be called'));

        self::assertSame(Command::INVALID, $tester->execute(['docid' => '42', '--save' => '/nonexistent-directory-' . bin2hex(random_bytes(4)) . '/x.pdf']));
        self::assertStringContainsString('not a writable directory', $tester->getDisplay());
    }

    public function testRejectsAnInvalidDocid(): void
    {
        $tester = $this->makeTester(static fn(): never => self::fail('The source must not be called'));

        self::assertSame(Command::INVALID, $tester->execute(['docid' => 'abc']));
    }

    public function testFailsWhenThereIsNoAnalysableManuscript(): void
    {
        $tester = $this->makeTester(static fn(): ?ManuscriptFile => null);

        self::assertSame(Command::FAILURE, $tester->execute(['docid' => '42']));
        self::assertStringContainsString('No analysable manuscript', $tester->getDisplay());
    }

    public function testReportsWhetherAFailureIsRetryable(): void
    {
        $tester = $this->makeTester(static fn(): never => throw new ManuscriptTemporarilyUnavailableException('Repository down'));

        self::assertSame(Command::FAILURE, $tester->execute(['docid' => '42']));
        self::assertStringContainsString('retryable', $tester->getDisplay());
    }

    /**
     * @param \Closure(int): ?ManuscriptFile $fetch
     */
    private function makeTester(\Closure $fetch): CommandTester
    {
        $source = new class ($fetch) implements ManuscriptSourceInterface {
            public function __construct(private readonly \Closure $fetch)
            {
            }

            #[\Override]
            public function fetch(int $docId): ?ManuscriptFile
            {
                return ($this->fetch)($docId);
            }
        };

        $command = new class ($source) extends ManuscriptProbeCommand {
            public function __construct(private readonly ManuscriptSourceInterface $source)
            {
                parent::__construct();
            }

            #[\Override]
            protected function getSource(OutputInterface $output): ManuscriptSourceInterface
            {
                return $this->source;
            }

            #[\Override]
            protected function bootstrap(): void
            {
            }
        };

        return new CommandTester($command);
    }
}
