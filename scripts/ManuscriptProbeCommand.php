<?php

declare(strict_types=1);

use Episciences\Log\LoggerFactory;
use Episciences\Tca\Contract\ManuscriptSourceException;
use Episciences\Tca\Contract\ManuscriptSourceInterface;
use Episciences\Tca\Manuscript\RepositoryManuscriptSource;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use scripts\Command\Bootstrapper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Resolves and downloads the manuscript of a paper version the way the Turnitin integration will,
 * to check a repository before submitting anything to Turnitin.
 *
 * Usage: php scripts/console.php episciences:manuscript:probe 12345
 *        php scripts/console.php episciences:manuscript:probe 12345 --save=/path/to/copy.pdf
 */
#[AsCommand(name: 'episciences:manuscript:probe', description: 'Resolve and download the manuscript of a paper version, as submitted to Turnitin (diagnostic)')]
class ManuscriptProbeCommand extends Command
{
    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument('docid', InputArgument::REQUIRED, 'Paper version identifier (DOCID)')
            ->addOption('save', null, InputOption::VALUE_REQUIRED, 'Write the downloaded file to this path (must not exist)');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $docId = filter_var($input->getArgument('docid'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($docId === false) {
            $io->error('The DOCID must be a positive integer.');
            return Command::INVALID;
        }

        $savePath = $input->getOption('save');

        if (is_string($savePath) && file_exists($savePath)) {
            $io->error(sprintf('%s already exists.', $savePath));
            return Command::INVALID;
        }

        if (is_string($savePath) && !is_writable(dirname($savePath))) {
            $io->error(sprintf('%s is not a writable directory.', dirname($savePath)));
            return Command::INVALID;
        }

        $this->bootstrap();

        try {
            $manuscript = $this->getSource($output)->fetch($docId);
        } catch (ManuscriptSourceException $e) {
            $io->error(sprintf('%s (%s)', $e->getMessage(), $e->isRetryable() ? 'retryable' : 'final'));
            return Command::FAILURE;
        }

        if ($manuscript === null) {
            $io->warning('No analysable manuscript for this DOCID (run with -v to see the reason).');
            return Command::FAILURE;
        }

        $io->definitionList(
            ['File name' => $manuscript->fileName],
            ['Size' => sprintf('%d bytes', $manuscript->size)],
            ['MIME type' => $manuscript->mimeType]
        );

        try {
            if (is_string($savePath)) {
                $this->save($manuscript->stream, $savePath);
                $io->success(sprintf('Saved to %s', $savePath));
            }
        } catch (RuntimeException $e) {
            $io->error(sprintf('The file could not be saved: %s', $e->getMessage()));
            return Command::FAILURE;
        } finally {
            $manuscript->stream->close();
        }

        return Command::SUCCESS;
    }

    /**
     * @throws RuntimeException the file cannot be created or written
     */
    private function save(StreamInterface $stream, string $path): void
    {
        $target = Utils::streamFor(Utils::tryFopen($path, 'xb'));

        try {
            Utils::copyToStream($stream, $target);
        } finally {
            $target->close();
        }
    }

    /**
     * The service logs every step; with -v the log lines (and the reason of a null result) are printed too.
     */
    protected function getSource(OutputInterface $output): ManuscriptSourceInterface
    {
        // Same log file as the Turnitin integration, plus the console with -v
        $logger = LoggerFactory::rotating(RepositoryManuscriptSource::LOG_CHANNEL);

        if ($output->isVerbose()) {
            $logger->pushHandler(new StreamHandler('php://stdout', Level::Debug));
        }

        return RepositoryManuscriptSource::create($logger);
    }

    /**
     * @throws Zend_Application_Exception
     * @throws Zend_Db_Exception
     */
    protected function bootstrap(): void
    {
        new Bootstrapper();
    }
}
