<?php

/*
 * La classe Ccsd_Dataprovider_Cern permet de récupérer le XML du Cern correspondant à un identifiant
 *
 *
 * @author S. Denoux
 */

class Ccsd_Dataprovider_Cern extends Ccsd_Dataprovider
{

    const CERN_URL = "http://cds.cern.ch/record/%%ID%%?of=xd";

    /**
     * @param $id
     * @return Ccsd_Externdoc_Cern|null
     */
    public function getDocument($id)
    {
        $this->_id = $id;
        $xmlDom = $this->requestXml(NULL);

        if(!$xmlDom) {
            return null;
        }

        $doc = Ccsd_Externdoc_Cern::createFromXML($id, $xmlDom);

        if (!$doc) {
            $this->_error = 'library_meta_badid';
            return null;
        }

        return $doc;
    }

    /*
     * Construction des metadatas
     * 
     * @return bool
     */

    public function requestXml($url, $postfield = NULL, $timeout = 10)
    {
        $url = str_replace('%%ID%%', $this->_id, self::CERN_URL);
        return parent::requestXml($url);
    }
}