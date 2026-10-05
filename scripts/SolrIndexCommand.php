<?php

declare(strict_types=1);

require_once __DIR__ . '/Solr/BootstrapsSolrEnvironment.php';
require_once __DIR__ . '/Solr/ResolvesDocIdOptions.php';

use Episciences\Solr\Indexing\Client\SolariumClientFactory;
use Episciences\Solr\Indexing\Enqueue\SolrIndexing;
use Episciences\Solr\Indexing\Messenger\Handler\IndexPaperMessageHandler;
use Episciences\Solr\Indexing\Messenger\Message\IndexPaperMessage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Solr indexing pipeline (Symfony Messenger + Doctrine DBAL transport).
 * Enqueues (or synchronously runs) re-indexing for one or more papers by
 * docid / SQL clause / file — see library/Episciences/Solr/Indexing/ for the
 * underlying implementation.
 */
class SolrIndexCommand extends Command
{
    use BootstrapsSolrEnvironment;
    use ResolvesDocIdOptions;

    protected static $defaultName = 'solr:index';

    protected function configure(): void
    {
        $this
            ->setDescription('Enqueue (or synchronously run) Solr re-indexing for one or more papers')
            ->addOption('docid', null, InputOption::VALUE_REQUIRED, 'Index only this DOCID.')
            ->addOption('sqlwhere', null, InputOption::VALUE_REQUIRED, "SQL WHERE clause to select DOCIDs (e.g. 'STATUS = 6'). TRUSTED INPUT ONLY — passed as-is to the query.")
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Path to a file of DOCIDs, one per line.')
            ->addOption('priority', null, InputOption::VALUE_REQUIRED, 'Message priority (informational only — the Doctrine DBAL transport does not reorder by priority, unlike legacy INDEX_QUEUE.PRIORITY).', 0)
            ->addOption('sync', null, InputOption::VALUE_NONE, 'Build and send each document immediately instead of enqueuing it (bypasses Messenger entirely). Useful for small manual reindexes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Solr indexing');

        // --sqlwhere resolution needs the DB adapter, so bootstrap runs before
        // resolveDocIds() here (unlike simpler commands that validate first).
        $this->bootstrapSolrEnvironment();

        $docIds = $this->resolveDocIds($input, $io);
        if ($docIds === null) {
            return Command::FAILURE;
        }

        $logger = $this->createSolrLogger($io, 'solrIndex');
        $priority = (int)$input->getOption('priority');

        if ((bool)$input->getOption('sync')) {
            return $this->runSync($docIds, $priority, $io, $logger);
        }

        foreach ($docIds as $docId) {
            SolrIndexing::enqueueIndex($docId, $priority);
        }

        $logger->info(sprintf('%d paper(s) enqueued for indexing.', count($docIds)));
        $io->success(sprintf('%d paper(s) enqueued for indexing.', count($docIds)));

        return Command::SUCCESS;
    }

    /** @param list<int> $docIds */
    private function runSync(array $docIds, int $priority, SymfonyStyle $io, \Monolog\Logger $logger): int
    {
        $handler = new IndexPaperMessageHandler($this->createDocumentBuilder(), new SolariumClientFactory());

        $io->progressStart(count($docIds));
        $failures = 0;

        foreach ($docIds as $docId) {
            try {
                $handler(new IndexPaperMessage($docId, $priority));
            } catch (\Throwable $e) {
                $failures++;
                $logger->error(sprintf('solr:index --sync docid=%d: %s', $docId, $e->getMessage()));
            }
            $io->progressAdvance();
        }

        $io->progressFinish();

        if ($failures > 0) {
            $io->error(sprintf('%d/%d paper(s) failed to index.', $failures, count($docIds)));

            return Command::FAILURE;
        }

        $io->success(sprintf('%d paper(s) indexed synchronously.', count($docIds)));

        return Command::SUCCESS;
    }
}
