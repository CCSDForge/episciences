<?php

/**
 * classe d'acces aux providers de donnees comme Crossref, Arxiv, ...
 */

abstract class Ccsd_Dataprovider
{
    /**  Identifiant de la référence dont on cherche les metadonnees
     * @var string */
    public $_id;

    /** Nom du service dont on cherche les metadonnees
     * @var string */
    protected $_type;

    /**
     * Erreur s'il y en a une lors de la requête cURL
     * @var string
     */
    protected $_error = "";

    // C'est pas terrible qu'il y ait besoin d'une base (surtout seulement pour Arxiv)
    /** @var Zend_Db_Adapter_Abstract  */
    protected $_dbAdapter;
    /** @var \Hal\Config $_context */
    protected $_context = null;

    /**
     * Ccsd_Dataprovider constructor.
     * @param null $dbAdapter
     * @param \Hal\Config $context
     */
    public function __construct($dbAdapter=NULL, $context = null)
    {
        $this->_dbAdapter = $dbAdapter;
        $this->_context = $context;
    }

    /**
     * Renvoit un Externdoc de type spécifique au dataprovider
     * @param $id
     * @return Ccsd_Externdoc
     */
    abstract public function getDocument($id);

    /**
     * Envoi de la requête Curl à @url pour recevoir la description xml des metadonnees
     * @param $url
     * @param string $postfield  // specify data to POST to server
     * @param int $timeout
     * @return DOMDocument|null
     */
    public function requestXml($url, $postfield = NULL, $timeout = 10)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_USERAGENT, DOMAIN);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

        if (isset($postfield)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postfield);
        }

        curl_setopt($curl, CURLOPT_FAILONERROR, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        $string = curl_exec($curl);

        if (curl_errno($curl) == CURLE_OK) {
            curl_close($curl);

            try {
                $dom = new DOMDocument();
                $parsingOk = $dom->loadXML((string)$string);
                if (!$parsingOk) {
                    error_log("XML Parsing failed for $url");
                }
                return $dom;
            } catch (Exception $e) {
                error_log("Requête de récupération des métadonnées a échouée");
                $this->_error = 'library_meta_badid';
                return null;
            }
        } else {
            $this->_error = curl_error($curl);
            curl_close($curl);
            return null;
        }
    }

    /**
     * Retourne l'e type'identifiant de la métadonnée
     * @return string $type
     */
    public function getID()
    {
        return $this->_id;
    }

    /**
     * Retourne le type de la métadonnée
     * @return string
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * Retourne erreur qui a pu se produire à l'occasion de la requête cURL
     * @return string $error
     */
    public function getError()
    {
        return $this->_error;
    }

    /**
     * @param $err
     */
    public function setError($err)
    {
        $this->_error = $err;
    }
   /**
     * @return Hal\Config
     */
    public function getContext()
    {
        return $this->_context;
    }

}

