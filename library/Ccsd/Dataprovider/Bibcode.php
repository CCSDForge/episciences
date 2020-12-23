<?php

/*
 * La classe Ccsd_Dataprovider_Bibcode permet de récupérer le XML de Bibcode correspondant à un identifiant
 *
 *
 * @author S. Denoux
 */

class Ccsd_Dataprovider_Bibcode extends Ccsd_Dataprovider
{

    const BIBCODE_URL = "http://cdsads.u-strasbg.fr/cgi-bin/nph-bib_query?db_key=AST&data_type=XML&bibcode=";

    /**
     * @param $id
     * @return Ccsd_Externdoc_Bibcode|null
     */
    public function getDocument($id)
    {
        $this->_id = $id;
        $xmlDom = $this->requestXml(NULL);

        if(!$xmlDom) {
            return null;
        }

        $doc = Ccsd_Externdoc_Bibcode::createFromXML($id, $xmlDom);

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
        if ($url == NULL) {
            $url = self::BIBCODE_URL . $this->_id;
        }

        return parent::requestXml($url, $postfield, $timeout);
    }
}