<?php

/*
 * La classe Ccsd_Dataprovider_Inspire permet de récupérer le XML d'Inspire correspondant à un identifiant
 *
 *
 * @author S. Denoux
 */

class Ccsd_Dataprovider_Inspire extends Ccsd_Dataprovider
{
    const INSPIRE_URL = "http://inspirehep.net/record/%%ID%%/export/xd";

    /**
     * @param $id
     * @return Ccsd_Externdoc_Inspire|null
     */
    public function getDocument($id)
    {
        $this->_id = $id;
        $xmlDom = $this->requestXml(NULL);

        if(!$xmlDom) {
            return null;
        }

        $doc = Ccsd_Externdoc_Inspire::createFromXML($id, $xmlDom);

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
        $url = str_replace('%%ID%%', $this->_id, self::INSPIRE_URL);
        return parent::requestXml($url);
    }
}