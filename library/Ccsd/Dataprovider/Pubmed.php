<?php

/*
 * La classe Ccsd_Dataprovider_Pubmed permet de récupérer le XML de Pubmed correspondant à un identifiant
 *
 *
 * @author S. Denoux
 */


class Ccsd_Dataprovider_Pubmed extends Ccsd_Dataprovider
{
    protected $_baseurl = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=pubmed&retmode=xml";

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

        $doc = Ccsd_Externdoc_Pubmed::createFromXML($id, $xmlDom);

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
        return parent::requestXml($this->_baseurl, ['id' => $this->_id]);
    }
}