<?php

class Ccsd_Externdoc_Inspire extends Ccsd_Externdoc
{
    /**
     * @var string
     */
    protected $_idtype = "inspire";

    const INTER_CREATORS = "creators";
    const INTER_JOURNAL = "jtitle";

    protected $_traductionArray = array(
        self::META_TITLE => '/collection/dc:dc/dc:title',
        self::META_IDENTIFIER => '/collection/dc:dc/dc:identifier',
        self::META_DATE => '/collection/dc:dc/dc:date',
        self::INTER_JOURNAL => '/collection/dc:dc/journal/publication',
        self::META_KEYWORD => '/collection/dc:dc/dc:subject',
        self::META_ABSTRACT => '/collection/dc:dc/dc:description',
        self::INTER_CREATORS => '/collection/dc:dc/dc:creator',
    );

    // Tableau de correspondance Meta => Metas Intermediaires
    protected $_interToMetas = array(self::META_JOURNAL => [self::INTER_JOURNAL]);

    // Tableau de correspondance Auteur => Metas Intermediaires
    protected $_interToAuthors = array(
        self::AUTHORS => [self::INTER_CREATORS]
    );

    protected $_xmlNamespace = array(
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


    /* Création du Referentiel Journal :
     *
     * @param $journal : array
     *
     * @return Ccsd_Referentiels_Journal
     */

    public function treatjournal($interMetas, $internames)
    {
        if (isset($interMetas[$internames[0]])) {
            return $this->formateJournal($interMetas[$internames[0]], "", "", "");
        } else {
            return null;
        }

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
}