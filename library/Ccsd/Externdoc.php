<?php

/**
 * La classe Ccsd_Externdoc
 * 
 * Pour ajouter la récupération d'une nouvelle métadonnées :
 * 1- Ajout de la correspondance 'META' => 'XPath' dans $_traductionArray
 * 2- Si la meta a un besoin de traitement
 *      a. Création de la/des constantes intermédiaires nécessaires
 *      b. Ajout de la correspondance 'META' => liste des 'META INTER'
 *      c. Création de la fonction de traitement avec le nom treatmetaname
 * 
 * @author S. Denoux
 */

abstract class Ccsd_Externdoc
{
    // Identifiant externe du document
    public $_id;

    /**
     * Type de Document
     * @var string
     */
    protected $_type = "";
    protected $_idtype = "";
    const DOC_TYPE = "doctype";

    /**
     * Dans l'idéal, ça devrait être un tableau d'objet META cf Hal_Metadatas.
     * Malheureusement, pour l'instant, on est coincé avec ce tableau
     * @var array
     */
    public $_metas = [];

    /**
     * @var DOMXPath
     */
    protected $_domXPath = null;

    /** @var string[] */
    protected $_xmlNamespace;

    /** @var string[] */
    protected $_interToMetas;
    // Liste des metas définissables dans HAL

    const META                  = "metas";
    const META_TITLE            = "title";
    const META_SUBTITLE         = "subtitle";
    const META_LANG             = "language";
    const META_DATE             = "date";
    const META_ABSTRACT         = "abstract";
    const META_KEYWORD          = "keyword";
    const META_JOURNAL          = "journal";
    const META_MESH             = "mesh";
    const META_VOLUME           = "volume";
    const META_SERIE            = "serie";
    const META_PAGE             = "page";
    const META_IDENTIFIER       = "identifier";
    const META_COMMENT          = "comment";
    const META_DOMAIN           = "domain";
    const META_ISSUE            = "issue";
    const META_CITY             = "city";
    const META_COUNTRY          = "country";
    const META_BIBLIO           = "ref_biblio";
    const META_BIBLIO_TITLE     = "j_title";
    const META_PUBLISHER        = "publisher";
    const META_PUBLOCATION      = "publicationLocation";
    const META_BOOKTITLE        = "bookTitle";
    const META_CONFTITLE        = "conferenceTitle";
    const META_CONFDATESTART    = "conferenceStartDate";
    const META_CONFDATEEND      = "conferenceEndDate";
    const META_CONFLOCATION     = "conferenceLocation";
    const META_CONFISBN         = "conferenceISBN";
    const META_PROCEEDINGSTITLE = "proceedingsTitle";
    const META_SERIESEDITOR     = "seriesEditor";
    const META_CLASSIFICATION   = "classification";
    const META_SEEALSO          = "seeAlso";
    const META_FUNDING          = "funding";
    const META_ANRPROJECT       = "anrProject";
    const META_EUROPEANPROJECT  = "europeanProject";
    const META_ISBN          = "ISBN";
    const AUTHORS            = "authors";
    const CONFAUTHORS        = 'confAuthors';
    const AUTHORS_FIRST      = "firstname";
    const AUTHORS_LAST       = "lastname";
    const AUTHORS_INITIALS   = "initials";
    const ERROR              = "iderror";
    const REFERENCES         = "references";
    const STRUCTURES         = "structures";

    protected $_wantedTags = array(
        self::ERROR,
        self::META_TITLE,
        self::META_SUBTITLE,
        self::META_LANG,
        self::META_DATE,
        self::META_ABSTRACT,
        self::META_KEYWORD,
        self::META_JOURNAL,
        self::META_MESH,
        self::META_SERIE,
        self::META_VOLUME,
        self::META_PAGE,
        self::META_IDENTIFIER,
        self::META_COMMENT,
        self::META_DOMAIN,
        self::META_ISSUE,
        self::META_CITY,
        self::META_COUNTRY,
        self::META_BOOKTITLE,
        self::META_CONFTITLE,
        self::META_CONFDATESTART,
        self::META_CONFDATEEND,
        self::META_BIBLIO,
        self::META_BIBLIO_TITLE,
        self::META_PUBLISHER,
        self::META_PUBLOCATION,
        self::META_CONFLOCATION,
        self::META_CONFISBN,
        self::META_PROCEEDINGSTITLE,
        self::META_SERIESEDITOR,
        self::META_ISBN,
        self::META_CLASSIFICATION,
        self::META_SEEALSO,
        self::META_FUNDING,
        self::META_ANRPROJECT,
        self::META_EUROPEANPROJECT
    );

