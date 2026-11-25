<?php

declare(strict_types=1);

namespace Episciences\Solr\Indexing\Client;

use Ccsd_Search_Solr;
use Solarium\Client;

/**
 * Thin wrapper around Ccsd_Search_Solr::getSolrIndexingClient() — the endpoint
 * configuration (ENDPOINTS_* constants sourced from config/pwd.json) is not
 * duplicated here, only reused, so the legacy and new pipelines always talk to
 * the same Solr endpoint with the same credentials/timeout.
 */
final class SolariumClientFactory
{
    public function getIndexingClient(): Client
    {
        return Ccsd_Search_Solr::getSolrIndexingClient();
    }
}
