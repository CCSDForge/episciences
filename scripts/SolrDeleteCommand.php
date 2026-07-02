<?php

declare(strict_types=1);

require_once __DIR__ . '/Solr/BootstrapsSolrEnvironment.php';

use Episciences\Solr\Indexing\Client\SolariumClientFactory;
use Episciences\Solr\Indexing\Enqueue\SolrIndexing;
use Episciences\Solr\Indexing\Messenger\Handler\DeletePaperMessageHandler;
use Episciences\Solr\Indexing\Messenger\Message\DeletePaperMessage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Solr deletion pipeline. Enqueues (or synchronously runs) a deletion, by
 * DOCID or by raw query — see library/Episciences/Solr/Indexing/ for the
 * underlying implementation.
 */
class SolrDeleteCommand extends Command
{
    use BootstrapsSolrEnvironment;

    protected static $defaultName = 'solr:delete';

    protected function configure(): void
    {
        $this
            ->setDescription('Enqueue (or synchronously run) a Solr deletion, by DOCID or by raw query')
            ->addOption('docid', null, InputOption::VALUE_REQUIRED, 'Delete this DOCID.')
            ->addOption('query', null, InputOption::VALUE_REQUIRED, "Raw Solr delete query, e.g. 'docid:19'. TRUSTED INPUT ONLY.")
            ->addOption('sync', null, InputOption::VALUE_NONE, 'Run the deletion immediately instead of enqueuing it.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Solr deletion');

        $docIdOption = $input->getOption('docid');
        $queryOption = $input->getOption('query');

        if (($docIdOption === null) === ($queryOption === null)) {
            $io->error('Exactly one of --docid or --query is required.');

            return Command::FAILURE;
        }

        $docId = $docIdOption !== null ? (int)$docIdOption : null;
        $query = $queryOption !== null ? (string)$queryOption : null;

        $this->bootstrapSolrEnvironment();
        $logger = $this->createSolrLogger($io, 'solrDelete');

        try {
            $message = new DeletePaperMessage($docId, $query);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ((bool)$input->getOption('sync')) {
            try {
                (new DeletePaperMessageHandler(new SolariumClientFactory()))($message);
            } catch (\Throwable $e) {
                $logger->error('solr:delete --sync: ' . $e->getMessage());
                $io->error('Deletion failed: ' . $e->getMessage());

                return Command::FAILURE;
            }

            $io->success('Deletion sent synchronously.');

            return Command::SUCCESS;
        }

        SolrIndexing::enqueueDelete($docId, $query);

        $logger->info('Deletion enqueued: ' . $message->toSolrDeleteQuery());
        $io->success('Deletion enqueued.');

        return Command::SUCCESS;
    }
}