    /**
     * Ccsd_Externdoc constructor.
     * @param string $id
     */
    public function __construct($id)
    {
        $this->_id = $id;
    }

    /**
     * Création d'un Doc Crossref à partir d'un XPATH
     * L'objet Crossref est seulement une factory pour un sous-type réel.
     * @param string $id
     * @param DOMDocument $xmlDom
     */
    abstract static public function createFromXML($id, $xmlDom);

    /**
     * @param $path
     */
    public function setDomPath($path)
    {
        $this->_domXPath = $path;
    }

    /**
     * @return DOMXPath
     */
    public function getDomPath()
    {
        return $this->_domXPath;
    }

    protected function registerNamespace()
    {
        foreach ($this->_xmlNamespace as $key => $namespace) {
            $this->_domXPath->registerNamespace($key, $namespace);
        }
    }

    /**
     * @param DOMNodeList $nodes
     * @return string[]|string
     */
    protected function getNodesListValue($nodes) {

        if (! isset($nodes)) {
            return [];
        }
        if ($nodes->length == 0) {
            return '';
        }
        // Children : tableau de DOMElements
        // Unique élément : l'élément est une string
        if ($nodes->length == 1) {
            return Ccsd_Tools::space_clean($nodes[0]->nodeValue);
            // Multiple éléments : ajoutés dans un tableau
        } else  {
            $values = [];
            foreach ($nodes as $child) {
                $values[] = Ccsd_Tools::space_clean($child->nodeValue);
            }
            return $values;
        }
    }

    /**
     * @param DOMXPath $xpathContext
     * @param string $value
     * @param DOMNode $node
     * @return string|string[]
     */
    protected function getNodesValue($xpathContext, $value, $node=null)
    {
        if ($node) {
            $children = $xpathContext->query($value, $node);
        } else {
            $children = $xpathContext->query($value);
        }
        return $this->getNodesListValue($children);
    }

    /**
     * @param $value
     * @param DOMNode $nodeXpath
     * @return string|string[]
     */
    protected function getValue($value, $nodeXpath=null)
    {
        if ($nodeXpath) {
            $root = $nodeXpath;
        } else {
            $root = $this->getDomPath();
        }
        $children = $root->query($value);
        return $this->getNodesListValue($children);
    }

    /**
     * Recherche si pour un identifiant donnée, il existe déjà cette valeur en base
     * @param Zend_Db_Adapter_Abstract $dbAdapter
     * @param string $type
     * @param string $id : identifiant
     * @return array
     */
    static public function hasCopy($dbAdapter, $type, $id)
    {
        return $dbAdapter->fetchAll($dbAdapter->select()->from(array("dhc" => "DOC_HASCOPY"), "dhc.DOCID")
            ->join(array("d" => "DOCUMENT"), "d.DOCID = dhc.DOCID", "IDENTIFIANT")
            ->where("dhc.CODE LIKE '" . $type . "' AND dhc.LOCALID LIKE ?", $id));
    }

    /**
     * Retourne l'e type'identifiant de la métadonnée
     * @return string
     */
    public function getID()
    {
        return $this->_id;
    }

    /**
     * @param string $id
     */
    public function setID($id)
    {
        $this->_id = $id;
    }

    /**
     * Retourne les métadonnées sous la forme de tableau attendue par HAL
     * @return array
     */
    public function getMetadatas()
    {
        $this->_metas[self::DOC_TYPE] = $this->_type;
        return $this->_metas;
    }

    /**
     * @param $metas
     */
    public function setMetadatas($metas)
    {
        $this->_metas = $metas;
    }

