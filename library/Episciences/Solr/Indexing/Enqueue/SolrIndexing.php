<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Enqueue;

use Episciences\AppRegistry;
use Episciences\Messenger\Dbal\DbalConnectionFactory;
use Episciences\Messenger\Enqueue\DispatcherFactory;
use Episciences\Solr\Indexing\Messenger\SolrIndexTransport;
use Throwable;
use Zend_Db_Table_Abstract;

/**
 * Static facade over SolrIndexQueuePort for the global-namespace / static
 * trigger call sites (Episciences\Trait\Tools::index(),
 * Episciences_PapersManager::delete(), Episciences_Import,
 * scripts/Import.php, scripts/update_papers.php,
 * AdministratepaperController::saveothervolumesAction()) that have no
 * constructor-injection seam of their own — this app has no DI container
 * anywhere (see scripts/console.php).
 *
 * Mirrors the existing lazy-build + memoize + explicit-setter convention
 * already used by Ccsd_Search_Solr::getSolrIndexingClient()/
 * setSolrIndexingClient() (library/Ccsd/Search/Solr.php) rather than
 * introducing a new pattern: a static getter builds and caches a
 * SolrIndexQueuePort on first use per request/process, and setPort() lets
 * tests inject a different instance.
 *
 * enqueueIndex()/enqueueDelete() only swallow a failure to *build* the port
 * (DB hiccup, etc.) — every trigger call site treats indexing as a
 * best-effort side effect of a change that has already been committed. Once
 * built, the port call itself is NOT wrapped here: enqueueDelete() must let
 * DeletePaperMessage's constructor validation (no docId, no solrQuery — a
 * caller bug) propagate immediately, not be swallowed alongside a genuine
 * infrastructure failure.
 */
final class SolrIndexing
{
    private static ?SolrIndexQueuePort $port = null;

    public static function enqueueIndex(int $docId, int $priority = 0): void
    {
        $port = self::tryGetPort();

        if ($port !== null) {
            $port->enqueueIndex($docId, $priority);
        }
    }

    public static function enqueueDelete(?int $docId = null, ?string $solrQuery = null): void
    {
        $port = self::tryGetPort();

        if ($port !== null) {
            $port->enqueueDelete($docId, $solrQuery);
        }
    }

    private static function tryGetPort(): ?SolrIndexQueuePort
    {
        try {
            return self::getPort();
        } catch (Throwable $e) {
            AppRegistry::getMonoLogger()?->error(sprintf(
                'Could not build the Solr indexing port: %s',
                $e->getMessage()
            ));

            return null;
        }
    }

    public static function getPort(): SolrIndexQueuePort
    {
        if (self::$port === null) {
            $connection = DbalConnectionFactory::fromZendAdapter(Zend_Db_Table_Abstract::getDefaultAdapter());
            $dispatcher = DispatcherFactory::create(
                $connection,
                SolrIndexTransport::config(),
                SolrIndexTransport::messageClasses(),
                new DbalEnqueueFailureStore($connection),
                AppRegistry::getMonoLogger()
            );

            self::setPort(new SolrIndexQueuePort($dispatcher));
        }

        return self::$port;
    }

    public static function setPort(SolrIndexQueuePort $port): void
    {
        self::$port = $port;
    }
}
