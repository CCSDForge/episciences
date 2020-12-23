<?php

class Ccsd_Externdoc_Bibcode extends Ccsd_Externdoc
{
    /**
     * Type de Document
     * @var string
     */
    protected $_type = "";

    /**
     * @var string
     */
    protected $_idtype = "bibcode";

    const XPATH_DOCTYPE = "/xmlns:records/xmlns:record/@type";

    const INTER_DATE = "interdate";
    const INTER_FIRSTPAGE = "firstpage";
    const INTER_LASTPAGE = "lastpage";
    const INTER_JOURNAL = "interjournal";
    const INTER_CREATORS = "creators";

    protected $_traductionArray = array(
        self::META_TITLE => '/xmlns:records/xmlns:record/xmlns:title',
        self::META_VOLUME => '/xmlns:records/xmlns:record/xmlns:volume',
        self::META_KEYWORD => '/xmlns:records/xmlns:record/xmlns:keywords/xmlns:keyword',
        self::META_ABSTRACT => '/xmlns:records/xmlns:record/xmlns:abstract',
        self::META_IDENTIFIER => '/xmlns:records/xmlns:record/xmlns:DOI',
        self::INTER_DATE => "/xmlns:records/xmlns:record/xmlns:pubdate",
        self::INTER_FIRSTPAGE => "/xmlns:records/xmlns:record/xmlns:page",
        self::INTER_LASTPAGE => "/xmlns:records/xmlns:record/xmlns:lastpage",
        self::INTER_JOURNAL => '/xmlns:records/xmlns:record/xmlns:journal',
        self::INTER_CREATORS => '/xmlns:records/xmlns:record/xmlns:author'
    );

    // Tableau de correspondance Meta => Metas Intermediaires
    protected $_interToMetas = array(
        self::META_DATE => [self::INTER_DATE],
        self::META_PAGE => [self::INTER_FIRSTPAGE, self::INTER_LASTPAGE],
        self::META_JOURNAL => [self::INTER_JOURNAL]
    );

    // Tableau de correspondance Auteur => Metas Intermediaires
    protected $_interToAuthors = array(
        self::AUTHORS => [self::INTER_CREATORS]
    );

    protected $_xmlNamespace = array(
        'xmlns' => 'http://ads.harvard.edu/schema/abs/1.1/abstracts'
    );

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

    public function getType()
    {
        $doctype = $this->getValue(self::XPATH_DOCTYPE);
        if ($doctype == "ARTICLE") {
            $this->_type = "ART";
        }

        return $this->_type;
    }

    /* Traduction de la date :
     *
     * @param $date : string
     *
     * @return string
     */

    public function treatdate($interMetas, $internames)
    {
        if (isset($interMetas[$internames[0]])) {

            $date = $interMetas[$internames[0]];
            return date('Y-m', strtotime($date));
        }

        return "";
    }

    /* Traduction de la page :
     *
     * @param $firstpage : string
     * @param $lastpage : string
     *
     * @return string
     */

    public function treatpage($interMetas, $internames)
    {
        if (isset($interMetas[$internames[0]]) && isset($interMetas[$internames[1]])) {
            $firstpage = $interMetas[$internames[0]];
            $lastpage = $interMetas[$internames[1]];

            $firstpage .= ' - ' . $lastpage;

            return trim($firstpage, '- ');
        }

        return "";
    }

    /* Création du Referentiel Journal :
     *
     * @param $journal : array
     *
     * @return Ccsd_Referentiels_Journal
     */

    public function treatjournal($interMetas, $internames)
    {
        if (isset($interMetas[$internames[0]])) {

            // On prend le premier journal trouvé s'il y en a plusieurs
            if (is_array($interMetas[$internames[0]])) {
                $interMetas[$internames[0]] = $interMetas[$internames[0]][0];
            }

            // Séparation du titre du journal avec le reste des informations
            $interMetas[$internames[0]] = substr($interMetas[$internames[0]], 0, strpos($interMetas[$internames[0]], ','));
        } else {
            $interMetas[$internames[0]] = "";
        }

        return $this->formateJournal($interMetas[$internames[0]], "", "", "");
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

            if (!is_array($authors)) {
                $authors = [$authors];
            }

            foreach ($authors as $author) {
                $finalAuthors[] = $this->separateFirstLastNames($author);
            }
        }

        return $finalAuthors;
    }
}