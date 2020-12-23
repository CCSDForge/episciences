<?php

/**
 * La classe Ccsd_Dataprovider_Ird permet de récupérer le XML d'IRD correspondant à un identifiant
 *
 *
 * @author S. Denoux
 */

class Ccsd_Dataprovider_Ird extends Ccsd_Dataprovider
{

    const IRD_URL = "http://www.documentation.ird.fr/hor/";

    /**
     * @param $id
     * @return Ccsd_Externdoc_Ird|null
     */
    public function getDocument($id)
    {
        $this->_id = $id;
        $xmlDom = $this->requestXml(self::IRD_URL);

        if(!$xmlDom) {
            $this->setError('library_meta_nourl');
            return null;
        }

        $doc = Ccsd_Externdoc_Ird::createFromXML($id, $xmlDom);

        if (!$doc) {
            $this->_error = 'library_meta_badid';
            return null;
        }

        return $doc;
    }

    /**
     * Construction des metadatas
     * @param string $url
     * @param string $postfield
     * @param int $timeout
     * @return DOMDocument
     */

    public function requestXml($url, $postfield = NULL, $timeout = 10)
    {
        return parent::requestXml($url . $this->_id .'.mods');
    }
}