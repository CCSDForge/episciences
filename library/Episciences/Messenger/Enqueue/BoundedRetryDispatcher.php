<?php

declare(strict_types=1);

namespace Episciences\Messenger\Enqueue;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * Generic dispatch-with-bounded-retry used by every enqueue port (Solr
 * indexing, Next.js revalidation, ...). dispatch() only ever fails on a
 * producer-side problem (DB blip while sending — a plain INSERT into
 * messenger_messages): Messenger's own retry strategy and failure transport
 * cannot help here, since no message row exists yet to retry. A short
 * bounded retry absorbs the common transient case; if it's still failing
 * after that, the call is recorded in $failureStore instead of being
 * silently lost — this method still does not throw, since every caller
 * treats enqueueing as a best-effort side effect of a change that has
 * already been committed.
 *
 * Takes an already-built message, not a callable that builds one: a
 * constructor throwing on invalid caller-supplied arguments is a bug at the
 * call site, not a transient send failure, and must propagate immediately
 * instead of being retried 3 times and recorded as a dispatch failure.
 */
final class BoundedRetryDispatcher
{
    private const MAX_ATTEMPTS = 3;
    private const RETRY_DELAYS_MICROSECONDS = [100_000, 300_000];

    public function __construct(
        private readonly MessageBusInterface $sendBus,
        private readonly EnqueueFailureStoreInterface $failureStore,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function dispatch(object $message, string $action, array $payload): void
    {
        $lastError = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $this->sendBus->dispatch($message);

                return;
            } catch (Throwable $e) {
                $lastError = $e;

                if ($attempt < self::MAX_ATTEMPTS) {
                    usleep(self::RETRY_DELAYS_MICROSECONDS[$attempt - 1]);
                }
            }
        }

        $this->logger?->critical(sprintf(
            'Enqueue dispatch failed after %d attempts (action=%s): %s',
            self::MAX_ATTEMPTS,
            $action,
            $lastError->getMessage()
        ));

        $this->recordFailure($action, $payload, $lastError->getMessage());
    }

    /** @param array<string, mixed> $payload */
    private function recordFailure(string $action, array $payload, string $errorMessage): void
    {
        try {
            $this->failureStore->record($action, $payload, $errorMessage);
        } catch (Throwable $e) {
            // The failure store shares the same DB connection as the transport
            // that just failed to dispatch — if it's unreachable, there is no
            // local durable option left. Log loudly and give up gracefully.
            $this->logger?->critical(sprintf(
                'Enqueue failure could not even be recorded (action=%s): %s',
                $action,
                $e->getMessage()
            ));
        }
    }
}