    /**
     * Retourne le type de document (pour l'instant directement le type HAL)
     * @return string
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * Récupère la valeur de la 1ere valeur de la requete XPATH
     * @param DOMXPath $xpath
     * @param string $query
     * @param DOMNode $context
     * @return bool|string
     */
    public function getFirstValueFromXPath($xpath, $query, $context = null)
    {
        $nodeList = $xpath->query($query, $context);
        if ($nodeList->length != 0) {
            return trim(Ccsd_Tools::space_clean($nodeList[0]->nodeValue), '.˙ ');
        }
        return false;
    }

    /** Création de la classe Ccsd_Referentiels_Journal à partir des paramètres spécifiques
     *
     * @param $journaltitle : string
     * @param $shortname : string
     * @param $issn : string
     * @param $eissn : string
     *
     * @return  Ccsd_Referentiels_Journal
     */
    protected function formateJournal($journaltitle, $shortname, $issn, $eissn)
    {
        if ( (!isset($journaltitle) || $journaltitle == '')
            && (!isset($shortname) || $shortname == '')
            && (!isset($issn) || $issn == '')
            && (!isset($eissn) || $eissn == ''))
            return null;
        // Debug cas existant...
        if (is_array($journaltitle)) {
            Ccsd_Tools::panicMsg(__FILE__,__LINE__, "journaltitle is an array(first: " .  $journaltitle[0] . ")");
        }
        if (is_array($shortname)) {
            Ccsd_Tools::panicMsg(__FILE__,__LINE__, "shortname is an array(first: " .  $shortname[0] . ")");
        }
        if (is_array($issn)) {
            Ccsd_Tools::panicMsg(__FILE__,__LINE__, "issn is an array (first: " .  $issn[0] . ")");
        }
        if (is_array($eissn)) {
            Ccsd_Tools::panicMsg(__FILE__,__LINE__, "eissn is an array (first: " .  $eissn[0] . ")");
        }
        $param = 'title_t:"' . $journaltitle . '" OR issn_s:"' . $issn . '" OR eissn_s:"' . $eissn . '"';

        $solrResult = Ccsd_Referentiels_Journal::search($param, 1);

        if (isset($solrResult[0]['docid']))
            return new Ccsd_Referentiels_Journal($solrResult[0]['docid']);
        else
            return new Ccsd_Referentiels_Journal(0, ['VALID' => 'INCOMING', 'JID' => '', 'JNAME' => $journaltitle, 'SHORTNAME' => $shortname, 'ISSN' => $issn, 'EISSN' => $eissn, 'PUBLISHER' => '', 'URL' => '']);
    }

    /** Création de la classe Ccsd_Referentiels_Anrproject ou Ccsd_Referentiels_Europeanproject ou Meta Funding simple
     *
     * @param $funding
     * @param $fundingname
     * @param $fundingdoi
     * @param $fundingcode
     *
     * @return : Ccsd_Referentiels_Anrproject | Ccsd_Referentiels_Europeanproject | array
     */

    protected function formateFunding($funding, $fundingname, $fundingdoi, $fundingcode)
    {
        /** TODO : Funding à terminer */
        if ( (!isset($funding) || $funding == '')
            && (!isset($fundingname) || $fundingname == '')
            && (!isset($fundingdoi) || $fundingdoi == '')
            && (!isset($fundingcode) || $fundingcode == ''))
            return null;


        $finalFunding = [];


        foreach ($funding as $i => $fund) {
            $paramAnr = 'anrProjectTitle_t:"' . $fundingname[$i] . '" OR anrProjectReference_s:"' . $fundingcode[$i]. '"';
            $paramEur = 'europeanProjectTitle_t:"' . $fundingname[$i] . '" OR europeanProjectCallId_s:"' . $fundingcode[$i]. '"';
            $solrResultAnr = Ccsd_Referentiels_Anrproject::search($paramAnr, 1);
            $solrResultEur = Ccsd_Referentiels_Europeanproject::search($paramEur, 1);

            if (isset($solrResultAnr[0]['docid']))
                return new Ccsd_Referentiels_Anrproject($solrResultAnr[0]['docid']);
            else if (isset($solrResultEur[0]['docid'])){
                return new Ccsd_Referentiels_Europeanproject($solrResultEur[0]['docid']);
            }

            foreach ($fundingname as $fundname) {
                if (strpos($fund, $fundname) !== false) {
                    $finalFunding[$i]['name'] = $fundname;
                    break;
                }
            }

            foreach ($fundingdoi as $funddoi) {
                if (strpos($fund, $funddoi) !== false) {
                    $finalFunding[$i]['doi'] = $funddoi;
                    break;
                }
            }

            foreach ($fundingcode as $fundcode) {
                if (strpos($fund, $fundcode) !== false) {
                    $finalFunding[$i]['code'] = $fundcode;
                    break;
                }
            }

        }


        return $finalFunding;
    }

