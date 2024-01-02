<?php
/**
 *
 * @see Zend_Paginator_Adapter_Interface
 */

use Solarium\Client;
use Solarium\Core\Query\Result\ResultInterface;
use Solarium\QueryType\Select\Query\Query;

require_once 'Zend/Paginator/Adapter/Interface.php';

/**
 * Adapter de pagination pour Solarium
 *
 */
class Ccsd_Paginator_Adapter_Solarium implements Zend_Paginator_Adapter_Interface
{

    protected ?Solarium\QueryType\Select\Query\Query $query = null;
    protected ?Solarium\Client $client = null;
    protected ?int $count = null;

    /**
     * Ccsd_Paginator_Adapter_Solarium constructor.
     * @param Client                       $client
     * @param Query $query
     */
    public function __construct (Solarium\Client $client, Solarium\QueryType\Select\Query\Query $query)
    {
        $this->client = $client;
        $this->query = $query;
    }

    /**
     * @param int $offset
     * @param int $itemCountPerPage
     * @return ResultInterface
     */
    public function getItems ($offset, $itemCountPerPage): ResultInterface
    {
        $this->query->setRows($itemCountPerPage);
        $this->query->setStart($offset);

        // manually create a request for the query
        $request = $this->client->createRequest($this->query);
        $response = $this->client->executeRequest($request);

        // and finally you can convert the response into a result
        $resultset = $this->client->createResult($this->query, $response);

        $this->count = $resultset->getNumFound();

        return $resultset;
    }

    /**
     * @return int
     */
    public function count ()
    {
        return $this->count;
    }

}

