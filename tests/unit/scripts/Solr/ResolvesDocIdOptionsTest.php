<?php

namespace unit\scripts\Solr;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

require_once __DIR__ . '/../../../../scripts/Solr/ResolvesDocIdOptions.php';

/**
 * Unit tests for the --docid / --file branches of ResolvesDocIdOptions. The
 * --sqlwhere branch touches the DB adapter and is exercised separately by
 * SolrIndexCommand's manual end-to-end test plan.
 */
class ResolvesDocIdOptionsTest extends TestCase
{
    use \ResolvesDocIdOptions;

    private function buildInput(array $options): InputInterface
    {
        $definition = new InputDefinition([
            new InputOption('docid', null, InputOption::VALUE_REQUIRED),
            new InputOption('sqlwhere', null, InputOption::VALUE_REQUIRED),
            new InputOption('file', null, InputOption::VALUE_REQUIRED),
        ]);

        return new ArrayInput($options, $definition);
    }

    private function buildIo(InputInterface $input): array
    {
        $output = new BufferedOutput();

        return [new SymfonyStyle($input, $output), $output];
    }

    public function testValidDocIdIsAccepted(): void
    {
        $input = $this->buildInput(['--docid' => '42']);
        [$io] = $this->buildIo($input);

        self::assertSame([42], $this->resolveDocIds($input, $io));
    }

    public function testNonNumericDocIdIsRejectedInsteadOfBecomingZero(): void
    {
        $input = $this->buildInput(['--docid' => 'abc']);
        [$io, $output] = $this->buildIo($input);

        self::assertNull($this->resolveDocIds($input, $io));
        self::assertStringContainsString('Invalid --docid', $output->fetch());
    }

    public function testZeroDocIdIsRejected(): void
    {
        $input = $this->buildInput(['--docid' => '0']);
        [$io, $output] = $this->buildIo($input);

        self::assertNull($this->resolveDocIds($input, $io));
        self::assertStringContainsString('Invalid --docid', $output->fetch());
    }

    public function testFileWithValidDocIdsIsParsed(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'docids');
        file_put_contents($file, "12\n34\n");

        try {
            $input = $this->buildInput(['--file' => $file]);
            [$io] = $this->buildIo($input);

            self::assertSame([12, 34], $this->resolveDocIds($input, $io));
        } finally {
            unlink($file);
        }
    }

    public function testFileWithOnlyInvalidLinesIsRejectedInsteadOfReturningZeros(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'docids');
        file_put_contents($file, "abc\n0\n-1\n");

        try {
            $input = $this->buildInput(['--file' => $file]);
            [$io, $output] = $this->buildIo($input);

            self::assertNull($this->resolveDocIds($input, $io));
            self::assertStringContainsString('No valid DOCID found', $output->fetch());
        } finally {
            unlink($file);
        }
    }

    public function testFileWithSomeInvalidLinesKeepsOnlyValidDocIds(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'docids');
        file_put_contents($file, "12\nabc\n0\n34\n");

        try {
            $input = $this->buildInput(['--file' => $file]);
            [$io] = $this->buildIo($input);

            self::assertSame([12, 34], $this->resolveDocIds($input, $io));
        } finally {
            unlink($file);
        }
    }
}
