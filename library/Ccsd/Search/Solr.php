<?php

use Solarium\Client;

class Ccsd_Search_Solr
{

    public const SOLR_ALPHA_SEPARATOR = '_AlphaSep_';
    public const SOLR_FACET_SEPARATOR = '_FacetSep_';
    public const SOLR_JOIN_SEPARATOR = '_JoinSep_';
    public const SOLR_MAX_RETURNED_FACETS_RESULTS = 1000;
    public const ENDPOINT_SEARCH = 'search';
    public const ENDPOINT_INDEXING = 'indexing';
    /**
     * @var Solarium\Client
     */
    protected static ?Client $solrIndexingClient = null;


    protected static ?Client $solrSearchClient = null;


    protected static ?array $searchEndpoint = null;
    /**
     * Core Solr
     */
    private string $core;
    /**
     * Handler de requête pour solr
     */
    private string $handler;


    public function __construct(array $options = [])
    {
        $this->setOptions($options);
    }

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options = []): Ccsd_Search_Solr
    {
        $this->setCore(ENDPOINTS_CORENAME);
        if (isset($options['handler'])) {
            $this->setHandler($options['handler']);
        } else {
            $this->setHandler();
        }
        return $this;
    }

    /**
     * @return Client
     */
    public static function getSolrSearchClient(): Client
    {
        if (self::$solrSearchClient === null) {
            $adapter = new Solarium\Core\Client\Adapter\Curl();
            $adapter->setTimeout(ENDPOINTS_SEARCH_TIMEOUT);
            $eventDispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
            $client = new Solarium\Client($adapter, $eventDispatcher, self::getSolrEndpoint());
            self::setSolrSearchClient($client);
        }
        return self::$solrSearchClient;
    }

    /**
     * @param Client $solrSearchClient
     */
    public static function setSolrSearchClient(Client $solrSearchClient): void
    {
        self::$solrSearchClient = $solrSearchClient;
    }

    /**
     * @return array
     */
    public static function getSolrEndpoint(): array
    {
        if (!self::$searchEndpoint) {
            $endpoint = [
                'endpoint' => [
                    self::ENDPOINT_SEARCH => [
                        'host' => ENDPOINTS_SEARCH_HOST,
                        'port' => ENDPOINTS_SEARCH_PORT,
                        'path' => ENDPOINTS_SEARCH_PATH,
                        'timeout' => ENDPOINTS_SEARCH_TIMEOUT,
                        'username' => ENDPOINTS_SEARCH_USERNAME,
                        'password' => ENDPOINTS_SEARCH_PASSWORD,
                        'core' => ENDPOINTS_CORENAME
                    ]
                ]
            ];
            self::setSearchEndpoint($endpoint);
        }
        return self::$searchEndpoint;
    }

    /**
     * @param array $searchEndpoint
     */
    public static function setSearchEndpoint(array $searchEndpoint): void
    {
        self::$searchEndpoint = $searchEndpoint;
    }

    /**
     * @return Client
     */
    public static function getSolrIndexingClient(): Solarium\Client
    {
        if (self::$solrIndexingClient === null) {
            $adapter = new Solarium\Core\Client\Adapter\Curl();
            $adapter->setTimeout(ENDPOINTS_INDEXING_TIMEOUT);
            $eventDispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
            $client = new Solarium\Client($adapter, $eventDispatcher, self::getSolrEndpoint());
            $client->getPlugin('postbigrequest');
            self::setSolrIndexingClient($client);
        }
        return self::$solrIndexingClient;
    }

    /**
     * @param Solarium\Client $solrClient
     */
    public static function setSolrIndexingClient(Solarium\Client $solrClient): void
    {
        self::$solrIndexingClient = $solrClient;
    }


    public static function facetStringResultAsArray($string)
    {
        return explode(self::SOLR_FACET_SEPARATOR, $string);
    }

    public static function getConstantesFacet(): array
    {
        return [
            self::SOLR_ALPHA_SEPARATOR,
            self::SOLR_FACET_SEPARATOR,
            self::SOLR_JOIN_SEPARATOR
        ];
    }

    /**
     * @return string
     */
    public function getEndPointUrl(): string
    {
        return sprintf("%s://%s:%s%s/%s/%s/", ENDPOINTS_SEARCH_PROTOCOL, ENDPOINTS_SEARCH_HOST, ENDPOINTS_SEARCH_PORT, ENDPOINTS_SEARCH_PATH, $this->getCore(), $this->getHandler());
    }

    /**
     * @return string $_core
     */
    public function getCore(): string
    {
        return $this->core;
    }

    /**
     * Get solr Core
     * @param string $core
     * @return Ccsd_Search_Solr
     */
    public function setCore(string $core): Ccsd_Search_Solr
    {
        $this->core = $core;
        return $this;
    }

    /**
     * @return string
     */
    public function getHandler(): string
    {
        return $this->handler;
    }

    /**
     * @param string $handler
     * @return $this
     */
    public function setHandler(string $handler = 'select'): Ccsd_Search_Solr
    {
        $this->handler = $handler;
        return $this;
    }

}