    /**
     * On recrée les auteurs à partir des tableaux de Noms Complet / Prénoms / Noms
     * @param string[] $fullNames
     * @param string[] $firstNames
     * @param string[] $lastNames
     * @param string[] $affiliations
     * @param string[] $orcids
     * @return array
     * @deprecated // TO DELETE  DON'T USE: BIG BUG!!!!
     */
    protected function formateAuthors($fullNames, $firstNames, $lastNames, $affiliations = [], $orcids = [])
    {
        $finalAuthors = [];

        // Boucle sur chaque 'auteur'
        foreach ($fullNames as $i => $fullName) {

            foreach ($firstNames as $firstname) {
                $firstname = self::cleanFirstname($firstname);

                // Le prénom doit se trouver dans l'information complète de l'auteur
                if (strpos($fullName, $firstname) !== false) {
                    $finalAuthors[$i]['firstname'] = $firstname;
                    break;
                }
            }

            foreach ($lastNames as $lastName) {
                // Le nom doit se trouver dans l'information complète de l'auteur
                if (strpos($fullName, $lastName) !== false) {
                    $finalAuthors[$i]['lastname'] = $lastName;
                    break;
                }
            }

            foreach ($orcids as $orcid) {
                // L'orcid doit se trouver dans l'information complète de l'auteur
                if (strpos($fullName, $orcid) !== false) {
                    $finalAuthors[$i]['orcid'] = $orcid;
                    break;
                }
            }

            foreach ($affiliations as $affiliation) {
                // L'affiliation doit se trouver dans l'information complète de l'auteur
                if (strpos($fullName, $affiliation) !== false) {
                    $finalAuthors[$i]['affiliation'] = $affiliation;
                    break;
                }
            }
        }

        return $finalAuthors;
    }

    /**
     * Formatage de la langue. Soit celle récupérée dans le XML du document, soit par détection de la langue du titre ou une langue par défaut
     * Et transformation en ISO1
     *
     * @param string $lang
     * @param string $titleLang
     * @param string $defaultLang
     * @return string
     */
    protected function formateLang($lang, $titleLang, $defaultLang = 'en')
    {
        // Choix de la langue
        $lang = empty($lang) ? empty($titleLang) ? $defaultLang : $titleLang : $lang;

        // Conversion de la langue
        $dl = new Ccsd_Locale();
        $lang = $dl->convertIso2ToIso1($lang);

        // Vérification de la validité de la langue
        return $dl->langExists($lang) ? $lang : $defaultLang;
    }

    /** Detection de la metadonnée "LANG" à partir de la metadonnee "TITLE" du document
     *
     * @param  $title string
     * @param  $defaultLang string
     * @return  string
     */

    static public function detectLangs($title, $defaultLang = 'en')
    {
        $dl = new Ccsd_Detectlanguage();

        $langueid = $dl->detect($title);
        // On prend la langue détectée dans le cas où la probabilité est supérieure à 0.9
        $lang = is_array($langueid) && isset($langueid['langid']) && isset($langueid['proba']) && $langueid['proba'] > 0.9 ? $langueid['langid'] : $defaultLang;

        return $lang;
    }

    /**
     * Transformation d'une méta (soit string si unique, soit tableau si pls langues)
     * en un tableau clé = langue / valeur = meta dans cette langue
     *
     * @param $meta : string | array
     * @param $defaultLang
     * @return array
     */
    protected function metasToLangArray($meta, $defaultLang)
    {
        $finalMeta = [];

        if (!is_array($meta)) {
            $meta = [$meta];
        }

        foreach ($meta as $m) {
            // Détection de la langue de la Meta
            $detectedTitleLang = self::detectLangs($m, $defaultLang);

            // On ne permet pas d'avoir plusieurs fois la même langue
            if (!array_key_exists($detectedTitleLang, $finalMeta)) {
                $finalMeta[$detectedTitleLang] = $m;
            }
        }

        return $finalMeta;
    }

