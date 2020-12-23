<?php

/*
 * La classe Ccsd_Dataprovider_Grobid permet de récupérer le XML de Grobid correspondant à un identifiant
 *
 *
 * @author S. Denoux
 */

class Ccsd_Dataprovider_Grobid extends Ccsd_Dataprovider
{

    protected $_xmlNamespace = array(
        'xmlns' => 'http://www.tei-c.org/ns/1.0',
    );

    // Table of references in XML format
    // Db Adapter pour accéder à la base
    public $_referencesList = [];
    const XPATH_REF = "/xmlns:TEI/xmlns:text/xmlns:back/xmlns:div/xmlns:listBibl/xmlns:biblStruct";

    /**
     * @param $id
     * @return Ccsd_Externdoc_Grobid|null
     */
    public function getDocument($id)
    {
        $this->_id = $id;
        $xmlDom = $this->requestXml(NULL);

        if(!$xmlDom) {
            return null;
        }

        $doc = Ccsd_Externdoc_Grobid::createFromXML($id, $xmlDom);

        if (!$doc) {
            $this->_error = 'library_meta_badid';
            return null;
        }

        return $doc;
    }

    /** 1- Envoi de la requête Curl à @url pour recevoir la description xml des metadonnees
     * @param $url : string
     * @param $postField : array
     *
     * @return DOMDocument : null if request failed
     * */
    public function requestXml($url, $postfield = NULL, $timeout = 10)
    {
        if ($url == NULL) {
            $url = GROBID_SERVER . GROBID_HEADER_URL;
        }
        
        if ($postfield == NULL) {
            $postfield = array('consolidate' => '1', 'input' => new CurlFile($this->_id));
        }
        
        return parent::requestXml($url, $postfield, $timeout);
    }

    //<editor-fold desc="References">
    /**
     * Call buildReferences of the parent to build the list of references
     * @param $url : string
     * @param $postContent : string
     * @return boolean
     */
    public function buildReferences($id, $url = NULL, $postContent = NULL)
    {
        $this->_id = $id;
        if($url == NULL) {
            $url = GROBID_SERVER . GROBID_REFERENCES_URL;
        }

        if($postContent == NULL) {
            $postContent = array('consolidate' => '1', 'input' => new CurlFile($this->_id));
        }

        $xmlDom = parent::requestXml($url, $postContent, 0);
        if (isset($xmlDom) && $xmlDom != "") {
            $this->_referencesList = $this->xmlToRefList($xmlDom->saveXML());
            if (count($this->_referencesList) == 0) {
                $this->_error = 'library_meta_badid';
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * Build a dom node list of the references
     * @param $xml : string
     * @return array
     */
    private function xmlToRefList($xml)
    {
        $refList = [];
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;
        $dom->substituteEntities = true;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML((string) $xml);
        $xpath = new DOMXPath($dom);

        foreach ($this->_xmlNamespace as $key => $namespace) {
            $xpath->registerNamespace($key, $namespace);
        }

        $dom_node_list = $xpath->query(self::XPATH_REF);
        foreach($dom_node_list as $n) {
            $temp_dom = new DOMDocument('1.0', 'utf-8');
            $temp_dom->formatOutput = true;
            $temp_dom->substituteEntities = true;
            $temp_dom->preserveWhiteSpace = false;
            $refList [] = $temp_dom->saveXML($temp_dom->importNode($n, true));
        }

        return $refList;
    }

    /**
     * Get the list of references in xml format
     * @return array
     */
    public function getReferences()
    {
        return $this->_referencesList;
    }
}
