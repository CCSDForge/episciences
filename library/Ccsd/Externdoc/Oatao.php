<?php

class Ccsd_Externdoc_Oatao extends Ccsd_Externdoc
{
    /**
     * @var string
     */
    protected $_idtype = "oatao";

    // Liste des metas intermédiaires pour Arxiv
    const INTER_CREATORS = "creators";

    // Path vers le type de document
    const XPATH_TYPE = '/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata/oai_dc:dc/dc:type';

    // Tableau de correspondance Meta Intermediaire => XPath
    protected $_traductionArray = array(
        self::ERROR => '/oai:OAI-PMH/oai:error',
        self::META_TITLE => '/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata/oai_dc:dc/dc:title',
        self::META_DATE => '/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata/oai_dc:dc/dc:date',
        self::META_IDENTIFIER => '/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata/oai_dc:dc/dc:relation',
        self::META_KEYWORD => '/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata/oai_dc:dc/dc:subject',
        self::META_ABSTRACT => '/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata/oai_dc:dc/dc:description',
        self::INTER_CREATORS => '/oai:OAI-PMH/oai:GetRecord/oai:record/oai:metadata/oai_dc:dc/dc:creator'
    );

    // Tableau de correspondance Meta => Metas Intermediaires
    protected $_interToMetas = array();

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

            if (!is_array($authors))
                $authors =  [$authors];

            foreach ($authors as $author)
                $finalAuthors[] = $this->separateFirstLastNames($author);
        }

        return $finalAuthors;
    }

    public function getType()
    {
        $type = $this->getValue(self::XPATH_TYPE);

        foreach ($type as $t) {
            if ($t == "Conference or Workshop Item") {
                return 'COMM';
            }
        }

        return '';
    }
}