    /**
     * @param string $firstname
     * @return string
     */
    static public function cleanFirstname($firstname)
    {
        $firstnames = explode(" ", $firstname);

        // Le prénom est séparé en 2 part un espace
        if (array_key_exists(1, $firstnames)) {
            if (strlen($firstnames[1])==1) {
                // S'il y a seulement une initiale pour la seconde partie, on la supprime (Jean)
                return $firstnames[0];
            } else if (strlen($firstnames[1])==2 && $firstnames[1][1] == '.') {
                // S'il y a une initiale suivie d'un point pour la seconde partie, on la supprime (Jean R.)
                return $firstnames[0];
            }
        }

        return $firstname;
    }

    /**
     * @param $value
     * @return mixed
     */
    protected function arrayToString($value) {
        if (is_array($value)) {
            return $value[0];
        } else {
            return $value;
        }
    }

    /**
     * @param $date
     * @return string
     */
    protected function addZeroInDate($date)
    {
        if (strlen($date) == 1) {
            return "0".$date;
        } else {
            return $date;
        }
    }

    /**
     * On prend le plus long en considérant que l'autre risque d'être une initiale
     * @param $one
     * @param $second
     * @return bool
     */
    static public function chooseFirstname($one, $second)
    {
        return strlen($one) > strlen($second) ? $one : $second;
    }

    /**
     * @param $name
     * @return bool
     */
    static public function isInitial($name)
    {
        if (strlen($name)==1) {
            // J
            return true;
        } else if (strlen($name)==2 && $name[1] == '.') {
            // J.
            return true;
        } else if (strlen($name)==5 && $name[1] == '.' && $name[4] == '.') {
            // J.-P.
            return true;
        }

        return false;
    }

    /**
     * @param $aut1
     * @param $aut2
     * @return bool
     */
    static public function isConsideredSameAuthor($aut1, $aut2)
    {
        // Il manque un nom de famille
        if (!isset($aut1[self::AUTHORS_LAST]) || !isset($aut2[self::AUTHORS_LAST])) {
            return false;
        }

        // Ils n'ont pas le même nom de famille
        if (strtolower($aut1[self::AUTHORS_LAST]) != strtolower($aut2[self::AUTHORS_LAST])) {
            return false;
        }

        // Les prénoms sont les mêmes
        if (strtolower($aut1[self::AUTHORS_FIRST]) == strtolower($aut2[self::AUTHORS_FIRST])) {
            return true;
        }

        // L'un des prénoms est une initiale et c'est la même initiale que l'autre
        if ((self::isInitial($aut1[self::AUTHORS_FIRST]) || self::isInitial($aut2[self::AUTHORS_FIRST])) &&
            strtolower($aut1[self::AUTHORS_FIRST][0]) == strtolower($aut2[self::AUTHORS_FIRST][0])) {
            return true;
        }

        // à priori, on arrive pas jusque là
        return false;
    }

    /**
     * @param array $autToMerge
     * @param array $autToKeep
     * @return  array
     */
    static public function merge2Authors($autToMerge, $autToKeep)
    {
        $mergedAuth = array_merge($autToMerge, $autToKeep);
        // Dans le cas du prénom, l'option proposée par le DOI que l'on choisit d'habitude au moment du merge n'est pas toujours la meilleure !
        $mergedAuth[self::AUTHORS_FIRST] = self::chooseFirstname($autToMerge[self::AUTHORS_FIRST], $autToKeep[self::AUTHORS_FIRST]);
        return $mergedAuth;
    }

