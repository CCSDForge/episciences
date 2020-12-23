<?php
/**
 *
 * @see Zend_Paginator_Adapter_Interface
 */
require_once 'Zend/Paginator/Adapter/Interface.php';

/**
 * Adapter de pagination pour Solarium
 *
 */
class Ccsd_Paginator_Adapter_Solarium implements Zend_Paginator_Adapter_Interface
{

    /**
     *
     * @var object Solarium\QueryType\Select\Query\Query
     */
    protected $_query = null;

    /**
     *
     * @var object Solarium\Client
     */
    protected $_client = null;

    /**
     *
     * @var integer nombre de résultats
     */
    protected $_count = null;

    /**
     * Ccsd_Paginator_Adapter_Solarium constructor.
     * @param \Solarium\Client                       $client
     * @param \Solarium\QueryType\Select\Query\Query $query
     */
    public function __construct (Solarium\Client $client, Solarium\QueryType\Select\Query\Query $query)
    {
        $this->_client = $client;
        $this->_query = $query;
    }

    /**
     * @param int $offset
     * @param int $itemCountPerPage
     * @return \Solarium\Core\Query\Result\ResultInterface
     */
    public function getItems ($offset, $itemCountPerPage)
    {
        $this->_query->setRows($itemCountPerPage);
        $this->_query->setStart($offset);

        // manually create a request for the query
        $request = $this->_client->createRequest($this->_query);
        $response = $this->_client->executeRequest($request);

        // and finally you can convert the response into a result
        $resultset = $this->_client->createResult($this->_query, $response);

//         echo 'URI : ' . $request->getUri();
//         echo '<br>';

        $this->_count = $resultset->getNumFound();

        return $resultset;
    }

    /**
     * @return int
     */
    public function count ()
    {
        return $this->_count;
    }

}

