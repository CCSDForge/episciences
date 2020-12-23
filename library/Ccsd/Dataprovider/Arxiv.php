<?php

/*
 * La classe Ccsd_Dataprovider_Arxiv permet de récupérer le XML d'Arxiv correspondant à un identifiant
 *
 *
 * @author S. Denoux
 */

class Ccsd_Dataprovider_Arxiv extends Ccsd_Dataprovider
{

    const ARXIV_URL = "http://export.arXiv.org/oai2";

    public $_URL = "http://arxiv.org/abs";

    /**
     * @param $id
     * @return Ccsd_Externdoc_Arxiv|null
     */
    public function getDocument($id)
    {
        $this->_id = $id;
        $xmlDom = $this->requestXml(NULL);

        if(!$xmlDom) {
            return null;
        }

        $doc = Ccsd_Externdoc_Arxiv::createFromXML($id, $xmlDom);

        if (!$doc) {
            $this->_error = 'library_meta_badid';
            return null;
        }

        $doc->setAdapter($this->_dbAdapter);

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
        if ($postfield == NULL) {
            //---------
            $postfield = array();
            $postfield["metadataPrefix"] = "oai_dc";
            $postfield["verb"] = "GetRecord";
            $postfield["identifier"] = "oai:arXiv.org:" . $this->_id;
        }

        if ($url == NULL) {
            $url = self::ARXIV_URL;
        }

        return parent::requestXml($url, $postfield, $timeout);
    }
}