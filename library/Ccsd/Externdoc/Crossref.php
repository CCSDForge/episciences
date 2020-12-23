<?php


class Ccsd_Externdoc_Crossref extends Ccsd_Externdoc
{
    /**
     * @var string
     */
    protected $_idtype = "doi";

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


    const REL_PERSON_FIRST = 'given_name';
    const REL_PERSON_LAST  = 'surname';
    const REL_PERSON_ORCID = 'ORCID';

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
     * @param string $pathAuthors   (Xpath  string)
     * @return array
     */
    public function getAuthors($pathAuthors): array
    {
        $authors = [];
        $xpathObject = $this->getDomPath();
        $nodeAuthors = $xpathObject->query($pathAuthors);
        foreach ($nodeAuthors as $node) {
            $author = [];
            $firstNames = $this->getNodesValue($xpathObject, self::REL_PERSON_FIRST, $node);
            if (!empty($firstNames)) {
                $author['firstname'] = self::cleanFirstname($firstNames);
            }
            $lastNames = $this->getNodesValue($xpathObject,  self::REL_PERSON_LAST, $node);
            if (!empty($lastNames)) {
                $author['lastname'] = $lastNames;
            }

            $orcIds = $this->getNodesValue($xpathObject,  self::REL_PERSON_ORCID, $node);
            if (!empty($orcIds)) {
                $author['orcid'] = $orcIds;
            }
            // $affiliationsNode = $domxpathAut->query(self::REL_ROOT_AUT . self::REL_XPATH_AUT_AFFILIATION);
            // $affiliations = $this->getInraAffiliation($affiliationsNode);
            // if (!empty($affiliations)) {
            //    $author['affiliation'] = $affiliations;
            //}
            //$extAffiliationsNode = $domxpathAut->query(self::REL_ROOT_AUT . self::REL_XPATH_EXTAUT_EXTAFFILIATION);
            //$extAffiliations = $this->getExtAffiliation($extAffiliationsNode);
            //if (!empty($extAffiliations)) {
            //    $author['affiliation externe'] = $extAffiliations;
            //}

            //$emails = $this->getValue(self::REL_ROOT_AUT . self::REL_XPATH_AUT_EMAIL, $domxpathAut);
            //if (!empty($emails)) {
            //    $author['email'] = $emails;
            //}
            if (!empty($author)) {
                $authors[] = $author;
            }
        }
        return $authors;
    }
    /**
     * @param $year
     * @param $month
     * @param $day
     * @return string
     */
    protected function formateDate($year, $month, $day)
    {
        $dateString = "";

        if (!empty($year)) {
            $dateString .= $this->arrayToString($year);
        }

        if (!empty($month)) {
            if (!empty($dateString)) {
                $dateString .= '-';
            }

            $dateString .= self::addZeroInDate($this->arrayToString($month));
        }

        if (!empty($day)) {
            if (!empty($dateString)) {
                $dateString .= '-';
            }

            $dateString .= self::addZeroInDate($this->arrayToString($day));
        }

        return Ccsd_Tools::str2date($dateString);
    }

    /**
     * Formatage des pages premiere - derniere
     * @param $first
     * @param $last
     * @return string
     */
    protected function formatePage($first, $last)
    {
        if (!empty($first) && !empty($last)) {
            return $first . "-" . $last;
        }

        if (!empty($first)) {
            return $first;
        }

        return $last;
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


foreach (glob(__DIR__."/Crossref/*.php") as $filename)
{
    require_once($filename);
}