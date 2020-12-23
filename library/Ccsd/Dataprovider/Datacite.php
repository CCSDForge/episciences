<?php

/*
 * La classe Ccsd_Dataprovider_Datacite permet de récupérer le XML de datacite correspondant à un identifiant
 *
 *
 * @author S. Denoux
 */

class Ccsd_Dataprovider_Datacite extends Ccsd_Dataprovider
{

    protected $_url = "https://api.datacite.org/works";

    /**
     * @param $id
     * @return null
     */
    public function getDocument($id)
    {
        $url = $this->_url . "/" . $id;

        $json = $this->requestJson($url);

        if (empty($json)) {
            $this->setError('library_meta_nourl');
            return null;
        }

        $jsonArray = json_decode($json, true);

        // Pour l'instant, l'API ne propose pas de récupérer directement le XML à partir du DOI.
        // Le JSON n'étant pas complet (manque les affiliations par exemple), on créé le document à partir du XML décodé
        $xmlstring = base64_decode ($jsonArray['data']['attributes']['xml']);

        $dom = new DOMDocument();
        $dom->loadXML((string)$xmlstring);

        $doc = Ccsd_Externdoc_Datacite::createFromXML($id, $dom);

        if (isset($doc)) {
            return $doc;
        }

        $this->setError('library_meta_nourl');
        return null;
    }

    /**
     * @param $url
     * @param int $timeout
     * @return mixed|string
     */
    public function requestJson($url, $postfield = NULL, $timeout = 10)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_USERAGENT, "CCSD - HAL Proxy");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

        curl_setopt($curl, CURLOPT_FAILONERROR, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        $json = curl_exec($curl);

        if (curl_errno($curl) == CURLE_OK) {
            curl_close($curl);
            return $json;
        }
        curl_close($curl);
        return "";
    }
}