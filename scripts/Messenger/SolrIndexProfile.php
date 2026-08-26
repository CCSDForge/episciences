<?php

declare(strict_types=1);

require_once __DIR__ . '/TransportProfileInterface.php';
require_once __DIR__ . '/../Solr/BootstrapsSolrEnvironment.php';

use Doctrine\DBAL\Connection as DbalConnection;
use Episciences\Messenger\Enqueue\AbstractDbalEnqueueFailureStore;
use Episciences\Messenger\Transport\TransportConfig;
use Episciences\Solr\Indexing\Client\SolariumClientFactory;
use Episciences\Solr\Indexing\Enqueue\DbalEnqueueFailureStore;
use Episciences\Solr\Indexing\Messenger\Handler\DeletePaperMessageHandler;
use Episciences\Solr\Indexing\Messenger\Handler\IndexPaperMessageHandler;
use Episciences\Solr\Indexing\Messenger\Message\DeletePaperMessage;
use Episciences\Solr\Indexing\Messenger\Message\IndexPaperMessage;
use Episciences\Solr\Indexing\Messenger\SolrIndexTransport;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;

final class SolrIndexProfile implements TransportProfileInterface
{
    use BootstrapsSolrEnvironment;

    private ?ArrayAdapter $volumeSectionCache = null;

    public function config(): TransportConfig
    {
        return SolrIndexTransport::config();
    }

    /** @return list<class-string> */
    public function messageClasses(): array
    {
        return SolrIndexTransport::messageClasses();
    }

    public function retryStrategy(): RetryStrategyInterface
    {
        return SolrIndexTransport::retryStrategy();
    }

    public function label(): string
    {
        return 'Solr indexing queue';
    }

    public function logPrefix(): string
    {
        return 'solr';
    }

    public function bootstrap(): void
    {
        $this->bootstrapSolrEnvironment();
    }

    public function handlers(): array
    {
        // Cleared before every message via registerWorkerListeners(), so a
        // long-running worker never serves a journal/volume/section snapshot
        // older than the message currently being handled.
        $this->volumeSectionCache = new ArrayAdapter(0, false);

        $indexHandler = new IndexPaperMessageHandler($this->createDocumentBuilder($this->volumeSectionCache), new SolariumClientFactory());
        $deleteHandler = new DeletePaperMessageHandler(new SolariumClientFactory());

        return [
            IndexPaperMessage::class => [$indexHandler],
            DeletePaperMessage::class => [$deleteHandler],
        ];
    }

    public function registerWorkerListeners(EventDispatcher $dispatcher): void
    {
        if ($this->volumeSectionCache === null) {
            return;
        }

        $cache = $this->volumeSectionCache;
        $dispatcher->addListener(WorkerMessageReceivedEvent::class, static fn () => $cache->clear());
    }

    public function failureStore(DbalConnection $connection): AbstractDbalEnqueueFailureStore
    {
        return new DbalEnqueueFailureStore($connection);
    }

    public function rebuildMessage(array $row): object
    {
        $docId = $row['docid'] !== null ? (int)$row['docid'] : null;

        return match ($row['action']) {
            'index' => new IndexPaperMessage((int)$docId, (int)$row['priority']),
            'delete' => new DeletePaperMessage($docId, $row['solr_query']),
            default => throw new InvalidArgumentException(sprintf(
                'Unknown Solr dispatch-failure action "%s" for row id %s — refusing to guess between index/delete.',
                (string)$row['action'],
                (string)($row['id'] ?? '?')
            )),
        };
    }

    /** @return list<string> */
    public function dispatchFailureColumns(): array
    {
        return ['id', 'action', 'docid', 'priority', 'solr_query', 'retry_attempts', 'last_error', 'created_at'];
    }
}
