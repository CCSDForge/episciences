<?php

declare(strict_types=1);

namespace Episciences\Next;

use Episciences\AppRegistry;
use Episciences\Messenger\Dbal\DbalConnectionFactory;
use Episciences\Messenger\Enqueue\DispatcherFactory;
use Episciences\Next\Enqueue\DbalNextRevalidationFailureStore;
use Episciences\Next\Enqueue\NextRevalidationQueuePort;
use Episciences\Next\Messenger\NextRevalidationTransport;
use Throwable;
use Zend_Db_Table_Abstract;

/**
 * Static facade over NextRevalidationQueuePort for the ~20 trigger call
 * sites (Paper, PapersManager, Volume, VolumesManager, Section,
 * SectionsManager, VolumesAndSectionsManager, JournalNews, User,
 * Volume/PapersManager, AdministratepaperController) that have no
 * constructor-injection seam of their own — this app has no DI container
 * anywhere (see scripts/console.php).
 *
 * All methods are no-ops when EPISCIENCES_ENABLE_NEXT_FRONT is not defined
 * or falsy.
 *
 * Mirrors the lazy-build + memoize + explicit-setter convention already used
 * by Episciences\Solr\Indexing\Enqueue\SolrIndexing: a static getter builds
 * and caches a NextRevalidationQueuePort on first use per request/process,
 * and setPort() lets tests inject a different instance.
 *
 * enqueueTag()/enqueueTags() only swallow a failure to *build* the port (DB
 * hiccup, etc.) — every trigger call site treats revalidation as a
 * best-effort side effect of a change that has already been committed. Once
 * built, NextRevalidationQueuePort's own calls are never wrapped here: they
 * cannot throw (blank input is ignored, not rejected, and dispatch failures
 * are already caught by BoundedRetryDispatcher) — see its class docblock.
 */
final class RevalidationService
{
    private static ?NextRevalidationQueuePort $port = null;

    private static function isEnabled(): bool
    {
        return defined('EPISCIENCES_ENABLE_NEXT_FRONT') && (bool)EPISCIENCES_ENABLE_NEXT_FRONT;
    }

    /**
     * Enqueue multiple cache revalidation tags for a journal.
     *
     * @param string   $rvcode Journal code (e.g. "epijinfo")
     * @param string[] $tags   Cache tags to invalidate
     */
    public static function enqueueTags(string $rvcode, array $tags): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $port = self::tryGetPort();

        if ($port !== null) {
            $port->enqueueTags($rvcode, $tags);
        }
    }

    /**
     * Enqueue a single cache revalidation tag for a journal.
     */
    public static function enqueueTag(string $rvcode, string $tag): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $port = self::tryGetPort();

        if ($port !== null) {
            $port->enqueueTag($rvcode, $tag);
        }
    }

    private static function tryGetPort(): ?NextRevalidationQueuePort
    {
        try {
            return self::getPort();
        } catch (Throwable $e) {
            AppRegistry::getMonoLogger()?->error(sprintf(
                'Could not build the Next.js revalidation port: %s',
                $e->getMessage()
            ));

            return null;
        }
    }

    public static function getPort(): NextRevalidationQueuePort
    {
        if (self::$port === null) {
            $connection = DbalConnectionFactory::fromZendAdapter(Zend_Db_Table_Abstract::getDefaultAdapter());
            $dispatcher = DispatcherFactory::create(
                $connection,
                NextRevalidationTransport::config(),
                NextRevalidationTransport::messageClasses(),
                new DbalNextRevalidationFailureStore($connection),
                AppRegistry::getMonoLogger()
            );

            self::setPort(new NextRevalidationQueuePort($dispatcher));
        }

        return self::$port;
    }

    public static function setPort(NextRevalidationQueuePort $port): void
    {
        self::$port = $port;
    }
}
