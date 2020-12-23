<?php

/**
 * Class Ccsd_Externdoc_Datacite
 * @see https://support.datacite.org/docs/api
 */

class Ccsd_Externdoc_Datacite extends Ccsd_Externdoc
{

    /**
     * @var string
     */
    protected $_idtype = "doi";

    protected $_datacite_url = "https://api.datacite.org/works";

    const XPATH_COMPLETE_AUTHOR = '/xmlns:resource/xmlns:creators/xmlns:creator';
    const XPATH_CONTRIBUTORS_FIRST = '/xmlns:resource/xmlns:creators/xmlns:creator/xmlns:givenName';
    const XPATH_CONTRIBUTORS_LAST = '/xmlns:resource/xmlns:creators/xmlns:creator/xmlns:familyName';
    const XPATH_CONTRIBUTORS_AFF = '/xmlns:resource/xmlns:creators/xmlns:creator/xmlns:affiliation';

    const XPATH_TITLE = '/xmlns:resource/xmlns:titles/xmlns:title';
    const XPATH_PUBLISHER = '/xmlns:resource/xmlns:publisher';
    const XPATH_YEAR = '/xmlns:resource/xmlns:publicationYear';
    const XPATH_KEYWORDS = '/xmlns:resource/xmlns:subjects/xmlns:subject';
    const XPATH_LANG = '/xmlns:resource/xmlns:language';
    const XPATH_DOCTYPE = '/xmlns:resource/xmlns:resourceType/@resourceTypeGeneral';
    const XPATH_ABSTRACT = '/xmlns:resource/xmlns:descriptions/xmlns:description[@descriptionType="Abstract"]';

    protected $_xmlNamespace = array(
        'xmlns' => 'http://datacite.org/schema/kernel-4'
    );

    /**
     * @var array
     */
    private $_decodedJson = [];

    /**
     * Extension de Ccsd_Externdoc
     * @param string $id
     * @param DOMDocument $xmlDom
     * @return null
     */
    static public function createFromXML($id, $xmlDom)
    {
        $doc = new Ccsd_Externdoc_Datacite($id);
        $doc->setDomPath(new DOMXPath($xmlDom));
        $doc->registerNamespace();

        // Est-ce qu'on remplit ici ? Pour avoir la possibilité de remplir avec un JSON aussi ?
        return $doc;
    }

    /**
     * Création d'un Doc Datacite à partir d'un JSON
     * @param string $id
     * @param string $json
     * @return Ccsd_Externdoc_Datacite|null
     */
    static public function createFromJson($id, $json)
    {
        $array = json_decode($json);

        if (empty($array) || !isset($array['data'])) {
            return null;
        }

        $doc = new self($id);
        $doc->setDataArray($array['data']);

        return $doc;
    }

    /**
     * On set les données. Nécessaire à toute utilisation d'un objet DataCite
     * @param $array
     */
    public function setDataArray($array)
    {
        $this->_decodedJson = $array;
    }

    /**
     * @return mixed
     */
    public function getDoi()
    {
        return $this->_id;
    }

    /**
     * @param string $defaultLang
     * @return array
     */
    public function getTitle($defaultLang='en')
    {
        $title = $this->getValue(self::XPATH_TITLE);
        $title = empty($title) ? "" : $title;

        // Transformation du titre en tableau avec la clé comme langue
        return $this->metasToLangArray($title, $defaultLang);

    }

    /**
     * @param string $defaultLang
     * @return array
     */
    public function getAbstract($defaultLang='en')
    {
        $title = $this->getValue(self::XPATH_ABSTRACT);
        $title = empty($title) ? "" : $title;

        // Transformation du titre en tableau avec la clé comme langue
        return $this->metasToLangArray($title, $defaultLang);
    }

    /**
     * Pour les mots-clés. Il est difficile de détecter la langue automatiquement alors qu'il y a généralement 1 mot.
     * On utilise donc la langue du document ou la langue par défaut
     *
     * @param string $defaultLang
     * @return array
     */
    public function getKeywords($defaultLang='en')
    {
        $doclang = $this->getDocLang($defaultLang);

        $keywords = $this->getValue(self::XPATH_KEYWORDS);
        return empty($keywords) ? [] : [$doclang => $keywords];
    }

    /**
     * @param string $defaultLang
     * @return DOMNodeList|string
     */
    public function getDocLang($defaultLang='en')
    {
        $doclang = $this->getValue(self::XPATH_LANG);
        return empty($doclang) ? $defaultLang : $doclang;
    }

    /**
     * @return string
     */
    public function getDate()
    {
        $year = $this->getValue(self::XPATH_YEAR);
        return empty($year) ? "" : $year;
    }

    /**
     * @return string
     */
    public function getPublisher()
    {
        $publisher = $this->getValue(self::XPATH_PUBLISHER);
        return empty($publisher) ? "" : $publisher;
    }

    public function getType()
    {
        $doctype = $this->getValue(self::XPATH_DOCTYPE);
        $doctype = empty($doctype) ? "" : $doctype;
        
        if ($doctype == "Software") {
            return 'SOFTWARE';
        }

        // TODO : lister les autres types de documents et leur mapping avec ceux du CCSD

        return "";
        
    }

    public function getAuthors()
    {
        $fullNames = $this->getValue(self::XPATH_COMPLETE_AUTHOR);
        $fullNames = is_array($fullNames) ? $fullNames : [$fullNames];

        $firstNames = $this->getValue(self::XPATH_CONTRIBUTORS_FIRST);
        $firstNames = is_array($firstNames) ? $firstNames : [$firstNames];

        $lastNames = $this->getValue(self::XPATH_CONTRIBUTORS_LAST);
        $lastNames = is_array($lastNames) ? $lastNames : [$lastNames];

        // Todo : En faire quelque chose !
        $affiliations = $this->getValue(self::XPATH_CONTRIBUTORS_AFF);
        $affiliations = is_array($affiliations) ? $affiliations : [$affiliations];

        return $this->formateAuthors($fullNames, $firstNames, $lastNames, $affiliations);
    }

    /**
     * @return array
     */
    public function getMetadatas()
    {
        // Si les métas ont déjà été chargées, on ne les recréent pas
        if (!empty($this->_metas)) {
            return $this->_metas;
        }

        $this->_metas = [];
        $this->_metas[self::META] = [];
        $this->_metas[self::AUTHORS] = [];

        foreach ($this->_wantedTags as $metakey) {

            $meta = "";

            switch ($metakey) {
                case self::META_TITLE :
                    $meta = $this->getTitle();
                    break;
                case self::META_LANG :
                    $meta = $this->getDocLang();
                    break;
                case self::META_DATE:
                    $meta = $this->getDate();
                    break;
                case self::META_PUBLISHER :
                    $meta = $this->getPublisher();
                    break;
                case self::META_KEYWORD :
                    $meta = $this->getKeywords();
                    break;
                case self::META_ABSTRACT :
                    $meta = $this->getAbstract();
                    break;
                default:
                    break;
            }

            if (!empty($meta)) {
                $this->_metas[self::META][$metakey] = $meta;
            }
        }

        // Récupération de la langue du premier titre
        $titleLang = isset($this->_metas[self::META_TITLE]) ? array_keys($this->_metas[self::META_TITLE])[0] : '';

        // Ajout de la langue
        $this->_metas[self::META_LANG] = $this->formateLang($this->getDocLang(), $titleLang);

        $this->_metas[self::META_IDENTIFIER]["doi"] = $this->_id;
        $this->_metas[self::AUTHORS] = $this->getAuthors();

        $this->_metas[self::DOC_TYPE] = $this->_type;
        return $this->_metas;
    }

}