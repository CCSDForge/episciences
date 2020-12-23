<?php

class Ccsd_Externdoc_Cern extends Ccsd_Externdoc
{
    /**
     * @var string
     */
    protected $_idtype = "cern";

    const INTER_CREATORS = "creators";

    protected $_traductionArray = array(
        self::META_TITLE => '/collection/dc:dc/dc:title',
        self::META_ABSTRACT => '/collection/dc:dc/dc:description',
        self::META_DATE => '/collection/dc:dc/dc:date',
        self::META_COMMENT => 'j_title',
        self::META_LANG => '/collection/dc:dc/dc:language',
        self::META_IDENTIFIER => '/collection/dc:dc/dc:doi',
        self::INTER_CREATORS => '/collection/dc:dc/dc:creator'
    );

    // Tableau de correspondance Meta => Metas Intermediaires
    protected $_interToMetas = array();

    // Tableau de correspondance Auteur => Metas Intermediaires
    protected $_interToAuthors = array(
        self::AUTHORS => [self::INTER_CREATORS]
    );

    protected $_xmlNamespace = array(
        'xmlns' => 'http://ads.harvard.edu/schema/abs/1.1/abstracts',
        'oai' => 'http://www.openarchives.org/OAI/2.0/',
        'oai_dc' => 'http://www.openarchives.org/OAI/2.0/oai_dc/',
        'dc' => 'http://purl.org/dc/elements/1.1/'
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