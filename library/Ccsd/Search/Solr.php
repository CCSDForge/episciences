<?php

use Solarium\Client;

class Ccsd_Search_Solr
{

    const SOLR_ALPHA_SEPARATOR = '_AlphaSep_';
    const ENDPOINT_MASTER = 'master';
    const SOLR_FACET_SEPARATOR = '_FacetSep_';
    const SOLR_JOIN_SEPARATOR = '_JoinSep_';
    const SOLR_MAX_RETURNED_FACETS_RESULTS = 1000;
    const ENDPOINT_SEARCH = 'search';
    const ENDPOINT_INDEXING = 'indexing';
    /**
     * @var Solarium\Client
     */
    protected static $_solrIndexingClient;

    /**
     * @var Solarium\Client
     */
    protected static $_solrSearchClient;

    /**
     * @var array
     */
    protected static $_indexingEndpoint;
    /**
     * @var array
     */
    protected static $_searchEndpoint;
    /**
     * Core Solr
     * @var string
     */
    private $_core;
    /**
     * Handler de requête pour solr
     * @var string
     */
    private $_handler;

    /**
     * Ccsd_Search_Solr constructor.
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->setOptions($options);
    }

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options = [])
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
        if (self::$_solrSearchClient === null) {
            $client = new Client(self::getSearchEndpoint());
            $client->getPlugin('postbigrequest');
            self::setSolrSearchClient($client);
        }
        return self::$_solrSearchClient;
    }

    /**
     * @param Client $solrSearchClient
     */
    public static function setSolrSearchClient(Client $solrSearchClient)
    {
        self::$_solrSearchClient = $solrSearchClient;
    }

    /**
     * @return array
     */
    public static function getSearchEndpoint(): array
    {
        if (!self::$_searchEndpoint) {
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
        return self::$_searchEndpoint;
    }

    /**
     * @param array $searchEndpoint
     */
    public static function setSearchEndpoint(array $searchEndpoint)
    {
        self::$_searchEndpoint = $searchEndpoint;
    }

    /**
     * @return Client
     */
    public static function getSolrIndexingClient(): Solarium\Client
    {
        if (self::$_solrIndexingClient === null) {
            $client = new Solarium\Client(self::getIndexingEndpoint());
            $client->getPlugin('postbigrequest');
            self::setSolrIndexingClient($client);
        }
        return self::$_solrIndexingClient;
    }

    /**
     * @param Solarium\Client $solrClient
     */
    public static function setSolrIndexingClient(Solarium\Client $solrClient)
    {
        self::$_solrIndexingClient = $solrClient;
    }

    /**
     * @return array
     */
    public static function getIndexingEndpoint(): array
    {

        if (!self::$_indexingEndpoint) {
            $endpoint = [
                'endpoint' => [
                    self::ENDPOINT_INDEXING => [
                        'host' => ENDPOINTS_INDEXING_HOST,
                        'port' => ENDPOINTS_INDEXING_PORT,
                        'path' => ENDPOINTS_INDEXING_PATH,
                        'timeout' => ENDPOINTS_INDEXING_TIMEOUT,
                        'username' => ENDPOINTS_INDEXING_USERNAME,
                        'password' => ENDPOINTS_INDEXING_PASSWORD,
                        'core' => ENDPOINTS_CORENAME
                    ]
                ]
            ];
            self::setIndexingEndpoint($endpoint);
        }

        return self::$_indexingEndpoint;
    }

    /**
     * @param array $indexingEndpoint
     */
    public static function setIndexingEndpoint(array $indexingEndpoint)
    {
        self::$_indexingEndpoint = $indexingEndpoint;
    }

    public static function facetStringResultAsArray($string)
    {
        return explode(self::SOLR_FACET_SEPARATOR, $string);
    }

    public static function getConstantesFacet()
    {
        return [
            self::SOLR_ALPHA_SEPARATOR,
            self::SOLR_FACET_SEPARATOR,
            self::SOLR_JOIN_SEPARATOR
        ];
    }

    /**
     * @param string $endpointType
     * @return string
     */
    public function getEndPointUrl(string $endpointType = self::ENDPOINT_SEARCH): string
    {
        if ($endpointType === self::ENDPOINT_INDEXING) {
            return ENDPOINTS_INDEXING_PROTOCOL . '://' . ENDPOINTS_INDEXING_HOST . ':' . ENDPOINTS_INDEXING_PORT . ENDPOINTS_INDEXING_PATH . '/' . $this->getCore() . '/' . $this->getHandler() . '/';
        }
        return ENDPOINTS_SEARCH_PROTOCOL . '://' . ENDPOINTS_SEARCH_HOST . ':' . ENDPOINTS_SEARCH_PORT . ENDPOINTS_SEARCH_PATH . '/' . $this->getCore() . '/' . $this->getHandler() . '/';


    }

    /**
     *
     * @return string $_core
     */
    public function getCore()
    {
        return $this->_core;
    }

    /**
     * Get solr Core
     * @param string $_core
     */
    public function setCore($_core)
    {
        $this->_core = $_core;
        return $this;
    }

    /**
     * @return string
     */
    public function getHandler()
    {
        return $this->_handler;
    }

    /**
     * @param string $_handler
     * @return $this
     */
    public function setHandler($_handler = 'select')
    {
        $this->_handler = $_handler;
        return $this;
    }

}