    /**
     * Fusion des metadatas de manière récursive
     * @param $metasToMerge : tableau de metadonnées à fusionner
     * @param $metasToKeep : tableau de metadonnées à fusionner, prioritairement conservées lors de la fusion
     *
     * @return array
     */
    static public function mergeAuthorAndStructMetas($metasToMerge, $metasToKeep)
    {
        $finalMetas = array();

        if (!empty($metasToMerge) && !empty($metasToKeep)) {


            // Merge les auteurs
            // S'il existe seulement dans l'un ou l'autre des tableaux à fusionner ==> Ajouté au tableau final
            // Si le 'lastname' + 'firstname' ou 'premiere lettre firstname' sont égaux ==> On merge l'auteur
            $mergedAut = array();
            foreach ($metasToMerge[self::AUTHORS] as $k => $autToMerge) {

                $merged = false;

                foreach ($metasToKeep[self::AUTHORS] as $k2 => $autToKeep) {

                    // Merge au final inférieur
                    if (self::isConsideredSameAuthor($autToMerge, $autToKeep)) {
                        $finalMetas[self::AUTHORS][] = self::merge2Authors($autToMerge, $autToKeep);
                        $mergedAut[$k2] = true;
                        $merged = true;
                    }
                }

                // Ajout des auteurs qui n'ont pas été fusionné
                if (!$merged) {
                    $finalMetas[self::AUTHORS][] = $metasToMerge[self::AUTHORS][$k];
                }
            }

            // Ajout des auteurs qui n'ont pas été fusionné
            foreach ($metasToKeep[self::AUTHORS] as $k2 => $autToKeep) {
                if (!isset($mergedAut[$k2])) {
                    $finalMetas[self::AUTHORS][] = $metasToKeep[self::AUTHORS][$k2];
                }
            }

            $finalMetas[self::STRUCTURES] = [];

            if (isset($metasToKeep[self::STRUCTURES]) && isset($metasToMerge[self::STRUCTURES])) {
                $finalMetas[self::STRUCTURES] = array_merge($metasToMerge[self::STRUCTURES], $metasToKeep[self::STRUCTURES]);
            } else if (isset($metasToKeep[self::STRUCTURES])) {
                $finalMetas[self::STRUCTURES] = $metasToKeep[self::STRUCTURES];
            } else if (isset($metasToMerge[self::STRUCTURES])) {
                $finalMetas[self::STRUCTURES] = $metasToMerge[self::STRUCTURES];
            }

        } else if (!empty($metasToMerge)) {
            $finalMetas = $metasToMerge;
        } else if (!empty($metasToKeep)) {
            $finalMetas = $metasToKeep;
        }

        return $finalMetas;
    }

    /**
     * Construction des metadatas :
     * 1- Récupération du XML par requête curl au service spécifique
     * 2- Traduction du XML en métadonnées intermédiaires
     * 3- Traduction des métadonnées intermédiaires en métadonnées HAL
     *
     * @return bool : si false, on peut récupérer l'erreur avec getError()
     */
    public function buildMetadatas()
    {
        $interMetas = $this->xmlToInterMetas($this->_domXPath);

        if (isset($interMetas[self::ERROR]) || sizeof($interMetas) == 0) {
            return false;
        }

        $this->_metas = $this->interMetasToMetas($interMetas);

        return true;
    }

    /**
     * 2- Traduction du XML en métadonnées intermédiaires grâce à un tableau de correspondance (NomMetaIntermediaire => XPath) défini dans la Classe Enfant
     *
     * @param $xpath DOMXPath
     *
     * @return array
     */
    protected function xmlToInterMetas($xpath)
    {
        $interMetas = [];

        foreach ($this->_traductionArray as $interMeta => $value) {
            $children = $xpath->query($value);

            if (isset($children)) {
                // Children : tableau de DOMElements
                // Unique élément : l'élément est une string
                if ($children->length == 1) {
                    foreach ($children as $child) {
                        $interMetas[$interMeta] = Ccsd_Tools::space_clean($child->nodeValue);
                    }
                    // Multiple éléments : ajoutés dans un tableau
                } else if ($children->length > 1) {
                    if (!isset($interMetas[$interMeta]))
                        $interMetas[$interMeta] = array();

                    foreach ($children as $child) {
                        array_push($interMetas[$interMeta], Ccsd_Tools::space_clean($child->nodeValue));
                    }
                }
            }
        }

        return $interMetas;
    }

