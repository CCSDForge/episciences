<?php

/**
 * Class Ccsd_Externdoc_Pubmed2
 */
class Ccsd_Externdoc_Pubmed extends Ccsd_Externdoc
{
   /**
     * @var string
     */
    protected $_idtype = "pubmed";

    /**
     * Clé : Le XPATH qui permet de repérer la classe => Valeur : La classe à créer
     * TODO : remplir dynamiquement au chargement de la classe... trouver comment faire !
     * @var array
     */
    static public $_existing_types = [];

    protected $_xmlNamespace = array();

    /**
     * @var DOMXPath
     */
    protected $_domXPath = null;


   /**
     * Création d'un Doc Crossref à partir d'un XPATH
     * L'objet Crossref est seulement une factory pour un sous-type réel.
     * @param string $id
     * @param DOMDocument $xmlDom
     */
    static public function createFromXML($id, $xmlDom)
    {
        $domxpath = new DOMXPath($xmlDom);

        // On recherche le type de document associé au DOI à partir du XPATH de référence
        foreach (self::$_existing_types as $order => $xpath2class) {
            /**
             * @var string  $xpath
             * @var Ccsd_Externdoc $type
             */
            foreach ($xpath2class as $xpath => $type) {

                if ($domxpath->query($xpath)->length > 0) {
                    return $type::createFromXML($id, $xmlDom);
                }
            }
        }

        return null;
    }

    /**
     * @param $type
     */
    static public function registerType($xpath, $type, $order = 50)
    {
        self::$_existing_types[$order][$xpath] = $type;
        // Il faut trier suivant l'ordre car PHP ne tri pas numeriquement par defaut
        ksort(self::$_existing_types);
    }
}

foreach (glob(__DIR__."/Pubmed/*.php") as $filename)
{
    require_once($filename);
}