<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Enqueue;

use Episciences\Solr\Indexing\Messenger\Dbal\DbalConnectionFactory;
use Episciences\Solr\Indexing\Messenger\MessengerFactory;
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
 */
final class SolrIndexing
{
    private static ?SolrIndexQueuePort $port = null;

    public static function enqueueIndex(int $docId, int $priority = 0): void
    {
        self::getPort()->enqueueIndex($docId, $priority);
    }

    public static function enqueueDelete(?int $docId = null, ?string $solrQuery = null): void
    {
        self::getPort()->enqueueDelete($docId, $solrQuery);
    }

    public static function getPort(): SolrIndexQueuePort
    {
        if (self::$port === null) {
            $connection = DbalConnectionFactory::fromZendAdapter(Zend_Db_Table_Abstract::getDefaultAdapter());
            $transport = MessengerFactory::createTransport($connection);
            self::setPort(new SolrIndexQueuePort(
                MessengerFactory::createSendBus($transport),
                new DbalEnqueueFailureStore($connection)
            ));
        }

        return self::$port;
    }

    public static function setPort(SolrIndexQueuePort $port): void
    {
        self::$port = $port;
    }
}
