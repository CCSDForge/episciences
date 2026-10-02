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

    /**
     * Matches any Solr delete query that wipes every document for a single
     * field (docid:*, revue_id_i:*, ...) or the whole core (*:*) — any schema
     * field name works as the wildcard target, not just docid/*. Matched
     * anywhere in the query, not just as an exact full-string match, so it
     * still catches a destructive clause wrapped in parentheses or combined
     * with another clause via AND/OR (e.g. "(*:*)", "*:* OR docid:1"), and the
     * open-range form "field:[* TO *]" which is equally destructive. Also
     * matches the Lucene/Solr unary "required clause" prefix ("+*:*",
     * "+docid:*") — just as destructive as the bare form, but otherwise
     * bypasses the boundary check below. The unary "-" prefix is NOT
     * included: "-*:*" alone excludes everything rather than matching it, so
     * it isn't destructive.
     */
    private const WILDCARD_DELETE_PATTERN = '/(^|[\s(])\+?(\*:\*|[A-Za-z0-9_]+:(\*|\[\s*\*\s+TO\s+\*\s*]))([\s)]|$)/i';

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

        $solrQuery = trim($message->toSolrDeleteQuery());

        if (preg_match(self::WILDCARD_DELETE_PATTERN, $solrQuery) === 1) {
            $io->caution(sprintf(
                'This deletes ALL documents matching "%s" from the Solr index — this cannot be undone.',
                $solrQuery
            ));

            // Regardless of environment (dev/staging/prod): a wildcard delete
            // is destructive everywhere, not just in prod. Default answer is
            // "no", so --no-interaction (cron/CI) refuses instead of wiping.
            if (!$io->confirm('Are you sure you want to proceed?', false)) {
                $logger->warning('solr:delete: wildcard deletion aborted by operator: ' . $solrQuery);
                $io->warning('Aborted.');

                return Command::FAILURE;
            }
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
