<?php

class Ccsd_Externdoc_Arxiv extends Ccsd_Externdoc
{
    /**
     * @var string
     */
    protected $_type = "UNDEFINED";

    /**
     * @var string
     */
    protected $_idtype = "arxiv";

    // Liste des metas intermédiaires pour Arxiv
    const INTER_DESCRIPTION = "Description";
    const INTER_SUBJECTS = "Subjects";
    const INTER_CREATORS = "Creators";

    // Tableau de correspondance Meta Intermediaire => XPath
    protected $_traductionArray = array(
        self::ERROR => '/oai:OAI-PMH/oai:error',

        self::META_TITLE => '/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata/oai_dc:dc/dc:title',
        self::META_DATE => '/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata/oai_dc:dc/dc:date',
        self::META_IDENTIFIER => '/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata/oai_dc:dc/dc:identifier',

        self::INTER_DESCRIPTION => '/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata/oai_dc:dc/dc:description',
        self::INTER_SUBJECTS => '/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata/oai_dc:dc/dc:subject',

        self::INTER_CREATORS => '/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata/oai_dc:dc/dc:creator'
    );

    // Tableau de correspondance Meta => Metas Intermediaires
    protected $_interToMetas = array(
        self::META_DOMAIN => [self::INTER_SUBJECTS],
        self::META_ABSTRACT => [self::INTER_DESCRIPTION],
        self::META_COMMENT => [self::INTER_DESCRIPTION]
    );

    // Tableau de correspondance Auteur => Metas Intermediaires
    protected $_interToAuthors = array(
        self::AUTHORS => [self::INTER_CREATORS]
    );

    // Namespace nécessaires à la récupération des XPath
    protected $_xmlNamespace = array(
        'oai' => 'http://www.openarchives.org/OAI/2.0/',
        'oai_dc' => 'http://www.openarchives.org/OAI/2.0/oai_dc/',
        'dc' => 'http://purl.org/dc/elements/1.1/'
    );

    // C'est pas terrible qu'il y ait besoin d'une base (surtout seulement pour Arxiv)
    /** @var Zend_Db_Adapter_Abstract  */
    protected $_dbAdapter;

    /**
     * Ccsd_Externdoc_Document constructor.
     * @param string $id
     * @param DOMDocument $metas
     */
    static public function createFromXML($id, $xmlDom)
    {
        $doc = new self($id);
        $doc->setDomPath(new DOMXPath($xmlDom));
        $doc->registerNamespace();

        if (!$doc->buildMetadatas()) {
            return null;
        }

        return $doc;
    }

    /*
     * Traduction des Metas d'Arxiv pour coller au format de HAL
     * Division en 2 fonctions - traductSubject et traductSubjects
     *
     * @param $subjects : string OR array
     *
     * @return $domains
     */

    public function treatdomain($interMetas, $internames)
    {
        $domains = array();

        if (isset($interMetas[$internames[0]])) {
            $subjects = $interMetas[$internames[0]];

            if (!is_array($subjects))
                $subjects =  [$subjects];

            foreach ($subjects as $subject) {

                // Séparation du domain - subdomain. Les 2 infos sont encodées dans dc:subject
                $subjectArray = explode(" - ", $subject);

                if (isset($subjectArray[0]) && isset($subjectArray[1])) {
                    $subject = $subjectArray[1];
                }

                if (isset($this->_dbAdapter)) {
                    $db = $this->_dbAdapter;
                    $sql = $db->select()->from('REF_DOMAIN_ARXIV', 'code')->where('LIBELLE = ?', $subject);
                    $res = $db->fetchOne($sql);
                    if ($res) {
                        $domains[] = $res;
                    }
                }
            }
        }

        return $domains;
    }

    /* Séparation de la metadonnée "description" en abstract/comment
     *
     * @param $description
     *
     * @return $abstract
     * @return $comment
     */

    public function treatabstract($interMetas, $internames)
    {
        $abstract = [];

        if (isset($interMetas[$internames[0]])) {

            if (!is_array($interMetas[$internames[0]]))
                $description[] = $interMetas[$internames[0]];
            else
                $description = $interMetas[$internames[0]];

            $dl = new Ccsd_Detectlanguage();

            foreach ($description as $desc) {
                if (!preg_match("/^Comment:/", $desc)) {
                    $abs = Ccsd_Tools::nl2space($desc);
                    $langueid = $dl->detect($abs);
                    $lang = count($langueid) && isset($langueid['langid']) ? $langueid['langid'] : 'en';
                    $abstract = array($lang => $abs);
                }
            }
        }
        return $abstract;
    }

    public function treatcomment($interMetas, $internames)
    {
        if (isset($interMetas[$internames[0]])) {

            if (!is_array($interMetas[$internames[0]]))
                $description[] = $interMetas[$internames[0]];
            else
                $description = $interMetas[$internames[0]];

            foreach ($description as $desc) {
                if (preg_match("/^Comment:/", $desc))
                    return Ccsd_Tools::nl2space(trim(mb_substr($desc, 8)));
            }
        }
        return "";
    }

    /* Traduction du tableau des auteurs :
     * Séparation des noms et prénoms pour chaque auteur
     *
     * @param $authors : array (0 => "Doe, J.", 1 => "Smith, J.") ou string si auteur unique
     *
     * @return $autors: array (
     *              0 => array(firstname => "J.", lastname => "Doe"),
     *              1 => array(firstname => "J.", lastname => "Smith"))
     */

    public function treatauthors($interMetas, $internames)
    {
        $finalAuthors = [];

        if (isset($interMetas[$internames[0]])) {
            $authors = $interMetas[$internames[0]];

            if (!is_array($authors))
                $authors =  [$authors];

            foreach ($authors as $author)
                $finalAuthors[] = $this->separateFirstLastNames($author);
        }

        return $finalAuthors;
    }

    public function setAdapter($dbApater)
    {
        $this->_dbAdapter = $dbApater;
    }
}