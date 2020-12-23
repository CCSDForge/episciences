<?php

/*
 * La classe Ccsd_Dataprovider_Pubmedcentral permet de récupérer le XML de Pubmedcentral correspondant à un identifiant
 *
 *
 * @author S. Denoux
 */

class Ccsd_Dataprovider_Pubmedcentral extends Ccsd_Dataprovider_Pubmed
{
    protected $_baseurl = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=pmc&retmode=xml";
    
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

        $doc = Ccsd_Externdoc_Pubmedcentral::createFromXML($id, $xmlDom);

        if (!$doc) {
            $this->_error = 'library_meta_badid';
            return null;
        }

        return $doc;
    }
}