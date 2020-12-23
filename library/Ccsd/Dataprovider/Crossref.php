<?php

/**
 * La classe Ccsd_Dataprovider_Crossref permet de récupérer le XML de Crossref correspondant à un identifiant
 *
 * @author S. Denoux
 */

class Ccsd_Dataprovider_Crossref extends Ccsd_Dataprovider
{
    /**
     * @param $id
     * @return null
     */
    public function getDocument($id)
    {
        //-----------
        $var_post = array();
        $var_post["pid"] = CROSSREF_USER . ":" . CROSSREF_PWD;
        $var_post["format"] = "unixref";
        $var_post["id"] = ("doi:" . urlencode($id));
        $var_post["noredirect"] = "true";

        $url = CROSSREF_URL . "?";

        foreach ($var_post as $key => $val) {
            $url .= "$key=$val&";
        }

        $url = rtrim($url, "&");

        $xmlDom = $this->requestXml($url);

        if (!isset($xmlDom)) {
            $this->setError('library_meta_nourl');
            return null;
        }

        $doc = Ccsd_Externdoc_Crossref::createFromXML($id, $xmlDom);

        if (isset($doc)) {
            return $doc;
        }

        $this->setError('library_meta_nourl');
        return null;
    }
}