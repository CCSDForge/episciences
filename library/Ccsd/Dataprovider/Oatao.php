<?php

/*
 * La classe Ccsd_Dataprovider_Oatao permet de récupérer le XML d'OATAO correspondant à un identifiant
 *
 *
 * @author S. Denoux
 */

class Ccsd_Dataprovider_Oatao extends Ccsd_Dataprovider
{

    const OATAO_URL = "http://oatao.univ-toulouse.fr/cgi/oai2";

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

        $doc = Ccsd_Externdoc_Oatao::createFromXML($id, $xmlDom);

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
        //---------
        $varPost = array();
        $varPost["metadataPrefix"] = "oai_dc";
        $varPost["verb"] = "GetRecord";
        $varPost["identifier"] = "oai:oatao.univ-toulouse.fr:" . $this->_id;

        return parent::requestXml(self::OATAO_URL, $varPost, $timeout);
    }
}