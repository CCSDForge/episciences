<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection as DbalConnection;
use Episciences\Messenger\Enqueue\AbstractDbalEnqueueFailureStore;
use Episciences\Messenger\Transport\TransportConfig;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;

/**
 * Everything that differs between Messenger queues (Solr indexing, Next.js
 * revalidation, ...) that episciences:worker / episciences:queue need in
 * order to stay generic. Lives in scripts/, not library/, because
 * handlers()/bootstrap() depend on the non-autoloadable bootstrap traits
 * (BootstrapsSolrEnvironment, BootstrapsNextEnvironment).
 */
interface TransportProfileInterface
{
    public function config(): TransportConfig;

    /** @return list<class-string> */
    public function messageClasses(): array;

    public function retryStrategy(): RetryStrategyInterface;

    /** Human-readable label, e.g. 'Solr indexing queue'. */
    public function label(): string;

    /** Log channel prefix, e.g. 'solr' -> solrWorker / solrQueue channels. */
    public function logPrefix(): string;

    /**
     * Must be called before handlers(). May throw to abort the command on a
     * fatal configuration error (e.g. NEXT_BASE_URL missing).
     */
    public function bootstrap(): void;

    /**
     * Requires bootstrap() to have run first.
     *
     * @return array<class-string, list<callable>>
     */
    public function handlers(): array;

    /**
     * Requires handlers() to have run first (the Solr profile's ArrayAdapter
     * cache is created there). No-op by default.
     */
    public function registerWorkerListeners(EventDispatcher $dispatcher): void;

    public function failureStore(DbalConnection $connection): AbstractDbalEnqueueFailureStore;

    /** @param array<string, mixed> $row */
    public function rebuildMessage(array $row): object;

    /** @return list<string> Row columns to display for --list-dispatch-failures. */
    public function dispatchFailureColumns(): array;
}