    /**
     * 3- Traduction des métadonnées intermédiaires en métadonnées HAL en appliquant des traitements à certaines metadonnées (globale ou spécifique à la classe enfant)
     *
     * @param $interMetas : array
     *
     * @return array
     */
    public function interMetasToMetas($interMetas)
    {
        $finalMetas = [];
        $finalMetas[self::META] = [];
        $finalMetas[self::AUTHORS] = [];

        // Copie des metadonnees intermediaires qui ne nécessitent pas de traitement
        foreach ($this->_traductionArray as $interMeta => $value) {
            if (in_array($interMeta, $this->_wantedTags) && isset($interMetas[$interMeta]) && !empty($interMetas[$interMeta])) {
                $finalMetas[self::META][$interMeta] = $interMetas[$interMeta];
            }
        }

        // Traitement des metadonnees spécifiques
        $this->treatMetas($interMetas, $finalMetas);

        $metas = $finalMetas[self::META];

        // Choix de la langue par défaut (Langue donnée dans le XML ou 'en')
        $documentHasLang = array_key_exists(self::META_LANG, $metas) && !empty($metas[self::META_LANG]);

        $defaultLang = $documentHasLang ? $metas[self::META_LANG] : 'en';

        // Transforme titre sous forme de string à un titre sous forme de tableau (On utilise la langue détectée plutôt que la langue du document)
        if (array_key_exists(self::META_TITLE, $metas) && !empty($metas[self::META_TITLE])) {
            $metas[self::META_TITLE] = $this->metasToLangArray($metas[self::META_TITLE], $defaultLang);
            $detectedTitleLang = array_keys($metas[self::META_TITLE])[0];
        }

        // Transforme l'abstract sous forme de string à un titre sous forme de tableau
        if (array_key_exists(self::META_ABSTRACT, $metas) && !empty($metas[self::META_ABSTRACT])) {
            $metas[self::META_ABSTRACT] = $this->metasToLangArray($metas[self::META_ABSTRACT], $defaultLang);
        }


        // Choix de la langue du document (Langue par défaut ou langue détectée)
        $dl = new Ccsd_Locale();
        if (!$documentHasLang) {
            $metas[self::META_LANG] = isset($detectedTitleLang) ? $detectedTitleLang : $defaultLang;
        }

        // Passage de la langue en minuscule
        $metas[self::META_LANG] = strtolower($metas[self::META_LANG]);

        // Conversion de la langue
        $metas[self::META_LANG] = $dl->convertIso2ToIso1($metas[self::META_LANG]);

        // Vérification de la validité de la langue
        $metas[self::META_LANG] = $dl->langExists($metas[self::META_LANG]) ? $metas[self::META_LANG] : 'en';


        // Détecte un DOI
        if (isset($metas[self::META_IDENTIFIER])) {
            $tmp = $this->detectDOI($metas[self::META_IDENTIFIER]);
            $metas[self::META_IDENTIFIER] = array();

            if (isset($tmp) && $tmp != "")
                $metas[self::META_IDENTIFIER]['doi'] = $tmp;
        }

        if ($this->_idtype != 'pdf') {
            $metas[self::META_IDENTIFIER][$this->_idtype] = $this->_id;
        }

        // Transforme les mots-clés sous forme de string à un titre sous forme de tableau
        if (array_key_exists(self::META_KEYWORD, $metas) && !empty($metas[self::META_KEYWORD])) {
            $metas[self::META_KEYWORD] = array($metas[self::META_LANG] => $metas[self::META_KEYWORD]);
        }

        // Transforme la date
        if (array_key_exists(self::META_DATE, $metas) && !empty($metas[self::META_DATE])) {
            $metas[self::META_DATE] = Ccsd_Tools::str2date($metas[self::META_DATE]);
        }

        $finalMetas[self::META] = $metas;

        return $finalMetas;
    }

    /** Mise à Jour des metas avec les traitements spécifiques
     *
     * @param $interMetas : metadonnées à traduire
     * @param $metas : metadonnées transformées par la fonction
     *
     */
    private function treatMetas($interMetas, &$metas)
    {
        // La fonction de traitement dépend du nom de la métadonnée à créer
        foreach ($this->_interToMetas as $metaname => $internames) {

            $functionName = "treat" . $metaname;
            $meta = $this->$functionName($interMetas, $internames);

            if (isset($meta))
                $metas[self::META][$metaname] = $meta;
        }

        if (method_exists($this, 'treatauthors')) {
            $metas[self::AUTHORS] = $this->treatauthors($interMetas, $this->_interToAuthors[self::AUTHORS]);

            // if (empty($metas[self::AUTHORS]) && !empty($this->_interToAuthors[self::CONFAUTHORS])) {
                /** ARGH!!!  @var Ccsd_Externdoc_Crossref_Conference $this
                 * This is oldies... can't be executed
                 */
            //      $metas[self::AUTHORS] = $this->treatconfauthors($interMetas, $this->_interToAuthors[self::CONFAUTHORS]);
            // }
        }

        if (isset($interMetas[self::STRUCTURES])) {
            $metas[self::STRUCTURES] = $interMetas[self::STRUCTURES];
        }
    }

