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
class Ccsd_Paginator_Adapter_Curl implements Zend_Paginator_Adapter_Interface
{

    /**
     *
     * @var string url partielle : critère et format de sortie
     */
    protected $_urlPartielle = "";
    
    
    /**
     *
     * @var string core de recherche
     */
    protected $_core = "";
    

    /**
     *
     * @var integer nombre de résultats
     */
    protected $_count = null;
    

    public function __construct ($query, $core)
    {
        $this->_urlPartielle = $query . "&wt=phps";
        $this->_core = $core;
    }

    public function getItems ($offset, $itemCountPerPage)
    {
        $sortie = array();
        $parametresPages = "&rows=". $itemCountPerPage . "&start=" . $offset;
        $url = $this->_urlPartielle . $parametresPages;
        $resultats = unserialize(Ccsd_Tools::solrCurl($url, $this->_core));
        if ( ! is_array($resultats) || array_key_exists("error", $resultats) ) {
            $this->_count = 0;
        } else {
            $this->_count = $resultats['response']['numFound'];
            $sortie = $resultats['response']['docs'];
        }
        return $sortie;
    }

    public function count ()
    {
        if ($this->_count === null) {
            $resultats = unserialize(Ccsd_Tools::solrCurl($this->_urlPartielle, $this->_core));
            $this->_count = (! is_array($resultats) || array_key_exists("error", $resultats)) ? 0 : $resultats['response']['numFound'];
        }
        return $this->_count;
    }
    
}

