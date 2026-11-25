<?php

declare(strict_types=1);

require_once __DIR__ . '/TransportProfileInterface.php';
require_once __DIR__ . '/../Next/BootstrapsNextEnvironment.php';

use Doctrine\DBAL\Connection as DbalConnection;
use Episciences\AppRegistry;
use Episciences\Messenger\Enqueue\AbstractDbalEnqueueFailureStore;
use Episciences\Messenger\Transport\TransportConfig;
use Episciences\Next\Enqueue\DbalNextRevalidationFailureStore;
use Episciences\Next\Messenger\HandlerFactory;
use Episciences\Next\Messenger\Message\RevalidateTagMessage;
use Episciences\Next\Messenger\NextRevalidationTransport;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;

final class NextRevalidationProfile implements TransportProfileInterface
{
    use BootstrapsNextEnvironment;

    public function config(): TransportConfig
    {
        return NextRevalidationTransport::config();
    }

    /** @return list<class-string> */
    public function messageClasses(): array
    {
        return NextRevalidationTransport::messageClasses();
    }

    public function retryStrategy(): RetryStrategyInterface
    {
        return NextRevalidationTransport::retryStrategy();
    }

    public function label(): string
    {
        return 'Next.js revalidation queue';
    }

    public function logPrefix(): string
    {
        return 'next';
    }

    public function bootstrap(): void
    {
        $this->bootstrapNextEnvironment();

        // A missing NEXT_BASE_URL is a global configuration error: retrying
        // would burn the retry budget of every message and dead-letter the
        // whole backlog at once. Failing the worker at boot instead leaves
        // everything pending, exits non-zero, and lets the supervisor complain.
        if (!defined('NEXT_BASE_URL') || NEXT_BASE_URL === '') {
            throw new InvalidArgumentException('NEXT_BASE_URL is not defined in config/pwd.json.');
        }

        if (!defined('EPISCIENCES_ENABLE_NEXT_FRONT') || !EPISCIENCES_ENABLE_NEXT_FRONT) {
            // Not fatal: the flag can legitimately be turned on later without
            // restarting the worker. Warn once at boot so an operator doesn't
            // mistake "running but idle" for "broken" (see R12).
            AppRegistry::getMonoLogger()?->warning(
                'EPISCIENCES_ENABLE_NEXT_FRONT is currently off — this worker will run but find nothing to consume until it is turned on (no restart needed once it is).'
            );
        }
    }

    public function handlers(): array
    {
        $handler = HandlerFactory::createRevalidateTagHandler(AppRegistry::getMonoLogger());

        return [
            RevalidateTagMessage::class => [$handler],
        ];
    }

    public function registerWorkerListeners(EventDispatcher $dispatcher): void
    {
        // No per-message cache to clear, unlike SolrIndexProfile's
        // volume/section cache.
    }

    public function failureStore(DbalConnection $connection): AbstractDbalEnqueueFailureStore
    {
        return new DbalNextRevalidationFailureStore($connection);
    }

    public function rebuildMessage(array $row): object
    {
        return new RevalidateTagMessage((string)$row['rvcode'], (string)$row['tag']);
    }

    /** @return list<string> */
    public function dispatchFailureColumns(): array
    {
        return ['id', 'action', 'rvcode', 'tag', 'retry_attempts', 'last_error', 'created_at'];
    }
}
