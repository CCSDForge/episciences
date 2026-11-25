<?php

namespace unit\library\Episciences\Messenger\Enqueue;

use Episciences\Messenger\Enqueue\EnqueueFailureStoreInterface;

/**
 * Minimal EnqueueFailureStoreInterface test double that records calls
 * in-memory instead of touching a database — used to assert what
 * BoundedRetryDispatcher persists once its retry budget is exhausted.
 */
final class SpyEnqueueFailureStore implements EnqueueFailureStoreInterface
{
    /** @var list<array{action: string, payload: array<string, mixed>, errorMessage: string}> */
    public array $recorded = [];

    public function record(string $action, array $payload, string $errorMessage): void
    {
        $this->recorded[] = [
            'action' => $action,
            'payload' => $payload,
            'errorMessage' => $errorMessage,
        ];
    }
}