    /** Detection d'un identifiant DOI dans la metadonnee "IDENTIFIER"
     *
     * @param $identifiers : array
     *
     * @return string
     */
    private function detectDOI($identifiers)
    {
        if (gettype($identifiers) == "array") {
            foreach ($identifiers as $identifier) {
                if (preg_match("/(10\..+)$/", $identifier, $match)) {
                    return $match[1];
                }
            }
        } else if (preg_match("/(10\..+)$/", $identifiers, $match)) {
            return $match[1];
        }
        return "";
    }

    /** Traduction du tableau des auteurs :
     * Séparation des noms et prénoms pour chaque auteur
     *
     * @param $author  string
     *
     * @return array(firstname => "J.", lastname => "Doe")
     */
    protected function separateFirstLastNames($author)
    {
        $author .= ", ";
        list($lastname, $firstname) = explode(", ", $author);

        return array(
            'lastname' => $lastname,
            'firstname' => $firstname,
        );
    }

    /**
     * Les informations de l'auteur sont récupérées de plusieurs manières :
     * $interMetas[$internames[0]] => totalité de l'information d'un auteur sous forme de string
     * $interMetas[$internames[$i]] => information triée par paramètre (firstname, lastname, etc)
     *
     * Il faut trier les informations par auteur pour coller au format HAL :
     * @param array $interMetas
     * @param array $internames
     * @return array (
     *              0 => array(firstname => "J.", lastname => "Doe"),
     *              1 => array(firstname => "J.", lastname => "Smith"))
     */
    protected function createAuthorArray($interMetas, $internames)
    {
        $finalAuthors = [];
        $personnames =[];

        // Récupération des informations auteurs au complet
        if (isset($interMetas[$internames[0]])) {
            $personnames = $interMetas[$internames[0]];

            if (!is_array($personnames)) {
                $personnames = [$personnames];
            }
        }

        $i = 0;
        // Boucle sur chaque 'auteur'
        while (isset($personnames[$i])) {
            $j = 1;
            // Boucle sur ses différentes informations (firstname, middlename, lastname, email, quality)
            while ($j < sizeof($internames)) {
                if (isset($interMetas[$internames[$j]])) {
                    $authorParam = $interMetas[$internames[$j]];
                    if (!is_array($authorParam))
                        $authorParam = [$authorParam];
                    for ($k = 0; $k < sizeof($authorParam); $k++) {
                        if ($internames[$j] == self::AUTHORS_FIRST) {
                            $authorParam[$k] = self::cleanFirstname($authorParam[$k]);
                        }
                        // Chaque paramètre doit se trouver dans l'information complète de l'auteur
                        if (strpos($personnames[$i], $authorParam[$k]) !== false)
                            $finalAuthors[$i][$internames[$j]] = $authorParam[$k];
                    }
                }
                $j++;
            }
            $i++;
        }

        return $finalAuthors;
    }

    /** Traduction de la date
     * @param array $interMetas
     * @param array $internames
     * @return string
     */
    public function treatdate($interMetas, $internames)
    {
        $yearconst  = $internames[0];
        $monthconst = $internames[1];
        $dayconst   = $internames[2];

        $dateString = "";

        if (array_key_exists($dayconst, $interMetas)) {
            $dateString .= $this->arrayToString($interMetas[$dayconst]) . '-';
        }

        if (array_key_exists($monthconst, $interMetas)) {
            $dateString .= $this->arrayToString($interMetas[$monthconst]) . '-';
        }

        if (array_key_exists($yearconst, $interMetas)) {
            $dateString .= $this->arrayToString($interMetas[$yearconst]);
        }

        return date('Y-m-d', strtotime($dateString));
    }
}