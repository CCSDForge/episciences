<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Enqueue;

use Episciences\AppRegistry;
use Episciences\Solr\Indexing\Messenger\Message\DeletePaperMessage;
use Episciences\Solr\Indexing\Messenger\Message\IndexPaperMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * The single entry point used to enqueue Solr indexing/deletion work — by the
 * solr:* CLI commands directly, and by every trigger call site (paper
 * publication, deletion, import) indirectly via
 * Episciences\Solr\Indexing\Enqueue\SolrIndexing.
 *
 * dispatch() only ever fails on a producer-side problem (DB blip while
 * sending — a plain INSERT into messenger_messages): Messenger's own retry
 * strategy and failure transport (see MessengerFactory) cannot help here,
 * since no message row exists yet to retry. A short bounded retry absorbs
 * the common transient case; if it's still failing after that, the call is
 * recorded in $failureStore instead of being silently lost, and this method
 * still does not throw — every trigger call site treats indexing as a
 * best-effort side effect of a change that has already been committed.
 */
final class SolrIndexQueuePort
{
    private const MAX_ATTEMPTS = 3;
    private const RETRY_DELAYS_MICROSECONDS = [100_000, 300_000];

    public function __construct(
        private readonly MessageBusInterface $sendBus,
        private readonly EnqueueFailureStoreInterface $failureStore,
    ) {
    }

    public function enqueueIndex(int $docId, int $priority = 0): void
    {
        $this->dispatchWithRetry(
            fn () => $this->sendBus->dispatch(new IndexPaperMessage($docId, $priority)),
            'index',
            $docId,
            $priority,
            null
        );
    }

    public function enqueueDelete(?int $docId = null, ?string $solrQuery = null): void
    {
        $this->dispatchWithRetry(
            fn () => $this->sendBus->dispatch(new DeletePaperMessage($docId, $solrQuery)),
            'delete',
            $docId,
            0,
            $solrQuery
        );
    }

    private function dispatchWithRetry(callable $dispatch, string $action, ?int $docId, int $priority, ?string $solrQuery): void
    {
        $lastError = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $dispatch();

                return;
            } catch (Throwable $e) {
                $lastError = $e;

                if ($attempt < self::MAX_ATTEMPTS) {
                    usleep(self::RETRY_DELAYS_MICROSECONDS[$attempt - 1]);
                }
            }
        }

        AppRegistry::getMonoLogger()?->critical(sprintf(
            'Solr enqueue dispatch failed after %d attempts (action=%s, docid=%s): %s',
            self::MAX_ATTEMPTS,
            $action,
            $docId !== null ? (string)$docId : 'null',
            $lastError->getMessage()
        ));

        $this->recordFailure($action, $docId, $priority, $solrQuery, $lastError->getMessage());
    }

    private function recordFailure(string $action, ?int $docId, int $priority, ?string $solrQuery, string $errorMessage): void
    {
        try {
            $this->failureStore->record($action, $docId, $priority, $solrQuery, $errorMessage);
        } catch (Throwable $e) {
            // The failure store shares the same DB connection as the transport
            // that just failed to dispatch — if it's unreachable, there is no
            // local durable option left. Log loudly and give up gracefully.
            AppRegistry::getMonoLogger()?->critical(sprintf(
                'Solr enqueue failure could not even be recorded (action=%s, docid=%s): %s',
                $action,
                $docId !== null ? (string)$docId : 'null',
                $e->getMessage()
            ));
        }
    }
}
