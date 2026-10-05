<?php

declare(strict_types=1);

namespace Episciences\Messenger\Enqueue;

/**
 * Durable record of a dispatch that a BoundedRetryDispatcher could not
 * enqueue even after its bounded retry — the one case Messenger's own
 * retry/failure transport cannot cover, since no message row was ever
 * created to retry.
 */
interface EnqueueFailureStoreInterface
{
    /** @param array<string, mixed> $payload */
    public function record(string $action, array $payload, string $errorMessage): void;
}
